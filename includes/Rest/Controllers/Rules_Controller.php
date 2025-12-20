<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\Router;
use TightShip\Engine\Services\Audit;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rules_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'rules';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'perm_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'perm_manage' ),
				),
			)
		);
	}

	public function perm_access(): bool {
		return Router::can_access();
	}

	public function perm_manage(): bool {
		return Router::can_manage();
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'tightship_rules';
		$rows  = $wpdb->get_results( "SELECT id, name, active, match_type, pattern, destination_space_id, tags, created_at, updated_at FROM {$table} ORDER BY id ASC", ARRAY_A );

		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$tags = array();
			if ( isset( $r['tags'] ) && is_string( $r['tags'] ) && $r['tags'] !== '' ) {
				$tmp = json_decode( $r['tags'], true );
				if ( is_array( $tmp ) ) {
					$tags = $tmp;
				}
			}
			$r['active'] = (int) $r['active'];
			$r['destination_space_id'] = (int) $r['destination_space_id'];
			$r['tags'] = $tags;
			$out[] = $r;
		}

		return new WP_REST_Response( array( 'ok' => true, 'rules' => $out ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		$pattern = isset( $params['pattern'] ) ? (string) $params['pattern'] : '';
		$match_type = isset( $params['match_type'] ) ? sanitize_key( (string) $params['match_type'] ) : 'contains';
		$dest = isset( $params['destination_space_id'] ) ? (int) $params['destination_space_id'] : 0;
		$active = isset( $params['active'] ) ? (int) (bool) $params['active'] : 1;

		if ( $name === '' || $pattern === '' || $dest <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Name, pattern and destination_space_id required.', array( 'status' => 400 ) );
		}
		if ( ! in_array( $match_type, array( 'contains', 'regex' ), true ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid match_type.', array( 'status' => 400 ) );
		}
		if ( $match_type === 'regex' ) {
			set_error_handler( static function () { return true; } );
			$ok = @preg_match( $pattern, 'test' );
			restore_error_handler();
			if ( $ok === false ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid regex pattern.', array( 'status' => 400 ) );
			}
		}

		$tags = array();
		if ( isset( $params['tags'] ) ) {
			if ( is_array( $params['tags'] ) ) {
				$tags = array_values( array_filter( array_map( 'sanitize_key', $params['tags'] ) ) );
			} elseif ( is_string( $params['tags'] ) ) {
				$tags = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $params['tags'] ) ) ) );
			}
		}

		global $wpdb;
		$spaces_table = $wpdb->prefix . 'tightship_spaces';
		$rules_table  = $wpdb->prefix . 'tightship_rules';

		$exists_space = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$spaces_table} WHERE id=%d LIMIT 1", $dest ) );
		if ( $exists_space <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Destination space does not exist.', array( 'status' => 400 ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$rules_table,
			array(
				'name'                 => $name,
				'active'               => $active,
				'match_type'           => $match_type,
				'pattern'              => $pattern,
				'destination_space_id' => $dest,
				'tags'                 => empty( $tags ) ? null : wp_json_encode( $tags ),
				'created_by'           => (int) get_current_user_id(),
				'created_at'           => $now,
				'updated_at'           => $now,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'rule_created', 'rule', $id, array( 'name' => $name ) );

		return new WP_REST_Response( array( 'ok' => true, 'rule_id' => $id ), 200 );
	}

	public function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$data = array();
		$fmt  = array();

		if ( isset( $params['name'] ) ) {
			$data['name'] = sanitize_text_field( (string) $params['name'] );
			$fmt[] = '%s';
		}
		if ( isset( $params['active'] ) ) {
			$data['active'] = (int) (bool) $params['active'];
			$fmt[] = '%d';
		}
		if ( isset( $params['match_type'] ) ) {
			$mt = sanitize_key( (string) $params['match_type'] );
			if ( ! in_array( $mt, array( 'contains', 'regex' ), true ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid match_type.', array( 'status' => 400 ) );
			}
			$data['match_type'] = $mt;
			$fmt[] = '%s';
		}
		if ( isset( $params['pattern'] ) ) {
			$data['pattern'] = (string) $params['pattern'];
			$fmt[] = '%s';
		}
		if ( isset( $params['destination_space_id'] ) ) {
			$data['destination_space_id'] = (int) $params['destination_space_id'];
			$fmt[] = '%d';
		}
		if ( array_key_exists( 'tags', $params ) ) {
			$tags = array();
			if ( is_array( $params['tags'] ) ) {
				$tags = array_values( array_filter( array_map( 'sanitize_key', $params['tags'] ) ) );
			} elseif ( is_string( $params['tags'] ) ) {
				$tags = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $params['tags'] ) ) ) );
			}
			$data['tags'] = empty( $tags ) ? null : wp_json_encode( $tags );
			$fmt[] = '%s';
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'tightship_bad_request', 'Nothing to update.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$rules_table  = $wpdb->prefix . 'tightship_rules';
		$spaces_table = $wpdb->prefix . 'tightship_spaces';

		if ( isset( $data['destination_space_id'] ) && (int) $data['destination_space_id'] > 0 ) {
			$exists_space = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$spaces_table} WHERE id=%d LIMIT 1", (int) $data['destination_space_id'] ) );
			if ( $exists_space <= 0 ) {
				return new WP_Error( 'tightship_bad_request', 'Destination space does not exist.', array( 'status' => 400 ) );
			}
		}

		if ( isset( $data['match_type'] ) && $data['match_type'] === 'regex' && isset( $data['pattern'] ) ) {
			set_error_handler( static function () { return true; } );
			$ok = @preg_match( (string) $data['pattern'], 'test' );
			restore_error_handler();
			if ( $ok === false ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid regex pattern.', array( 'status' => 400 ) );
			}
		}

		$data['updated_at'] = current_time( 'mysql' );
		$fmt[] = '%s';

		$wpdb->update(
			$rules_table,
			$data,
			array( 'id' => $id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'rule_updated', 'rule', $id, array( 'fields' => array_keys( $data ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$rules_table = $wpdb->prefix . 'tightship_rules';
		$wpdb->delete( $rules_table, array( 'id' => $id ), array( '%d' ) );
		Audit::log( 'rule_deleted', 'rule', $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
