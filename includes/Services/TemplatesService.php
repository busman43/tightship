<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TemplatesService {

    private AuditService $audit;

    public function __construct() {
        $this->audit = new AuditService();
    }

    public function list(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, slug, name, category, updated_at FROM " . Tables::templates() . " ORDER BY updated_at DESC, id DESC LIMIT 200",
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    public function get( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . Tables::templates() . " WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return array(
            'id'       => (int) $row['id'],
            'slug'     => (string) $row['slug'],
            'name'     => (string) $row['name'],
            'category' => (string) ( $row['category'] ?? 'general' ),
            'content'  => (string) $row['content'],
            'updated_at' => (string) $row['updated_at'],
        );
    }

    public function create( array $data, int $actor_id ): int|\WP_Error {
        global $wpdb;

        $slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
        $content = wp_kses_post( (string) ( $data['content'] ?? '' ) );

        if ( $slug === '' || $name === '' ) {
            return Json::error( 'invalid_template', 'Template slug and name are required', 400 );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            Tables::templates(),
            array(
                'slug'       => $slug,
                'name'       => $name,
                'category'   => sanitize_key( (string) ( $data['category'] ?? 'general' ) ),
                'content'    => $content,
                'created_by' => $actor_id,
                'updated_by' => $actor_id,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        $id = (int) $wpdb->insert_id;
        $this->audit->log( 'template.create', 'template', $id, array( 'slug' => $slug ), $actor_id );

        return $id;
    }

    public function update( int $id, array $data, int $actor_id ): bool|\WP_Error {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::templates(),
            array(
                'slug'       => sanitize_title( (string) ( $data['slug'] ?? '' ) ),
                'name'       => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
                'category'   => sanitize_key( (string) ( $data['category'] ?? 'general' ) ),
                'content'    => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
                'updated_by' => $actor_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update template', 500 );
        }

        $this->audit->log( 'template.update', 'template', $id, array(), $actor_id );
        return true;
    }
}
