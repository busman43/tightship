<?php
declare(strict_types=1);

namespace TightShip\Engine\Frontend;

use TightShip\Engine\Core\Capabilities;
use TightShip\Engine\Db\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	private static bool $did_enqueue = false;

	public static function register(): void {
		add_shortcode( 'tightship_app', array( __CLASS__, 'render_app' ) );
		add_shortcode( 'tightship_login', array( __CLASS__, 'render_login' ) );
		add_shortcode( 'tightship_dashboard', array( __CLASS__, 'render_dashboard' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ), 20 );
	}

	public static function render_login( array $atts = array(), string $content = '' ): string {
		$atts['mode'] = 'login';
		return self::render_app( $atts, $content );
	}

	public static function render_dashboard( array $atts = array(), string $content = '' ): string {
		$atts['mode'] = 'dashboard';
		return self::render_app( $atts, $content );
	}

	public static function render_app( array $atts = array(), string $content = '' ): string {
		$atts = shortcode_atts(
			array(
				'mode'          => 'dashboard',
				'login_page_id' => '',
			),
			$atts,
			'tightship_app'
		);

		$mode = sanitize_key( (string) $atts['mode'] );
		if ( $mode !== 'login' ) {
			$mode = 'dashboard';
		}

		$login_url = Installer::get_login_url();
		if ( is_numeric( $atts['login_page_id'] ) && (int) $atts['login_page_id'] > 0 ) {
			$tmp = get_permalink( (int) $atts['login_page_id'] );
			if ( is_string( $tmp ) && $tmp !== '' ) {
				$login_url = $tmp;
			}
		}

		$app_url = Installer::get_app_url();

		if ( $mode === 'dashboard' ) {
			if ( ! is_user_logged_in() ) {
				$login = esc_url( add_query_arg( 'redirect_to', rawurlencode( (string) $app_url ), $login_url ) );
				return '<div class="ts-card"><h2>TightShip</h2><p>You must log in to access the workspace.</p><p><a class="ts-btn ts-btn-primary" href="' . $login . '">Log in</a></p></div>';
			}

			if ( ! self::current_user_can_access() ) {
				return '<div class="ts-card"><h2>TightShip</h2><p>Access denied.</p></div>';
			}
		}

		if ( $mode === 'login' && is_user_logged_in() ) {
			wp_safe_redirect( $app_url );
			exit;
		}

		self::$did_enqueue = true;

		$mount_id = 'tightship-app';
		$placeholder = '<div class="ts-loading"><div class="ts-loading__spinner" aria-hidden="true"></div><div class="ts-loading__text">Loading TightShip…</div></div>';

		$html  = '<div id="' . esc_attr( $mount_id ) . '" class="tightship-app" data-mode="' . esc_attr( $mode ) . '">' . $placeholder . '</div>';
		$html .= '<noscript><div class="ts-card"><h2>TightShip</h2><p>JavaScript is required for the TightShip app.</p></div></noscript>';

		$html = '<div class="tightship-wrap">' . $html . '</div>';

		$config = array(
			'restUrl'  => esc_url_raw( rest_url( 'tightship/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'mode'     => $mode,
			'appUrl'   => esc_url_raw( $app_url ),
			'loginUrl' => esc_url_raw( $login_url ),
			'homeUrl'  => esc_url_raw( home_url( '/' ) ),
		);

		$html .= '<script>window.TightShipApp=window.TightShipApp||{};window.TightShipApp.config=' . wp_json_encode( $config ) . ';</script>';

		return $html;
	}

	public static function maybe_enqueue_assets(): void {
		if ( ! self::$did_enqueue ) {
			return;
		}

		$css_file = TIGHTSHIP_ENGINE_DIR . 'assets/app/tightship-app.css';
		$js_file  = TIGHTSHIP_ENGINE_DIR . 'assets/app/tightship-app.js';

		$css = TIGHTSHIP_ENGINE_URL . 'assets/app/tightship-app.css';
		$js  = TIGHTSHIP_ENGINE_URL . 'assets/app/tightship-app.js';

		$ver_css = file_exists( $css_file ) ? (string) filemtime( $css_file ) : TIGHTSHIP_ENGINE_VERSION;
		$ver_js  = file_exists( $js_file ) ? (string) filemtime( $js_file ) : TIGHTSHIP_ENGINE_VERSION;

		wp_enqueue_style( 'tightship-app', $css, array(), $ver_css );
		wp_enqueue_script( 'tightship-app', $js, array(), $ver_js, true );
	}

	private static function current_user_can_access(): bool {
		$required = apply_filters( 'tightship_frontend_required_cap', Capabilities::CAP_ACCESS );
		$ok       = current_user_can( (string) $required ) || current_user_can( Capabilities::CAP_MANAGE );

		$ok = (bool) apply_filters( 'tightship_frontend_can_access', $ok, get_current_user_id() );
		return $ok;
	}
}
