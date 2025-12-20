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

final class Auth_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = Router::NS;
		$this->rest_base = 'auth';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'me' ),
					'permission_callback' => array( $this, 'perm_me' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/login',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'login' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'username' => array( 'type' => 'string', 'required' => true ),
						'password' => array( 'type' => 'string', 'required' => true ),
						'remember' => array( 'type' => 'boolean', 'required' => false ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => array( $this, 'perm_me' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/register',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'register' ),
					'permission_callback' => array( $this, 'perm_register' ),
				),
			)
		);
	}

	public function perm_me(): bool {
		return is_user_logged_in();
	}

	public function perm_register(): bool {
		if ( (bool) get_option( 'users_can_register' ) ) {
			return true;
		}
		return Router::can_manage();
	}

	public function me( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();

		$data = array(
			'ok'     => true,
			'user'   => array(
				'id'           => (int) $user->ID,
				'display_name' => (string) $user->display_name,
				'email'        => (string) $user->user_email,
			),
			'caps'   => array(
				'access' => Router::can_access(),
				'manage' => Router::can_manage(),
			),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function login( WP_REST_Request $request ) {
		if ( is_user_logged_in() ) {
			return $this->me( $request );
		}

		$username = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );
		$remember = (bool) $request->get_param( 'remember' );

		if ( $username === '' || $password === '' ) {
			return new WP_Error( 'tightship_bad_request', 'Missing credentials.', array( 'status' => 400 ) );
		}

		$login = $username;
		if ( is_email( $username ) ) {
			$u = get_user_by( 'email', $username );
			if ( $u instanceof \WP_User ) {
				$login = (string) $u->user_login;
			}
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'tightship_login_failed', $user->get_error_message(), array( 'status' => 401 ) );
		}

		wp_set_current_user( (int) $user->ID );

		Audit::log( 'login', 'user', (int) $user->ID );

		return new WP_REST_Response(
			array(
				'ok'    => true,
				'user'  => array(
					'id'           => (int) $user->ID,
					'display_name' => (string) $user->display_name,
					'email'        => (string) $user->user_email,
				),
				'caps'  => array(
					'access' => Router::can_access(),
					'manage' => Router::can_manage(),
				),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
			200
		);
	}

	public function logout( WP_REST_Request $request ): WP_REST_Response {
		$u = wp_get_current_user();
		Audit::log( 'logout', 'user', (int) $u->ID );
		wp_logout();

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function register( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$name  = isset( $params['full_name'] ) ? sanitize_text_field( (string) $params['full_name'] ) : '';
		$org   = isset( $params['organization'] ) ? sanitize_text_field( (string) $params['organization'] ) : '';
		$email = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		$pass  = isset( $params['password'] ) ? (string) $params['password'] : '';
		$pass2 = isset( $params['password_confirm'] ) ? (string) $params['password_confirm'] : '';

		if ( $email === '' || ! is_email( $email ) ) {
			return new WP_Error( 'tightship_bad_email', 'Valid email required.', array( 'status' => 400 ) );
		}
		if ( $pass === '' || strlen( $pass ) < 8 ) {
			return new WP_Error( 'tightship_bad_password', 'Password must be at least 8 characters.', array( 'status' => 400 ) );
		}
		if ( $pass2 !== '' && $pass2 !== $pass ) {
			return new WP_Error( 'tightship_password_mismatch', 'Passwords do not match.', array( 'status' => 400 ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'tightship_email_exists', 'Email is already registered.', array( 'status' => 409 ) );
		}

		$username = sanitize_user( strstr( $email, '@', true ) ?: $email, true );
		if ( username_exists( $username ) ) {
			$username = $username . wp_rand( 1000, 9999 );
		}

		$user_id = wp_create_user( $username, $pass, $email );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'tightship_register_failed', $user_id->get_error_message(), array( 'status' => 400 ) );
		}

		wp_update_user(
			array(
				'ID'           => (int) $user_id,
				'display_name' => $name !== '' ? $name : $username,
			)
		);

		$user = get_user_by( 'id', (int) $user_id );
		if ( $user instanceof \WP_User ) {
			$user->set_role( 'tightship_member' );
			if ( $org !== '' ) {
				update_user_meta( (int) $user_id, 'tightship_org', $org );
			}
		}

		// Log them in immediately.
		wp_set_current_user( (int) $user_id );
		wp_set_auth_cookie( (int) $user_id, true, is_ssl() );

		Audit::log( 'register', 'user', (int) $user_id, array( 'org' => $org ) );

		return new WP_REST_Response(
			array(
				'ok'    => true,
				'user'  => array(
					'id'           => (int) $user_id,
					'display_name' => $user instanceof \WP_User ? (string) $user->display_name : $username,
					'email'        => $email,
				),
				'caps'  => array(
					'access' => Router::can_access(),
					'manage' => Router::can_manage(),
				),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
			200
		);
	}
}
