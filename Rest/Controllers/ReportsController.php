<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest\Controllers;

use TightShip\Engine\Rest\BaseController;
use TightShip\Engine\Rest\Routes;
use TightShip\Engine\Services\DocumentsService;
use TightShip\Engine\Services\ProjectsService;
use TightShip\Engine\Services\ProposalsService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ReportsController extends BaseController {

    private DocumentsService $docs;
    private ProjectsService $projects;
    private ProposalsService $proposals;

    public function __construct() {
        $this->rest_base = 'reports';
        $this->docs = new DocumentsService();
        $this->projects = new ProjectsService();
        $this->proposals = new ProposalsService();
    }

    public function register_routes(): void {
        register_rest_route(
            Routes::NAMESPACE,
            '/reports/summary',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'summary' ),
                    'permission_callback' => array( $this, 'permission_access' ),
                ),
            )
        );
    }

    public function summary( WP_REST_Request $request ): WP_REST_Response {
        $summary = $this->docs->stats_summary();

        return new WP_REST_Response(
            array(
                'summary'   => $summary,
                'projects'  => $this->projects->list(),
                'proposals' => $this->proposals->list(),
            ),
            200
        );
    }
}
