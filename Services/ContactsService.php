<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContactsService {

    private AuditService $audit;

    public function __construct() {
        $this->audit = new AuditService();
    }

    public function list(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM " . Tables::contacts() . " ORDER BY updated_at DESC, id DESC LIMIT 500",
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $out = array();
        foreach ( $rows as $r ) {
            $out[] = $this->hydrate( $r );
        }
        return $out;
    }

    public function create( array $data ): int|\WP_Error {
        global $wpdb;

        $name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
        if ( $name === '' ) {
            return Json::error( 'invalid_contact', 'Contact name is required', 400 );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            Tables::contacts(),
            array(
                'name'         => $name,
                'organization' => sanitize_text_field( (string) ( $data['organization'] ?? '' ) ),
                'email'        => sanitize_email( (string) ( $data['email'] ?? '' ) ),
                'phone'        => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
                'tags_json'    => Json::encode( $data['tags'] ?? array() ),
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        $id = (int) $wpdb->insert_id;
        $this->audit->log( 'contact.create', 'contact', $id, array( 'name' => $name ), get_current_user_id() );

        return $id;
    }

    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::contacts(),
            array(
                'name'         => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
                'organization' => sanitize_text_field( (string) ( $data['organization'] ?? '' ) ),
                'email'        => sanitize_email( (string) ( $data['email'] ?? '' ) ),
                'phone'        => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
                'tags_json'    => Json::encode( $data['tags'] ?? array() ),
                'updated_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update contact', 500 );
        }

        $this->audit->log( 'contact.update', 'contact', $id, array(), get_current_user_id() );
        return true;
    }

    private function hydrate( array $r ): array {
        return array(
            'id'           => (int) $r['id'],
            'name'         => (string) $r['name'],
            'organization' => (string) ( $r['organization'] ?? '' ),
            'email'        => (string) ( $r['email'] ?? '' ),
            'phone'        => (string) ( $r['phone'] ?? '' ),
            'tags'         => Json::decode_array( (string) ( $r['tags_json'] ?? '' ) ),
        );
    }
}
