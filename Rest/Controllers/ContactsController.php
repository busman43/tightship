<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\ContactsService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContactsController extends BaseController {

    private ContactsService $contacts;

    public function __construct() {
        $this->rest_base = 'contacts';
        $this->contacts = new ContactsService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/contacts',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'list' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'create' ),
                    'permission_callback' => array( $this, 'permission_manage' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/contacts/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'PUT,PATCH',
                    'callback'            => array( $this, 'update' ),
                    'permission_callback' => array( $this, 'permission_manage' ),
                ),
            )
        );
    }

    public function list( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( array( 'items' => $this->contacts->list() ), 200 );
    }

    public function create( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $id = $this->contacts->create( array_map( 'wp_unslash', $data ) );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        return new WP_REST_Response( array( 'id' => (int) $id ), 201 );
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $ok = $this->contacts->update( $id, array_map( 'wp_unslash', $data ) );
        if ( is_wp_error( $ok ) ) {
            return $ok;
        }

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }
}
