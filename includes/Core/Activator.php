<?php
declare(strict_types=1);

namespace TightShip\Engine\Core;

use TightShip\Engine\Db\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		Installer::install();

		self::ensure_roles();
		self::ensure_pages();

		flush_rewrite_rules();
	}

	private static function ensure_roles(): void {
		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			$admin->add_cap( Capabilities::CAP_ACCESS );
			$admin->add_cap( Capabilities::CAP_MANAGE );
		}

		$manager = get_role( 'tightship_manager' );
		if ( ! ( $manager instanceof \WP_Role ) ) {
			$manager = add_role(
				'tightship_manager',
				__( 'TightShip Manager', 'tightship-engine' ),
				array(
					'read'                     => true,
					Capabilities::CAP_ACCESS   => true,
					Capabilities::CAP_MANAGE   => true,
				)
			);
		}

		$member = get_role( 'tightship_member' );
		if ( ! ( $member instanceof \WP_Role ) ) {
			$member = add_role(
				'tightship_member',
				__( 'TightShip Member', 'tightship-engine' ),
				array(
					'read'                     => true,
					Capabilities::CAP_ACCESS   => true,
				)
			);
		}

		// Make sure existing roles keep access.
		$editor = get_role( 'editor' );
		if ( $editor instanceof \WP_Role ) {
			$editor->add_cap( Capabilities::CAP_ACCESS );
		}
	}

	private static function ensure_pages(): void {
		$login_id = self::upsert_page(
			__( 'TightShip Login', 'tightship-engine' ),
			'tightship-login',
			'[tightship_app mode="login"]'
		);

		self::upsert_page(
			__( 'TightShip', 'tightship-engine' ),
			'tightship',
			'[tightship_app mode="dashboard" login_page_id="' . (int) $login_id . '"]'
		);
	}

	private static function upsert_page( string $title, string $slug, string $content ): int {
		$existing = get_page_by_path( $slug );
		if ( $existing instanceof \WP_Post ) {
			if ( $existing->post_status !== 'publish' || $existing->post_content !== $content ) {
				wp_update_post(
					array(
						'ID'           => $existing->ID,
						'post_title'   => $title,
						'post_status'  => 'publish',
						'post_content' => $content,
					)
				);
			}
			return (int) $existing->ID;
		}

		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			),
			true
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}
}
