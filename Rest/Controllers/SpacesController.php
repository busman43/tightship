<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\SpacesService;
use TightShip\Engine\Support\Json;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SpacesController extends BaseController {

    private SpacesService $spaces;

    public function __construct() {
        $this->rest_base = 'spaces';
        $this->spaces = new SpacesService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/spaces',
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
            '/spaces/(?P<id>\d+)',
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
        return new WP_REST_Response( array( 'items' => $this->spaces->list() ), 200 );
    }

    public function create( WP_REST_Request $request ) {
        $slug = sanitize_title( (string) $request->get_param( 'slug' ) );
        $name = sanitize_text_field( (string) $request->get_param( 'name' ) );
        $color = sanitize_key( (string) $request->get_param( 'color' ) );
        $sort = (int) $request->get_param( 'sort_order' );

        if ( $slug === '' || $name === '' ) {
            return Json::error( 'invalid_space', 'Space slug and name are required', 400 );
        }

        $id = $this->spaces->create(
            array(
                'slug' => $slug,
                'name' => $name,
                'color' => $color !== '' ? $color : 'slate',
                'sort_order' => $sort,
            ),
            get_current_user_id()
        );

        return new WP_REST_Response( array( 'id' => $id ), 201 );
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        $slug = sanitize_title( (string) $request->get_param( 'slug' ) );
        $name = sanitize_text_field( (string) $request->get_param( 'name' ) );
        $color = sanitize_key( (string) $request->get_param( 'color' ) );
        $sort = (int) $request->get_param( 'sort_order' );

        if ( $slug === '' || $name === '' ) {
            return Json::error( 'invalid_space', 'Space slug and name are required', 400 );
        }

        $ok = $this->spaces->update(
            $id,
            array(
                'slug' => $slug,
                'name' => $name,
                'color' => $color !== '' ? $color : 'slate',
                'sort_order' => $sort,
            ),
            get_current_user_id()
        );

        return $ok ? new WP_REST_Response( array( 'ok' => true ), 200 ) : Json::error( 'db_error', 'Failed to update space', 500 );
    }
}
