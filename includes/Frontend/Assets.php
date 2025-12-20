<?php
declare(strict_types=1);

namespace TightShip\Engine\Frontend;

use TightShip\Engine\Rest\Routes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Assets {

    private static bool $registered = false;

    public function register(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        $js  = TIGHTSHIP_ENGINE_DIR . 'assets/app/tightship-app.js';
        $css = TIGHTSHIP_ENGINE_DIR . 'assets/app/tightship-app.css';

        $js_url  = TIGHTSHIP_ENGINE_URL . 'assets/app/tightship-app.js';
        $css_url = TIGHTSHIP_ENGINE_URL . 'assets/app/tightship-app.css';

        wp_register_script(
            'tightship-app',
            $js_url,
            array(),
            ( file_exists( $js ) ? (string) filemtime( $js ) : TIGHTSHIP_ENGINE_VERSION ),
            true
        );

        wp_register_style(
            'tightship-app',
            $css_url,
            array(),
            ( file_exists( $css ) ? (string) filemtime( $css ) : TIGHTSHIP_ENGINE_VERSION )
        );
    }

    public static function enqueue( string $mode ): void {
        add_action(
            'wp_enqueue_scripts',
            static function () use ( $mode ): void {
                $rest_base = rest_url( Routes::NAMESPACE );
                $cfg = array(
                    'mode'     => $mode,
                    'restUrl'  => esc_url_raw( trailingslashit( $rest_base ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                    'siteUrl'  => esc_url_raw( home_url( '/' ) ),
                    'loginUrl' => esc_url_raw( wp_login_url( get_permalink() ) ),
                );

                wp_enqueue_style( 'tightship-app' );
                wp_enqueue_script( 'tightship-app' );
                wp_add_inline_script( 'tightship-app', 'window.TightShipApp = ' . wp_json_encode( $cfg ) . ';', 'before' );
            },
            20
        );
    }
}
