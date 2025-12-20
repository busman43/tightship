<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\DocumentsService;
use TightShip\Engine\Support\Json;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DocumentsController extends BaseController {

    private DocumentsService $docs;

    public function __construct() {
        $this->rest_base = 'documents';
        $this->docs = new DocumentsService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/documents',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'list' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                    'args'                => array(
                        'status' => array( 'type' => 'string', 'required' => false ),
                        'q'      => array( 'type' => 'string', 'required' => false ),
                    ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/documents/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'get' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/documents/upload',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'upload' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/documents/(?P<id>\d+)/revision',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'revision' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );

        register_rest_route(
            Routes::NAMESPACE,
            '/documents/(?P<id>\d+)/status',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'status' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                    'args'                => array(
                        'status' => array( 'type' => 'string', 'required' => true ),
                    ),
                ),
            )
        );
    }

    public function list( WP_REST_Request $request ): WP_REST_Response {
        $items = $this->docs->list(
            array(
                'status' => $request->get_param( 'status' ),
                'q'      => $request->get_param( 'q' ),
            )
        );

        return new WP_REST_Response(
            array(
                'items' => $items,
            ),
            200
        );
    }

    public function get( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $doc = $this->docs->get( $id );

        if ( ! is_array( $doc ) ) {
            return Json::error( 'not_found', 'Document not found', 404 );
        }

        return new WP_REST_Response( $doc, 200 );
    }

    public function upload( WP_REST_Request $request ) {
        $files = $request->get_file_params();
        $file = $files['file'] ?? null;

        if ( ! is_array( $file ) ) {
            return Json::error( 'missing_file', 'Upload requires multipart file field "file"', 400 );
        }

        $note = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
        $out = $this->docs->upload_new( $file, array( 'note' => $note ), get_current_user_id() );

        return $out;
    }

    public function revision( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $files = $request->get_file_params();
        $file = $files['file'] ?? null;

        if ( ! is_array( $file ) ) {
            return Json::error( 'missing_file', 'Upload requires multipart file field "file"', 400 );
        }

        $note = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
        if ( $note === '' ) {
            $note = __( 'New revision', 'tightship-engine' );
        }

        return $this->docs->add_revision( $id, $file, $note, get_current_user_id() );
    }

    
    public function autoroute( WP_REST_Request $request ) {
        $result = $this->docs->autoroute_inbox( get_current_user_id() );
        return new WP_REST_Response( $result, 200 );
    }

public function status( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $status = (string) $request->get_param( 'status' );

        return $this->docs->update_status( $id, $status, get_current_user_id() );
    }
}
        register_rest_route(
            Routes::NAMESPACE,
            '/documents/autoroute',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'autoroute' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );


