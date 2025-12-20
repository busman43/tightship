<?php
declare(strict_types=1);

namespace TightShip\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Logger {

	public static function log( string $message, array $context = array() ): void {
		$prefix = '[TightShip] ';
		if ( ! empty( $context ) ) {
			$message .= ' ' . wp_json_encode( $context );
		}
		error_log( $prefix . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
