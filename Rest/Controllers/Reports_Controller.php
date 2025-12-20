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

final class Reports_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'reports';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/overview',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'overview' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
			)
		);
	}

	public function perm_access(): bool {
		return Router::can_access();
	}

	public function overview( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$docs_table = $wpdb->prefix . 'tightship_documents';
		$spaces_table = $wpdb->prefix . 'tightship_spaces';
		$events_table = $wpdb->prefix . 'tightship_events';
		$projects_table = $wpdb->prefix . 'tightship_projects';
		$proposals_table = $wpdb->prefix . 'tightship_proposals';

		$docs_total = (int) $wpdb->get_var( "SELECT COUNT(1) FROM {$docs_table}" );
		$docs_by_status = $wpdb->get_results( "SELECT status, COUNT(1) c FROM {$docs_table} GROUP BY status", ARRAY_A );
		$docs_by_space = $wpdb->get_results( "SELECT s.name as space, s.slug as slug, COUNT(d.id) c
			FROM {$spaces_table} s LEFT JOIN {$docs_table} d ON d.space_id = s.id
			GROUP BY s.id ORDER BY s.sort ASC, s.id ASC", ARRAY_A );

		$now = current_time( 'mysql' );
		$next_7 = gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ); // approximate
		$events_upcoming = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$events_table} WHERE is_done=0 AND start_at >= %s AND start_at <= %s", $now, $next_7 ) );

		$projects_active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$projects_table} WHERE status=%s", 'active' ) );
		$proposals_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$proposals_table} WHERE status=%s", 'open' ) );
		$proposals_by_stage = $wpdb->get_results( "SELECT stage, COUNT(1) c FROM {$proposals_table} GROUP BY stage", ARRAY_A );

		$uploads = wp_get_upload_dir();
		$uploads_path = $uploads['basedir'] ?? '';
		$storage = array(
			'uploads_basedir' => is_string( $uploads_path ) ? $uploads_path : '',
			'tightship_dir'   => is_string( $uploads_path ) ? trailingslashit( $uploads_path ) . 'tightship' : '',
		);

		return new WP_REST_Response(
			array(
				'ok' => true,
				'metrics' => array(
					'documents_total' => $docs_total,
					'documents_by_status' => is_array( $docs_by_status ) ? $docs_by_status : array(),
					'documents_by_space'  => is_array( $docs_by_space ) ? $docs_by_space : array(),
					'events_upcoming_7d'  => $events_upcoming,
					'projects_active'     => $projects_active,
					'proposals_open'      => $proposals_open,
					'proposals_by_stage'  => is_array( $proposals_by_stage ) ? $proposals_by_stage : array(),
					'storage'             => $storage,
				),
			),
			200
		);
	}
}
