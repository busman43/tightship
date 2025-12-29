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

final class Templates_Controller extends WP_REST_Controller {

	private const FORMATS = array( 'markdown', 'html', 'text' );

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'templates';
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
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'perm_access' ),
				),
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
		$table = $wpdb->prefix . 'tightship_templates';

		$search = $request->get_param( 'search' );
		$where = array( '1=1' );
		$params = array();

		if ( is_string( $search ) && $search !== '' ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$where[] = '(title LIKE %s OR category LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT id, title, category, format, tags, created_by, created_at, updated_at FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC, id DESC LIMIT 200";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out = array();

		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$r['id'] = (int) $r['id'];
			$r['created_by'] = (int) $r['created_by'];
			$r['tags'] = $this->decode_json( $r['tags'] );
			$out[] = $r;
		}

		return new WP_REST_Response( array( 'ok' => true, 'templates' => $out ), 200 );
	}

	public function get( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_templates';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, category, format, content, tags, created_by, created_at, updated_at FROM {$table} WHERE id=%d", $id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return new WP_Error( 'tightship_not_found', 'Template not found.', array( 'status' => 404 ) );
		}

		$row['id'] = (int) $row['id'];
		$row['created_by'] = (int) $row['created_by'];
		$row['tags'] = $this->decode_json( $row['tags'] );

		return new WP_REST_Response( array( 'ok' => true, 'template' => $row ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$title = isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '';
		$category = isset( $params['category'] ) ? sanitize_text_field( (string) $params['category'] ) : '';
		$format = isset( $params['format'] ) ? sanitize_key( (string) $params['format'] ) : 'markdown';
		$content = isset( $params['content'] ) ? (string) $params['content'] : '';

		if ( $title === '' ) {
			return new WP_Error( 'tightship_bad_request', 'title required.', array( 'status' => 400 ) );
		}
		if ( ! in_array( $format, self::FORMATS, true ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid format.', array( 'status' => 400 ) );
		}

		$tags = $this->sanitize_tags( $params['tags'] ?? array() );

		// Keep content safe-by-default.
		$safe_content = ( $format === 'html' ) ? wp_kses_post( $content ) : wp_strip_all_tags( $content, true );
		if ( $format === 'markdown' ) {
			// Allow plain markdown (strip scripts).
			$safe_content = preg_replace( '/<\/?script\b[^>]*>/i', '', $content ) ?? $content;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_templates';
		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'title'      => $title,
				'category'   => $category !== '' ? $category : null,
				'format'     => $format,
				'content'    => $safe_content,
				'tags'       => empty( $tags ) ? null : wp_json_encode( $tags ),
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s','%s','%s','%s','%s','%d','%s','%s' )
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'template_created', 'template', $id, array( 'title' => $title ) );

		return new WP_REST_Response( array( 'ok' => true, 'template_id' => $id ), 200 );
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
		if ( array_key_exists( 'category', $params ) ) {
			$val = sanitize_text_field( (string) $params['category'] );
			$data['category'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}
		$format = null;
		if ( isset( $params['format'] ) ) {
			$format = sanitize_key( (string) $params['format'] );
			if ( ! in_array( $format, self::FORMATS, true ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid format.', array( 'status' => 400 ) );
			}
			$data['format'] = $format;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'content', $params ) ) {
			$content = (string) $params['content'];
			// Determine effective format.
			$eff = $format;
			if ( $eff === null ) {
				global $wpdb;
				$table = $wpdb->prefix . 'tightship_templates';
				$eff = (string) $wpdb->get_var( $wpdb->prepare( "SELECT format FROM {$table} WHERE id=%d", $id ) );
				if ( $eff === '' ) {
					$eff = 'markdown';
				}
			}

			$safe_content = ( $eff === 'html' ) ? wp_kses_post( $content ) : wp_strip_all_tags( $content, true );
			if ( $eff === 'markdown' ) {
				$safe_content = preg_replace( '/<\/?script\b[^>]*>/i', '', $content ) ?? $content;
			}

			$data['content'] = $safe_content;
			$fmt[] = '%s';
		}
		if ( array_key_exists( 'tags', $params ) ) {
			$tags = $this->sanitize_tags( $params['tags'] );
			$data['tags'] = empty( $tags ) ? null : wp_json_encode( $tags );
			$fmt[] = '%s';
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'tightship_bad_request', 'Nothing to update.', array( 'status' => 400 ) );
		}

		$data['updated_at'] = current_time( 'mysql' );
		$fmt[] = '%s';

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_templates';

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'template_updated', 'template', $id, array( 'fields' => array_keys( $data ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_templates';
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Audit::log( 'template_deleted', 'template', $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function decode_json( $value ): array {
		if ( ! is_string( $value ) || $value === '' ) {
			return array();
		}
		$tmp = json_decode( $value, true );
		return is_array( $tmp ) ? $tmp : array();
	}

	private function sanitize_tags( $raw ): array {
		$tags = array();

		if ( is_array( $raw ) ) {
			$tags = array_values( array_filter( array_map( 'sanitize_key', $raw ) ) );
		} elseif ( is_string( $raw ) ) {
			$tags = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
		}

		return array_values( array_unique( $tags ) );
	}
}
