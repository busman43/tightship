<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\RulesService;
use TightShip\Engine\Support\Json;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RulesController extends BaseController {

    private RulesService $rules;

    public function __construct() {
        $this->rest_base = 'rules';
        $this->rules = new RulesService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/rules',
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
            '/rules/(?P<id>\d+)',
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
        return new WP_REST_Response( array( 'items' => $this->rules->list() ), 200 );
    }

    public function create( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $id = $this->rules->create( array_map( 'wp_unslash', $data ) );
        return new WP_REST_Response( array( 'id' => $id ), 201 );
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $ok = $this->rules->update( $id, array_map( 'wp_unslash', $data ) );
        return $ok ? new WP_REST_Response( array( 'ok' => true ), 200 ) : Json::error( 'db_error', 'Failed to update rule', 500 );
    }
}
