<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest;

use TightShip\Engine\Core\Capabilities;
use TightShip\Engine\Rest\Controllers\Auth_Controller;
use TightShip\Engine\Rest\Controllers\Documents_Controller;
use TightShip\Engine\Rest\Controllers\Spaces_Controller;
use TightShip\Engine\Rest\Controllers\Rules_Controller;
use TightShip\Engine\Rest\Controllers\Events_Controller;
use TightShip\Engine\Rest\Controllers\Projects_Controller;
use TightShip\Engine\Rest\Controllers\Proposals_Controller;
use TightShip\Engine\Rest\Controllers\Contacts_Controller;
use TightShip\Engine\Rest\Controllers\Templates_Controller;
use TightShip\Engine\Rest\Controllers\Reports_Controller;
use TightShip\Engine\Rest\Controllers\Audit_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Router {

	public const NS = 'tightship/v1';

	public static function register(): void {
		$auth      = new Auth_Controller();
		$docs      = new Documents_Controller();
		$spaces    = new Spaces_Controller();
		$rules     = new Rules_Controller();
		$events    = new Events_Controller();
		$projects  = new Projects_Controller();
		$proposals = new Proposals_Controller();
		$contacts  = new Contacts_Controller();
		$templates = new Templates_Controller();
		$reports   = new Reports_Controller();
		$audit     = new Audit_Controller();

		$auth->register_routes();
		$docs->register_routes();
		$spaces->register_routes();
		$rules->register_routes();
		$events->register_routes();
		$projects->register_routes();
		$proposals->register_routes();
		$contacts->register_routes();
		$templates->register_routes();
		$reports->register_routes();
		$audit->register_routes();
	}

	public static function can_access(): bool {
		return is_user_logged_in() && ( current_user_can( Capabilities::CAP_ACCESS ) || current_user_can( Capabilities::CAP_MANAGE ) );
	}

	public static function can_manage(): bool {
		return is_user_logged_in() && current_user_can( Capabilities::CAP_MANAGE );
	}
}
