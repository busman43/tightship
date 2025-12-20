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

final class Contacts_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'contacts';
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
		$table = $wpdb->prefix . 'tightship_contacts';

		$search = $request->get_param( 'search' );
		$where = array( '1=1' );
		$params = array();

		if ( is_string( $search ) && $search !== '' ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$where[] = '(name LIKE %s OR organization LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT id, name, role, organization, email, phone, notes, tags, created_by, created_at, updated_at
			FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC, id DESC LIMIT 200";
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

		return new WP_REST_Response( array( 'ok' => true, 'contacts' => $out ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		if ( $name === '' ) {
			return new WP_Error( 'tightship_bad_request', 'name required.', array( 'status' => 400 ) );
		}

		$role = isset( $params['role'] ) ? sanitize_text_field( (string) $params['role'] ) : null;
		$org  = isset( $params['organization'] ) ? sanitize_text_field( (string) $params['organization'] ) : null;
		$email = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : null;
		$phone = isset( $params['phone'] ) ? sanitize_text_field( (string) $params['phone'] ) : null;
		$notes = isset( $params['notes'] ) ? sanitize_textarea_field( (string) $params['notes'] ) : null;
		$tags  = $this->sanitize_tags( $params['tags'] ?? array() );

		if ( $email !== null && $email !== '' && ! is_email( $email ) ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid email.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_contacts';
		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'role'        => $role !== '' ? $role : null,
				'organization'=> $org !== '' ? $org : null,
				'email'       => $email !== '' ? $email : null,
				'phone'       => $phone !== '' ? $phone : null,
				'notes'       => $notes !== '' ? $notes : null,
				'tags'        => empty( $tags ) ? null : wp_json_encode( $tags ),
				'created_by'  => (int) get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' )
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'contact_created', 'contact', $id, array( 'name' => $name ) );

		return new WP_REST_Response( array( 'ok' => true, 'contact_id' => $id ), 200 );
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

		foreach ( array( 'name', 'role', 'organization', 'phone' ) as $field ) {
			if ( array_key_exists( $field, $params ) ) {
				$val = sanitize_text_field( (string) $params[ $field ] );
				$data[ $field ] = $val !== '' ? $val : null;
				$fmt[] = '%s';
			}
		}

		if ( array_key_exists( 'email', $params ) ) {
			$val = sanitize_email( (string) $params['email'] );
			if ( $val !== '' && ! is_email( $val ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid email.', array( 'status' => 400 ) );
			}
			$data['email'] = $val !== '' ? $val : null;
			$fmt[] = '%s';
		}

		if ( array_key_exists( 'notes', $params ) ) {
			$val = sanitize_textarea_field( (string) $params['notes'] );
			$data['notes'] = $val !== '' ? $val : null;
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
		$table = $wpdb->prefix . 'tightship_contacts';

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'contact_updated', 'contact', $id, array( 'fields' => array_keys( $data ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_contacts';
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Audit::log( 'contact_deleted', 'contact', $id );

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
