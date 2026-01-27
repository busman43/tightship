<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EventsService {

    private AuditService $audit;

    public function __construct() {
        $this->audit = new AuditService();
    }

    public function list( ?string $start = null, ?string $end = null ): array {
        global $wpdb;

        $where = array();
        $params = array();

        if ( $start ) {
            $where[] = 'starts_at >= %s';
            $params[] = $start;
        }
        if ( $end ) {
            $where[] = 'starts_at <= %s';
            $params[] = $end;
        }

        $sql = "SELECT * FROM " . Tables::events();
        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY starts_at ASC LIMIT 500';

        $prepared = ! empty( $params ) ? $wpdb->prepare( $sql, $params ) : $sql;
        $rows = $wpdb->get_results( $prepared, ARRAY_A );

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

        $starts = (string) ( $data['starts_at'] ?? '' );
        if ( $starts === '' ) {
            return Json::error( 'invalid_starts_at', 'Missing start date/time', 400 );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            Tables::events(),
            array(
                'event_type'     => (string) ( $data['event_type'] ?? 'reminder' ),
                'title'          => (string) ( $data['title'] ?? '' ),
                'description'    => (string) ( $data['description'] ?? '' ),
                'starts_at'      => $starts,
                'ends_at'        => ! empty( $data['ends_at'] ) ? (string) $data['ends_at'] : null,
                'project_code'   => (string) ( $data['project_code'] ?? '' ),
                'priority'       => (string) ( $data['priority'] ?? 'medium' ),
                'reminders_json' => Json::encode( $data['reminders'] ?? array() ),
                'completed'      => ! empty( $data['completed'] ) ? 1 : 0,
                'created_by'     => $actor_id,
                'updated_by'     => $actor_id,
                'created_at'     => $now,
                'updated_at'     => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
        );

        $id = (int) $wpdb->insert_id;
        $this->audit->log( 'event.create', 'event', $id, array( 'title' => (string) ( $data['title'] ?? '' ) ), $actor_id );

        return $id;
    }

    public function update( int $id, array $data, int $actor_id ): bool|\WP_Error {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::events(),
            array(
                'event_type'     => (string) ( $data['event_type'] ?? 'reminder' ),
                'title'          => (string) ( $data['title'] ?? '' ),
                'description'    => (string) ( $data['description'] ?? '' ),
                'starts_at'      => (string) ( $data['starts_at'] ?? '' ),
                'ends_at'        => ! empty( $data['ends_at'] ) ? (string) $data['ends_at'] : null,
                'project_code'   => (string) ( $data['project_code'] ?? '' ),
                'priority'       => (string) ( $data['priority'] ?? 'medium' ),
                'reminders_json' => Json::encode( $data['reminders'] ?? array() ),
                'completed'      => ! empty( $data['completed'] ) ? 1 : 0,
                'updated_by'     => $actor_id,
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update event', 500 );
        }

        $this->audit->log( 'event.update', 'event', $id, array(), $actor_id );
        return true;
    }

    public function toggle_complete( int $id, bool $completed, int $actor_id ): bool|\WP_Error {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::events(),
            array(
                'completed'  => $completed ? 1 : 0,
                'updated_by' => $actor_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%d', '%d', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update event', 500 );
        }

        $this->audit->log( 'event.complete', 'event', $id, array( 'completed' => $completed ), $actor_id );
        return true;
    }

    public function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . Tables::events() . " WHERE id = %d", $id ),
            ARRAY_A
        );
        return is_array( $row ) ? $this->hydrate( $row ) : null;
    }

    private function hydrate( array $r ): array {
        return array(
            'id'          => (int) $r['id'],
            'event_type'  => (string) $r['event_type'],
            'title'       => (string) $r['title'],
            'description' => (string) ( $r['description'] ?? '' ),
            'starts_at'   => (string) $r['starts_at'],
            'ends_at'     => $r['ends_at'] ? (string) $r['ends_at'] : null,
            'project_code'=> (string) ( $r['project_code'] ?? '' ),
            'priority'    => (string) ( $r['priority'] ?? 'medium' ),
            'completed'   => (int) $r['completed'] === 1,
            'reminders'   => Json::decode_array( (string) ( $r['reminders_json'] ?? '' ) ),
        );
    }
}
