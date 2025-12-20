<?php
declare(strict_types=1);

namespace TightShip\Engine\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Pages {

    public static function ensure_pages(): void {
        self::ensure_page( 'tightship', 'TightShip', '[tightship_app mode="dashboard"]' );
        self::ensure_page( 'tightship-login', 'TightShip Login', '[tightship_app mode="login"]' );
    }

    private static function ensure_page( string $slug, string $title, string $content ): void {
        $existing = get_page_by_path( $slug );
        if ( $existing instanceof \WP_Post ) {
            // Ensure content is correct (don't override if user customized heavily).
            if ( strpos( (string) $existing->post_content, '[tightship_app' ) === false ) {
                wp_update_post(
                    array(
                        'ID'           => $existing->ID,
                        'post_content' => $content,
                    )
                );
            }
            return;
        }

        wp_insert_post(
            array(
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $content,
            )
        );
    }
}
