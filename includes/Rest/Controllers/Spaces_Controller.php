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

final class Spaces_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'spaces';
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
		$table = $wpdb->prefix . 'tightship_spaces';
		$rows  = $wpdb->get_results( "SELECT id, name, slug, description, color, sort, created_at FROM {$table} ORDER BY sort ASC, id ASC", ARRAY_A );
		return new WP_REST_Response( array( 'ok' => true, 'spaces' => is_array( $rows ) ? $rows : array() ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		if ( $name === '' ) {
			return new WP_Error( 'tightship_bad_request', 'Space name required.', array( 'status' => 400 ) );
		}

		$slug = isset( $params['slug'] ) ? sanitize_title( (string) $params['slug'] ) : sanitize_title( $name );
		$desc = isset( $params['description'] ) ? sanitize_textarea_field( (string) $params['description'] ) : '';
		$color = isset( $params['color'] ) ? sanitize_key( (string) $params['color'] ) : 'slate';
		$sort = isset( $params['sort'] ) ? (int) $params['sort'] : 100;

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_spaces';

		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", $slug ) );
		if ( $exists > 0 ) {
			return new WP_Error( 'tightship_conflict', 'Space slug already exists.', array( 'status' => 409 ) );
		}

		$now = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $desc,
				'color'       => $color,
				'sort'        => $sort,
				'created_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'space_created', 'space', $id, array( 'name' => $name, 'slug' => $slug ) );

		return new WP_REST_Response( array( 'ok' => true, 'space_id' => $id ), 200 );
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
		if ( isset( $params['description'] ) ) {
			$data['description'] = sanitize_textarea_field( (string) $params['description'] );
			$fmt[] = '%s';
		}
		if ( isset( $params['color'] ) ) {
			$data['color'] = sanitize_key( (string) $params['color'] );
			$fmt[] = '%s';
		}
		if ( isset( $params['sort'] ) ) {
			$data['sort'] = (int) $params['sort'];
			$fmt[] = '%d';
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'tightship_bad_request', 'Nothing to update.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_spaces';

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'space_updated', 'space', $id, array( 'fields' => array_keys( $data ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_spaces';

		$inbox_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", 'inbox' ) );
		if ( $id === $inbox_id ) {
			return new WP_Error( 'tightship_forbidden', 'Cannot delete Inbox space.', array( 'status' => 403 ) );
		}

		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		Audit::log( 'space_deleted', 'space', $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
