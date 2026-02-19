<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Audit {

	public static function log( string $action, string $entity_type, int $entity_id = 0, array $payload = array(), ?int $actor_id = null ): void {
		global $wpdb;

		$table   = $wpdb->prefix . 'tightship_audit';
		$actor   = $actor_id !== null ? (int) $actor_id : (int) get_current_user_id();
		$created = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'actor_id'    => $actor,
				'payload'     => empty( $payload ) ? null : wp_json_encode( $payload ),
				'created_at'  => $created,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}
}
