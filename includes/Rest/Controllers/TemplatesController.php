<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\TemplatesService;
use TightShip\Engine\Support\Json;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TemplatesController extends BaseController {

    private TemplatesService $templates;

    public function __construct() {
        $this->rest_base = 'templates';
        $this->templates = new TemplatesService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/templates',
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
            '/templates/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'get' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
                array(
                    'methods'             => 'PUT,PATCH',
                    'callback'            => array( $this, 'update' ),
                    'permission_callback' => array( $this, 'permission_manage' ),
                ),
            )
        );
    }

    public function list( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( array( 'items' => $this->templates->list() ), 200 );
    }

    public function get( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $tpl = $this->templates->get( $id );
        if ( ! is_array( $tpl ) ) {
            return Json::error( 'not_found', 'Template not found', 404 );
        }
        return new WP_REST_Response( $tpl, 200 );
    }

    public function create( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $id = $this->templates->create( array_map( 'wp_unslash', $data ), get_current_user_id() );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        return new WP_REST_Response( $this->templates->get( (int) $id ), 201 );
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $ok = $this->templates->update( $id, array_map( 'wp_unslash', $data ), get_current_user_id() );
        if ( is_wp_error( $ok ) ) {
            return $ok;
        }

        return new WP_REST_Response( $this->templates->get( $id ), 200 );
    }
}
