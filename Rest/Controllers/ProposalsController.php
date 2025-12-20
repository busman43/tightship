<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\ProposalsService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProposalsController extends BaseController {

    private ProposalsService $proposals;

    public function __construct() {
        $this->rest_base = 'proposals';
        $this->proposals = new ProposalsService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/proposals',
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
            '/proposals/(?P<id>\d+)',
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
        return new WP_REST_Response( array( 'items' => $this->proposals->list() ), 200 );
    }

    public function create( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $id = $this->proposals->create( array_map( 'wp_unslash', $data ), get_current_user_id() );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        return new WP_REST_Response( $this->proposals->get( (int) $id ), 201 );
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $ok = $this->proposals->update( $id, array_map( 'wp_unslash', $data ), get_current_user_id() );
        if ( is_wp_error( $ok ) ) {
            return $ok;
        }

        return new WP_REST_Response( $this->proposals->get( $id ), 200 );
    }
}
