<?php
/**
 * Plugin Name: TightShip Engine
 * Description: TightShip Workspace engine: inbox-zero document control, EU projects workflow, calendar/reminders, routing rules, audit trail.
 * Version: 2.0.2
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: TightShip
 * Text Domain: tightship-engine
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TIGHTSHIP_ENGINE_VERSION', '2.0.2' );
define( 'TIGHTSHIP_ENGINE_FILE', __FILE__ );
define( 'TIGHTSHIP_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIGHTSHIP_ENGINE_URL', plugin_dir_url( __FILE__ ) );

require_once TIGHTSHIP_ENGINE_DIR . 'includes/Core/Autoloader.php';
\TightShip\Engine\Core\Autoloader::register();

register_activation_hook( __FILE__, array( \TightShip\Engine\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \TightShip\Engine\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'tightship-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		\TightShip\Engine\Core\Plugin::instance()->boot();
	},
	0
);
