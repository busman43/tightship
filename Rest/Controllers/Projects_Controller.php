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

final class Projects_Controller extends WP_REST_Controller {

	private const STATUSES = array( 'active', 'paused', 'closed', 'archived' );

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'projects';
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
					'permission_callback' => array( $this, 'perm_access' ),
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
					'permission_callback' => array( $this, 'perm_access' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);
	}

	public function perm_access(): bool {
		return Router::can_access();
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'tightship_projects';

		$status = $request->get_param( 'status' );
		$where = '1=1';
		$params = array();

		if ( is_string( $status ) && $status !== '' ) {
			$status = sanitize_key( $status );
			$where = 'status=%s';
			$params[] = $status;
		}

		$sql = "SELECT id, code, name, status, start_at, end_at, notes, created_by, created_at, updated_at FROM {$table} WHERE {$where} ORDER BY updated_at DESC, id DESC LIMIT 200";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as &$r ) {
			$r['id'] = (int) $r['id'];
			$r['created_by'] = (int) $r['created_by'];
		}

		return new WP_REST_Response( array( 'ok' => true, 'projects' => is_array( $rows ) ? $rows : array() ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$code = isset( $params['code'] ) ? strtoupper( sanitize_text_field( (string) $params['code'] ) ) : '';
		$name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		$status = isset( $params['status'] ) ? sanitize_key( (string) $params['status'] ) : 'active';
		$start = isset( $params['start_at'] ) ? sanitize_text_field( (string) $params['start_at'] ) : null;
		$end = isset( $params['end_at'] ) ? sanitize_text_field( (string) $params['end_at'] ) : null;
		$notes = isset( $params['notes'] ) ? sanitize_textarea_field( (string) $params['notes'] ) : null;

		if ( $code === '' || $name === '' ) {
			return new WP_Error( 'tightship_bad_request', 'code and name required.', array( 'status' => 400 ) );
		}
		if ( ! preg_match( '/^[A-Z0-9][A-Z0-9\-]{1,62}$/', $code ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid code format.', array( 'status' => 400 ) );
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid status.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_projects';

		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code=%s LIMIT 1", $code ) );
		if ( $exists > 0 ) {
			return new WP_Error( 'tightship_conflict', 'Project code already exists.', array( 'status' => 409 ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'code'       => $code,
				'name'       => $name,
				'status'     => $status,
				'start_at'   => $start,
				'end_at'     => $end,
				'notes'      => $notes,
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s','%s','%s','%s','%s','%s','%d','%s','%s' )
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'project_created', 'project', $id, array( 'code' => $code, 'name' => $name ) );

		return new WP_REST_Response( array( 'ok' => true, 'project_id' => $id ), 200 );
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
		if ( isset( $params['status'] ) ) {
			$status = sanitize_key( (string) $params['status'] );
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid status.', array( 'status' => 400 ) );
			}
			$data['status'] = $status;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'start_at', $params ) ) {
			$val = sanitize_text_field( (string) $params['start_at'] );
			$data['start_at'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'end_at', $params ) ) {
			$val = sanitize_text_field( (string) $params['end_at'] );
			$data['end_at'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'notes', $params ) ) {
			$val = sanitize_textarea_field( (string) $params['notes'] );
			$data['notes'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'tightship_bad_request', 'Nothing to update.', array( 'status' => 400 ) );
		}

		$data['updated_at'] = current_time( 'mysql' );
		$fmt[] = '%s';

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_projects';

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'project_updated', 'project', $id, array( 'fields' => array_keys( $data ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_projects';
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Audit::log( 'project_deleted', 'project', $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
