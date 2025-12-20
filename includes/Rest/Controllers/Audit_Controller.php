<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\Router;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Audit_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'audit';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/recent',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'recent' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);
	}

	public function perm_access(): bool {
		return Router::can_access();
	}

	public function recent( WP_REST_Request $request ): WP_REST_Response {
		$limit = (int) $request->get_param( 'limit' );
		if ( $limit <= 0 ) {
			$limit = 25;
		}
		$limit = min( 100, $limit );

		$entity_type = $request->get_param( 'entity_type' );
		$action      = $request->get_param( 'action' );

		global $wpdb;
		$audit_table = $wpdb->prefix . 'tightship_audit';
		$users_table = $wpdb->users;

		$where  = array( '1=1' );
		$params = array();

		if ( is_string( $entity_type ) && $entity_type !== '' ) {
			$where[]  = 'a.entity_type=%s';
			$params[] = sanitize_key( $entity_type );
		}

		if ( is_string( $action ) && $action !== '' ) {
			$where[]  = 'a.action=%s';
			$params[] = sanitize_key( $action );
		}

		$sql = "SELECT a.id, a.action, a.entity_type, a.entity_id, a.actor_id, a.payload, a.created_at, u.display_name
			FROM {$audit_table} a
			LEFT JOIN {$users_table} u ON u.ID=a.actor_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY a.id DESC
			LIMIT {$limit}";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();

		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$r['id']        = (int) $r['id'];
			$r['entity_id'] = (int) $r['entity_id'];
			$r['actor_id']  = (int) $r['actor_id'];

			$payload = array();
			if ( isset( $r['payload'] ) && is_string( $r['payload'] ) && $r['payload'] !== '' ) {
				$tmp = json_decode( $r['payload'], true );
				if ( is_array( $tmp ) ) {
					$payload = $tmp;
				}
			}
			$r['payload'] = $payload;

			$out[] = $r;
		}

		return new WP_REST_Response( array( 'ok' => true, 'items' => $out ), 200 );
	}
}
