<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProjectsService {

    private AuditService $audit;

    public function __construct() {
        $this->audit = new AuditService();
    }

    public function list(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM " . Tables::projects() . " ORDER BY updated_at DESC, id DESC LIMIT 200",
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

    public function create( array $data, int $actor_id ): int|\WP_Error {
        global $wpdb;

        $code = sanitize_text_field( (string) ( $data['code'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );

        if ( $code === '' || $name === '' ) {
            return Json::error( 'invalid_project', 'Project code and name are required', 400 );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            Tables::projects(),
            array(
                'code'       => $code,
                'name'       => $name,
                'donor'      => sanitize_text_field( (string) ( $data['donor'] ?? '' ) ),
                'status'     => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
                'budget'     => isset( $data['budget'] ) && $data['budget'] !== '' ? (string) (float) $data['budget'] : null,
                'start_date' => ! empty( $data['start_date'] ) ? (string) $data['start_date'] : null,
                'end_date'   => ! empty( $data['end_date'] ) ? (string) $data['end_date'] : null,
                'created_by' => $actor_id,
                'updated_by' => $actor_id,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        $id = (int) $wpdb->insert_id;
        $this->audit->log( 'project.create', 'project', $id, array( 'code' => $code, 'name' => $name ), $actor_id );

        return $id;
    }

    public function update( int $id, array $data, int $actor_id ): bool|\WP_Error {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::projects(),
            array(
                'code'       => sanitize_text_field( (string) ( $data['code'] ?? '' ) ),
                'name'       => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
                'donor'      => sanitize_text_field( (string) ( $data['donor'] ?? '' ) ),
                'status'     => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
                'budget'     => isset( $data['budget'] ) && $data['budget'] !== '' ? (string) (float) $data['budget'] : null,
                'start_date' => ! empty( $data['start_date'] ) ? (string) $data['start_date'] : null,
                'end_date'   => ! empty( $data['end_date'] ) ? (string) $data['end_date'] : null,
                'updated_by' => $actor_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update project', 500 );
        }

        $this->audit->log( 'project.update', 'project', $id, array(), $actor_id );
        return true;
    }

    public function get( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . Tables::projects() . " WHERE id = %d", $id ),
            ARRAY_A
        );

        return is_array( $row ) ? $this->hydrate( $row ) : null;
    }

    private function hydrate( array $r ): array {
        return array(
            'id'       => (int) $r['id'],
            'code'     => (string) $r['code'],
            'name'     => (string) $r['name'],
            'donor'    => (string) ( $r['donor'] ?? '' ),
            'status'   => (string) ( $r['status'] ?? 'active' ),
            'budget'   => $r['budget'] !== null ? (float) $r['budget'] : null,
            'start_date' => $r['start_date'] ? (string) $r['start_date'] : null,
            'end_date'   => $r['end_date'] ? (string) $r['end_date'] : null,
        );
    }
}
