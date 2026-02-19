<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest;

use TightShip\Engine\Rest\Controllers\AuthController;
use TightShip\Engine\Rest\Controllers\DocumentsController;
use TightShip\Engine\Rest\Controllers\EventsController;
use TightShip\Engine\Rest\Controllers\SpacesController;
use TightShip\Engine\Rest\Controllers\RulesController;
use TightShip\Engine\Rest\Controllers\ProjectsController;
use TightShip\Engine\Rest\Controllers\ProposalsController;
use TightShip\Engine\Rest\Controllers\ContactsController;
use TightShip\Engine\Rest\Controllers\TemplatesController;
use TightShip\Engine\Rest\Controllers\ReportsController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Routes {

    public const NAMESPACE = 'tightship/v1';

    public function register(): void {
        add_action(
            'rest_api_init',
            function (): void {
                ( new AuthController() )->register_routes();
                ( new DocumentsController() )->register_routes();
                ( new SpacesController() )->register_routes();
                ( new RulesController() )->register_routes();
                ( new EventsController() )->register_routes();
                ( new ProjectsController() )->register_routes();
                ( new ProposalsController() )->register_routes();
                ( new ContactsController() )->register_routes();
                ( new TemplatesController() )->register_routes();
                ( new ReportsController() )->register_routes();
            }
        );
    }
}
