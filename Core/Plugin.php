<?php
declare(strict_types=1);

namespace TightShip\Engine\Core;

use TightShip\Engine\Frontend\Shortcodes;
use TightShip\Engine\Rest\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( Router::class, 'register' ) );
	}

	public function init(): void {
		Shortcodes::register();
	}
}
