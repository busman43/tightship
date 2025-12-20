<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\Router;
use TightShip\Engine\Services\Documents;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Documents_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'documents';
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
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upload',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/revision',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'revision' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/status',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/autoroute',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'autoroute' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);
	}

	public function perm_access(): bool {
		return Router::can_access();
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'status'   => $request->get_param( 'status' ),
			'space_id' => $request->get_param( 'space_id' ),
			'search'   => $request->get_param( 'search' ),
			'limit'    => $request->get_param( 'limit' ),
			'offset'   => $request->get_param( 'offset' ),
		);

		$rows = Documents::list( $args );

		return new WP_REST_Response(
			array(
				'ok'        => true,
				'documents' => $rows,
			),
			200
		);
	}

	public function get( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$doc = Documents::get( $id );
		if ( ! is_array( $doc ) ) {
			return new WP_Error( 'tightship_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'ok' => true, 'document' => $doc ), 200 );
	}

	public function upload( WP_REST_Request $request ) {
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return new WP_Error( 'tightship_no_file', 'No file uploaded.', array( 'status' => 400 ) );
		}

		$meta = array();
		$params = $request->get_params();
		if ( isset( $params['title'] ) ) {
			$meta['title'] = sanitize_text_field( (string) $params['title'] );
		}
		if ( isset( $params['notes'] ) ) {
			$meta['notes'] = sanitize_text_field( (string) $params['notes'] );
		}

		$res = Documents::create_from_upload( $_FILES['file'], $meta );

		if ( empty( $res['ok'] ) ) {
			return new WP_Error( 'tightship_upload_failed', isset( $res['error'] ) ? (string) $res['error'] : 'Upload failed.', array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $res, 200 );
	}

	public function revision( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return new WP_Error( 'tightship_no_file', 'No file uploaded.', array( 'status' => 400 ) );
		}

		$meta = array();
		$params = $request->get_params();
		if ( isset( $params['notes'] ) ) {
			$meta['notes'] = sanitize_text_field( (string) $params['notes'] );
		}

		$res = Documents::add_revision( $id, $_FILES['file'], $meta );
		if ( empty( $res['ok'] ) ) {
			return new WP_Error( 'tightship_revision_failed', isset( $res['error'] ) ? (string) $res['error'] : 'Revision failed.', array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $res, 200 );
	}

	public function status( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$status = isset( $params['status'] ) ? (string) $params['status'] : '';
		$space_id = isset( $params['space_id'] ) ? (int) $params['space_id'] : null;

		$res = Documents::update_status( $id, $status, $space_id );

		if ( empty( $res['ok'] ) ) {
			return new WP_Error( 'tightship_status_failed', isset( $res['error'] ) ? (string) $res['error'] : 'Status update failed.', array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $res, 200 );
	}

	public function autoroute( WP_REST_Request $request ): WP_REST_Response {
		$res = Documents::autoroute_inbox();
		return new WP_REST_Response( $res, 200 );
	}
}
