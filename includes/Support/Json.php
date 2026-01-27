<?php
declare(strict_types=1);

namespace TightShip\Engine\Support;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Json {

    public static function decode_array( ?string $json ): array {
        if ( ! is_string( $json ) || $json === '' ) {
            return array();
        }
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : array();
    }

    public static function encode( $value ): string {
        $json = wp_json_encode( $value );
        return is_string( $json ) ? $json : '[]';
    }

    public static function error( string $code, string $message, int $status = 400 ): WP_Error {
        return new WP_Error( $code, $message, array( 'status' => $status ) );
    }
}
