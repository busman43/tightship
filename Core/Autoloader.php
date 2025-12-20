<?php
declare(strict_types=1);

namespace TightShip\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {

	private const PREFIX = 'TightShip\\Engine\\';

	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( string $class ): void {
		if ( strncmp( $class, self::PREFIX, strlen( self::PREFIX ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', '/', $relative );
		$file     = trailingslashit( TIGHTSHIP_ENGINE_DIR ) . 'includes/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
