<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SpacesService {

    public function list(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, slug, name, color, sort_order FROM " . Tables::spaces() . " ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    public function get_by_id( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, slug, name, color, sort_order FROM " . Tables::spaces() . " WHERE id = %d", $id ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    public function create( array $data, int $actor_id ): int {
        global $wpdb;

        $now = current_time( 'mysql' );
        $wpdb->insert(
            Tables::spaces(),
            array(
                'slug'       => (string) $data['slug'],
                'name'       => (string) $data['name'],
                'color'      => (string) ( $data['color'] ?? 'slate' ),
                'sort_order' => (int) ( $data['sort_order'] ?? 0 ),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function update( int $id, array $data, int $actor_id ): bool {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::spaces(),
            array(
                'slug'       => (string) $data['slug'],
                'name'       => (string) $data['name'],
                'color'      => (string) ( $data['color'] ?? 'slate' ),
                'sort_order' => (int) ( $data['sort_order'] ?? 0 ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }
}
