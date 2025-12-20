<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\EventsService;
use TightShip\Engine\Support\Json;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EventsController extends BaseController {

    private EventsService $events;

    public function __construct() {
        $this->rest_base = 'events';
        $this->events = new EventsService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/events',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'list' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                    'args'                => array(
                        'start' => array( 'type' => 'string', 'required' => false ),
                        'end'   => array( 'type' => 'string', 'required' => false ),
                    ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'create' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/events/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'PUT,PATCH',
                    'callback'            => array( $this, 'update' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/events/(?P<id>\d+)/complete',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'complete' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );
    }

    public function list( WP_REST_Request $request ): WP_REST_Response {
        $start = $request->get_param( 'start' );
        $end   = $request->get_param( 'end' );

        $items = $this->events->list(
            $start ? sanitize_text_field( (string) $start ) : null,
            $end ? sanitize_text_field( (string) $end ) : null
        );

        return new WP_REST_Response( array( 'items' => $items ), 200 );
    }

    public function create( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $id = $this->events->create( array_map( 'wp_unslash', $data ), get_current_user_id() );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $ev = $this->events->get( (int) $id );
        return new WP_REST_Response( $ev, 201 );
    }

    public function update( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();
        if ( ! is_array( $data ) ) {
            $data = $request->get_params();
        }

        $ok = $this->events->update( $id, array_map( 'wp_unslash', $data ), get_current_user_id() );
        if ( is_wp_error( $ok ) ) {
            return $ok;
        }

        $ev = $this->events->get( $id );
        return new WP_REST_Response( $ev, 200 );
    }

    public function complete( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $completed = (bool) $request->get_param( 'completed' );

        $ok = $this->events->toggle_complete( $id, $completed, get_current_user_id() );
        if ( is_wp_error( $ok ) ) {
            return $ok;
        }

        $ev = $this->events->get( $id );
        return new WP_REST_Response( $ev, 200 );
    }
}
