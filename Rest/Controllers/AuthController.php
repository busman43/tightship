<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Core\Capabilities;
use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Support\Json;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AuthController extends BaseController {

    public function __construct() {
        $this->rest_base = 'auth';
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/auth/me',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'me' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/auth/login',
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
            Routes::NAMESPACE,
            '/auth/logout',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'logout' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/auth/register',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'register_user' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'email'      => array( 'type' => 'string', 'required' => true ),
                        'password'   => array( 'type' => 'string', 'required' => true ),
                        'fullName'   => array( 'type' => 'string', 'required' => false ),
                    ),
                ),
            )
        );
    }

    public function me( WP_REST_Request $request ): WP_REST_Response {
        if ( ! is_user_logged_in() ) {
            return new WP_REST_Response(
                array(
                    'loggedIn' => false,
                    'user'     => null,
                ),
                200
            );
        }

        if ( ! current_user_can( Capabilities::CAP_ACCESS ) ) {
            return new WP_REST_Response(
                array(
                    'loggedIn' => true,
                    'user'     => null,
                    'error'    => 'no_access',
                ),
                403
            );
        }

        $u = wp_get_current_user();
        return new WP_REST_Response(
            array(
                'loggedIn' => true,
                'user'     => $this->user_payload( $u ),
            ),
            200
        );
    }

    public function login( WP_REST_Request $request ) {
        $username = sanitize_user( (string) $request->get_param( 'username' ) );
        // Allow email login as well.
        if ( strpos( $username, '@' ) !== false ) {
            $u = get_user_by( 'email', $username );
            if ( $u instanceof WP_User ) {
                $username = (string) $u->user_login;
            }
        }
        $password = (string) $request->get_param( 'password' );
        $remember = (bool) $request->get_param( 'remember' );

        if ( $username === '' || $password === '' ) {
            return Json::error( 'invalid_credentials', 'Username and password are required', 400 );
        }

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $user = wp_signon( $creds, is_ssl() );
        if ( is_wp_error( $user ) ) {
            return Json::error( 'login_failed', $user->get_error_message(), 401 );
        }

        if ( ! ( $user instanceof WP_User ) ) {
            return Json::error( 'login_failed', 'Login failed', 401 );
        }

        // Ensure role/cap access.
        if ( ! user_can( $user, Capabilities::CAP_ACCESS ) ) {
            wp_logout();
            return Json::error( 'forbidden', 'Account has no TightShip access', 403 );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $remember, is_ssl() );

        return new WP_REST_Response(
            array(
                'ok'   => true,
                'user' => $this->user_payload( $user ),
            ),
            200
        );
    }

    public function logout( WP_REST_Request $request ): WP_REST_Response {
        wp_logout();
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    public function register_user( WP_REST_Request $request ) {
        if ( ! (bool) get_option( 'users_can_register' ) ) {
            return Json::error( 'registration_closed', 'Registration is disabled on this site', 403 );
        }

        $email = sanitize_email( (string) $request->get_param( 'email' ) );
        $password = (string) $request->get_param( 'password' );
        $fullName = sanitize_text_field( (string) $request->get_param( 'fullName' ) );

        if ( $email === '' || ! is_email( $email ) ) {
            return Json::error( 'invalid_email', 'Valid email is required', 400 );
        }

        if ( strlen( $password ) < 10 ) {
            return Json::error( 'weak_password', 'Password must be at least 10 characters', 400 );
        }

        if ( email_exists( $email ) ) {
            return Json::error( 'email_exists', 'Email already registered', 409 );
        }

        $username = sanitize_user( current( explode( '@', $email ) ), true );
        if ( username_exists( $username ) ) {
            $username = $username . wp_generate_password( 4, false, false );
        }

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return Json::error( 'register_failed', $user_id->get_error_message(), 500 );
        }

        wp_update_user(
            array(
                'ID'           => $user_id,
                'display_name' => $fullName !== '' ? $fullName : $username,
                'role'         => Capabilities::ROLE_MEMBER,
            )
        );

        $u = get_user_by( 'id', $user_id );
        return new WP_REST_Response(
            array(
                'ok'   => true,
                'user' => $u instanceof WP_User ? $this->user_payload( $u ) : null,
            ),
            201
        );
    }

    private function user_payload( WP_User $u ): array {
        return array(
            'id'          => (int) $u->ID,
            'username'    => (string) $u->user_login,
            'email'       => (string) $u->user_email,
            'displayName' => (string) $u->display_name,
            'canManage'   => user_can( $u, Capabilities::CAP_MANAGE ),
        );
    }
}
