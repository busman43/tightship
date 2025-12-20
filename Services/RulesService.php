<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RulesService {

    public function list(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, name, enabled, priority, filename_pattern, project_code_pattern, to_space_id, set_doc_type, set_status, add_tags FROM " . Tables::rules() . " ORDER BY enabled DESC, priority ASC, id ASC",
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        foreach ( $rows as &$row ) {
            $row['enabled'] = (int) $row['enabled'] === 1;
            $row['priority'] = (int) $row['priority'];
            $row['to_space_id'] = $row['to_space_id'] !== null ? (int) $row['to_space_id'] : null;
            $row['add_tags'] = array_values( array_filter( array_map( 'trim', explode( ',', (string) $row['add_tags'] ) ) ) );
        }
        unset( $row );

        return $rows;
    }

    public function create( array $data ): int {
        global $wpdb;

        $now = current_time( 'mysql' );

        $wpdb->insert(
            Tables::rules(),
            array(
                'name'                 => (string) $data['name'],
                'enabled'              => ! empty( $data['enabled'] ) ? 1 : 0,
                'priority'             => (int) ( $data['priority'] ?? 100 ),
                'filename_pattern'      => (string) ( $data['filename_pattern'] ?? '' ),
                'project_code_pattern'  => (string) ( $data['project_code_pattern'] ?? '' ),
                'to_space_id'          => ! empty( $data['to_space_id'] ) ? (int) $data['to_space_id'] : null,
                'set_doc_type'         => (string) ( $data['set_doc_type'] ?? '' ),
                'set_status'           => (string) ( $data['set_status'] ?? '' ),
                'add_tags'             => is_array( $data['add_tags'] ?? null ) ? implode( ',', array_map( 'sanitize_text_field', $data['add_tags'] ) ) : (string) ( $data['add_tags'] ?? '' ),
                'created_at'           => $now,
                'updated_at'           => $now,
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function update( int $id, array $data ): bool {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::rules(),
            array(
                'name'                 => (string) $data['name'],
                'enabled'              => ! empty( $data['enabled'] ) ? 1 : 0,
                'priority'             => (int) ( $data['priority'] ?? 100 ),
                'filename_pattern'      => (string) ( $data['filename_pattern'] ?? '' ),
                'project_code_pattern'  => (string) ( $data['project_code_pattern'] ?? '' ),
                'to_space_id'          => ! empty( $data['to_space_id'] ) ? (int) $data['to_space_id'] : null,
                'set_doc_type'         => (string) ( $data['set_doc_type'] ?? '' ),
                'set_status'           => (string) ( $data['set_status'] ?? '' ),
                'add_tags'             => is_array( $data['add_tags'] ?? null ) ? implode( ',', array_map( 'sanitize_text_field', $data['add_tags'] ) ) : (string) ( $data['add_tags'] ?? '' ),
                'updated_at'           => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    public function apply_rules( array $doc, array $rules ): array {
        $filename = (string) ( $doc['title'] ?? '' );
        $project_code = (string) ( $doc['project_code'] ?? '' );

        foreach ( $rules as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }

            $fn = (string) ( $rule['filename_pattern'] ?? '' );
            $pc = (string) ( $rule['project_code_pattern'] ?? '' );

            if ( $fn !== '' && ! $this->match_pattern( $fn, $filename ) ) {
                continue;
            }
            if ( $pc !== '' && ! $this->match_pattern( $pc, $project_code ) ) {
                continue;
            }

            // Match: apply.
            if ( ! empty( $rule['to_space_id'] ) ) {
                $doc['space_id'] = (int) $rule['to_space_id'];
            }
            if ( ! empty( $rule['set_doc_type'] ) ) {
                $doc['doc_type'] = (string) $rule['set_doc_type'];
            }
            if ( ! empty( $rule['set_status'] ) ) {
                $doc['status'] = (string) $rule['set_status'];
            }
            $tags = is_array( $doc['tags'] ?? null ) ? $doc['tags'] : array();
            $add = $rule['add_tags'] ?? array();
            if ( is_array( $add ) && ! empty( $add ) ) {
                $tags = array_values( array_unique( array_merge( $tags, $add ) ) );
            }
            $doc['tags'] = $tags;

            // First match wins.
            break;
        }

        return $doc;
    }

    private function match_pattern( string $pattern, string $value ): bool {
        $pattern = trim( $pattern );
        if ( $pattern === '' ) {
            return true;
        }

        // If pattern looks like a regex delimiter, use as-is. Else wrap as case-insensitive regex.
        $delim = substr( $pattern, 0, 1 );
        $is_regex = in_array( $delim, array( '/', '#', '~' ), true ) && strrpos( $pattern, $delim ) !== 0;

        $regex = $is_regex ? $pattern : '/' . str_replace( array( '\\*', '\\?' ), array( '.*', '.' ), preg_quote( $pattern, '/' ) ) . '/i';

        set_error_handler(
            static function () {
                // swallow invalid regex warnings.
                return true;
            }
        );
        $ok = preg_match( $regex, $value ) === 1;
        restore_error_handler();

        return $ok;
    }
}
