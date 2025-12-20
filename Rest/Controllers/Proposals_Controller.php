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

final class Proposals_Controller extends WP_REST_Controller {

	private const STAGES = array( 'concept-note', 'full-application', 'submitted', 'awarded', 'rejected' );
	private const STATUSES = array( 'open', 'closed', 'archived' );

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'proposals';
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
		$table = $wpdb->prefix . 'tightship_proposals';

		$stage = $request->get_param( 'stage' );
		$status = $request->get_param( 'status' );
		$search = $request->get_param( 'search' );

		$where = array( '1=1' );
		$params = array();

		if ( is_string( $stage ) && $stage !== '' ) {
			$where[] = 'stage=%s';
			$params[] = sanitize_key( $stage );
		}
		if ( is_string( $status ) && $status !== '' ) {
			$where[] = 'status=%s';
			$params[] = sanitize_key( $status );
		}
		if ( is_string( $search ) && $search !== '' ) {
			$where[] = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
		}

		$sql = "SELECT id, project_id, title, stage, donor, deadline, amount, currency, status, created_by, created_at, updated_at
				FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC, id DESC LIMIT 200";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as &$r ) {
			$r['id'] = (int) $r['id'];
			$r['project_id'] = $r['project_id'] !== null ? (int) $r['project_id'] : null;
			$r['amount'] = $r['amount'] !== null ? (float) $r['amount'] : null;
			$r['created_by'] = (int) $r['created_by'];
		}

		return new WP_REST_Response( array( 'ok' => true, 'proposals' => is_array( $rows ) ? $rows : array() ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$title = isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '';
		if ( $title === '' ) {
			return new WP_Error( 'tightship_bad_request', 'title required.', array( 'status' => 400 ) );
		}

		$stage = isset( $params['stage'] ) ? sanitize_key( (string) $params['stage'] ) : 'concept-note';
		if ( ! in_array( $stage, self::STAGES, true ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid stage.', array( 'status' => 400 ) );
		}
		$status = isset( $params['status'] ) ? sanitize_key( (string) $params['status'] ) : 'open';
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid status.', array( 'status' => 400 ) );
		}

		$project_id = isset( $params['project_id'] ) ? (int) $params['project_id'] : 0;
		$donor = isset( $params['donor'] ) ? sanitize_text_field( (string) $params['donor'] ) : null;
		$deadline = isset( $params['deadline'] ) ? sanitize_text_field( (string) $params['deadline'] ) : null;
		$currency = isset( $params['currency'] ) ? strtoupper( sanitize_text_field( (string) $params['currency'] ) ) : 'EUR';
		$amount = array_key_exists( 'amount', $params ) && (string) $params['amount'] !== '' ? (string) (float) $params['amount'] : null;

		if ( $currency === '' ) {
			$currency = 'EUR';
		}

		global $wpdb;
		$now = current_time( 'mysql' );
		$table = $wpdb->prefix . 'tightship_proposals';

		$wpdb->insert(
			$table,
			array(
				'project_id' => $project_id > 0 ? (string) $project_id : null,
				'title'      => $title,
				'stage'      => $stage,
				'donor'      => $donor !== '' ? $donor : null,
				'deadline'   => $deadline !== '' ? $deadline : null,
				'amount'     => $amount,
				'currency'   => $currency,
				'status'     => $status,
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' )
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'proposal_created', 'proposal', $id, array( 'title' => $title, 'stage' => $stage ) );

		return new WP_REST_Response( array( 'ok' => true, 'proposal_id' => $id ), 200 );
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

		if ( isset( $params['title'] ) ) {
			$data['title'] = sanitize_text_field( (string) $params['title'] );
			$fmt[] = '%s';
		}
		if ( isset( $params['stage'] ) ) {
			$stage = sanitize_key( (string) $params['stage'] );
			if ( ! in_array( $stage, self::STAGES, true ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid stage.', array( 'status' => 400 ) );
			}
			$data['stage'] = $stage;
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
		if ( array_key_exists( 'project_id', $params ) ) {
			$val = (int) $params['project_id'];
			$data['project_id'] = $val > 0 ? (string) $val : null;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'donor', $params ) ) {
			$val = sanitize_text_field( (string) $params['donor'] );
			$data['donor'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'deadline', $params ) ) {
			$val = sanitize_text_field( (string) $params['deadline'] );
			$data['deadline'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'amount', $params ) ) {
			$val = (string) $params['amount'];
			$data['amount'] = $val !== '' ? (string) (float) $val : null;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'currency', $params ) ) {
			$val = strtoupper( sanitize_text_field( (string) $params['currency'] ) );
			$data['currency'] = $val !== '' ? $val : 'EUR';
			$fmt[] = '%s';
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'tightship_bad_request', 'Nothing to update.', array( 'status' => 400 ) );
		}

		$data['updated_at'] = current_time( 'mysql' );
		$fmt[] = '%s';

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_proposals';

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'proposal_updated', 'proposal', $id, array( 'fields' => array_keys( $data ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_proposals';
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Audit::log( 'proposal_deleted', 'proposal', $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
