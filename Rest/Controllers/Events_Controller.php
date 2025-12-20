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

final class Events_Controller extends WP_REST_Controller {

	private const TYPES = array( 'meeting', 'deadline', 'task', 'reminder', 'call' );
	private const PRIORITIES = array( 'low', 'medium', 'high' );

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'events';
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/done',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'toggle_done' ),
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
		$table = $wpdb->prefix . 'tightship_events';

		$start = $request->get_param( 'start' );
		$end   = $request->get_param( 'end' );
		$include_done = (bool) $request->get_param( 'include_done' );

		$where = array( '1=1' );
		$params = array();

		if ( is_string( $start ) && $start !== '' ) {
			$where[] = 'start_at >= %s';
			$params[] = sanitize_text_field( $start );
		}
		if ( is_string( $end ) && $end !== '' ) {
			$where[] = 'start_at <= %s';
			$params[] = sanitize_text_field( $end );
		}
		if ( ! $include_done ) {
			$where[] = 'is_done=0';
		}

		$sql = "SELECT id, title, type, start_at, end_at, all_day, priority, project_code, description, reminders, is_done, created_by, created_at, updated_at
			FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY start_at ASC, id ASC LIMIT 200";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out = array();

		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$r['id'] = (int) $r['id'];
			$r['all_day'] = (int) $r['all_day'];
			$r['is_done'] = (int) $r['is_done'];
			$r['created_by'] = (int) $r['created_by'];
			$r['reminders'] = $this->decode_json( $r['reminders'] );
			$out[] = $r;
		}

		return new WP_REST_Response( array( 'ok' => true, 'events' => $out ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$validated = $this->validate_payload( $params, false );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_events';
		$now   = current_time( 'mysql' );

		$insert = array_merge(
			$validated,
			array(
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		$wpdb->insert(
			$table,
			$insert,
			array(
				'%s', // title
				'%s', // type
				'%s', // start_at
				'%s', // end_at
				'%d', // all_day
				'%s', // priority
				'%s', // project_code
				'%s', // description
				'%s', // reminders
				'%d', // is_done
				'%d', // created_by
				'%s', // created_at
				'%s', // updated_at
			)
		);

		$id = (int) $wpdb->insert_id;
		Audit::log( 'event_created', 'event', $id, array( 'title' => $validated['title'] ) );

		return new WP_REST_Response( array( 'ok' => true, 'event_id' => $id ), 200 );
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

		$validated = $this->validate_payload( $params, true );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		if ( empty( $validated ) ) {
			return new WP_Error( 'tightship_bad_request', 'Nothing to update.', array( 'status' => 400 ) );
		}

		$validated['updated_at'] = current_time( 'mysql' );

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_events';

		$wpdb->update(
			$table,
			$validated,
			array( 'id' => $id ),
			$this->format_for( $validated ),
			array( '%d' )
		);

		Audit::log( 'event_updated', 'event', $id, array( 'fields' => array_keys( $validated ) ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function toggle_done( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$done = isset( $params['is_done'] ) ? (int) (bool) $params['is_done'] : 1;

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_events';

		$wpdb->update(
			$table,
			array(
				'is_done'    => $done,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		Audit::log( 'event_done_toggled', 'event', $id, array( 'is_done' => $done ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return new WP_Error( 'tightship_bad_request', 'Invalid ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_events';
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Audit::log( 'event_deleted', 'event', $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function validate_payload( array $params, bool $partial ) {
		$data = array();

		if ( isset( $params['title'] ) || ! $partial ) {
			$title = isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '';
			if ( $title === '' ) {
				return new WP_Error( 'tightship_bad_request', 'Event title required.', array( 'status' => 400 ) );
			}
			$data['title'] = $title;
		}

		if ( isset( $params['type'] ) || ! $partial ) {
			$type = isset( $params['type'] ) ? sanitize_key( (string) $params['type'] ) : 'reminder';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid type.', array( 'status' => 400 ) );
			}
			$data['type'] = $type;
		}

		if ( isset( $params['start_at'] ) || ! $partial ) {
			$start = isset( $params['start_at'] ) ? sanitize_text_field( (string) $params['start_at'] ) : '';
			if ( $start === '' ) {
				return new WP_Error( 'tightship_bad_request', 'start_at required.', array( 'status' => 400 ) );
			}
			$data['start_at'] = $start;
		}

		if ( isset( $params['end_at'] ) ) {
			$end = sanitize_text_field( (string) $params['end_at'] );
			$data['end_at'] = $end !== '' ? $end : null;
		} elseif ( ! $partial ) {
			$data['end_at'] = null;
		}

		if ( isset( $params['all_day'] ) ) {
			$data['all_day'] = (int) (bool) $params['all_day'];
		} elseif ( ! $partial ) {
			$data['all_day'] = 0;
		}

		if ( isset( $params['priority'] ) || ! $partial ) {
			$priority = isset( $params['priority'] ) ? sanitize_key( (string) $params['priority'] ) : 'medium';
			if ( ! in_array( $priority, self::PRIORITIES, true ) ) {
				return new WP_Error( 'tightship_bad_request', 'Invalid priority.', array( 'status' => 400 ) );
			}
			$data['priority'] = $priority;
		}

		if ( isset( $params['project_code'] ) ) {
			$data['project_code'] = sanitize_text_field( (string) $params['project_code'] );
		} elseif ( ! $partial ) {
			$data['project_code'] = null;
		}

		if ( isset( $params['description'] ) ) {
			$data['description'] = sanitize_textarea_field( (string) $params['description'] );
		} elseif ( ! $partial ) {
			$data['description'] = null;
		}

		if ( array_key_exists( 'reminders', $params ) ) {
			$data['reminders'] = null;

			if ( is_array( $params['reminders'] ) ) {
				$san = array();
				foreach ( $params['reminders'] as $r ) {
					if ( ! is_array( $r ) ) {
						continue;
					}
					$min = isset( $r['minutes_before'] ) ? (int) $r['minutes_before'] : null;
					if ( $min === null || $min < 0 || $min > 43200 ) {
						continue;
					}
					$method = isset( $r['method'] ) ? sanitize_key( (string) $r['method'] ) : 'email';
					if ( ! in_array( $method, array( 'email', 'push' ), true ) ) {
						$method = 'email';
					}
					$san[] = array(
						'minutes_before' => $min,
						'method'         => $method,
					);
				}
				if ( ! empty( $san ) ) {
					$data['reminders'] = wp_json_encode( $san );
				}
			}
		} elseif ( ! $partial ) {
			$data['reminders'] = null;
		}

		if ( isset( $params['is_done'] ) ) {
			$data['is_done'] = (int) (bool) $params['is_done'];
		} elseif ( ! $partial ) {
			$data['is_done'] = 0;
		}

		return $data;
	}

	private function decode_json( $value ): array {
		if ( ! is_string( $value ) || $value === '' ) {
			return array();
		}
		$tmp = json_decode( $value, true );
		return is_array( $tmp ) ? $tmp : array();
	}

	private function format_for( array $data ): array {
		$fmt = array();
		foreach ( $data as $k => $v ) {
			if ( in_array( $k, array( 'all_day', 'is_done' ), true ) ) {
				$fmt[] = '%d';
			} else {
				$fmt[] = '%s';
			}
		}
		return $fmt;
	}
}
