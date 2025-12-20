<?php
declare(strict_types=1);

namespace TightShip\Engine\Db;

use TightShip\Engine\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'tightship_';

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}spaces (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			slug VARCHAR(190) NOT NULL,
			description TEXT NULL,
			color VARCHAR(32) NULL,
			sort INT NOT NULL DEFAULT 100,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			space_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'inbox',
			title VARCHAR(255) NOT NULL,
			attachment_id BIGINT UNSIGNED NOT NULL,
			mime VARCHAR(190) NOT NULL,
			size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			meta LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY space_status (space_id, status),
			KEY attachment_id (attachment_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}revisions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			document_id BIGINT UNSIGNED NOT NULL,
			attachment_id BIGINT UNSIGNED NOT NULL,
			rev_no INT NOT NULL DEFAULT 1,
			notes VARCHAR(255) NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY doc_rev (document_id, rev_no),
			KEY document_id (document_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			match_type VARCHAR(16) NOT NULL DEFAULT 'contains',
			pattern VARCHAR(255) NOT NULL,
			destination_space_id BIGINT UNSIGNED NOT NULL,
			tags TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY active (active),
			KEY dest (destination_space_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			type VARCHAR(24) NOT NULL DEFAULT 'reminder',
			start_at DATETIME NOT NULL,
			end_at DATETIME NULL,
			all_day TINYINT(1) NOT NULL DEFAULT 0,
			priority VARCHAR(10) NOT NULL DEFAULT 'medium',
			project_code VARCHAR(64) NULL,
			description TEXT NULL,
			reminders TEXT NULL,
			is_done TINYINT(1) NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY start_at (start_at),
			KEY type (type),
			KEY done (is_done)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}projects (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(64) NOT NULL,
			name VARCHAR(255) NOT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'active',
			start_at DATE NULL,
			end_at DATE NULL,
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code (code)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}proposals (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NULL,
			title VARCHAR(255) NOT NULL,
			stage VARCHAR(32) NOT NULL DEFAULT 'concept-note',
			donor VARCHAR(190) NULL,
			deadline DATE NULL,
			amount DECIMAL(18,2) NULL,
			currency VARCHAR(8) NOT NULL DEFAULT 'EUR',
			status VARCHAR(24) NOT NULL DEFAULT 'open',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY stage (stage),
			KEY deadline (deadline)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}contacts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			org VARCHAR(190) NULL,
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NULL,
			phone VARCHAR(64) NULL,
			role VARCHAR(128) NULL,
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY email (email)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}templates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			kind VARCHAR(64) NOT NULL DEFAULT 'doc',
			content LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}audit (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			action VARCHAR(190) NOT NULL,
			entity_type VARCHAR(64) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY action (action),
			KEY entity (entity_type, entity_id),
			KEY actor (actor_id),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::seed();
	}

	public static function get_login_url(): string {
		$page = get_page_by_path( 'tightship-login' );
		return $page instanceof \WP_Post ? get_permalink( $page ) : wp_login_url();
	}

	public static function get_app_url(): string {
		$page = get_page_by_path( 'tightship' );
		return $page instanceof \WP_Post ? get_permalink( $page ) : home_url( '/' );
	}

	private static function seed(): void {
		global $wpdb;

		$spaces_table = $wpdb->prefix . 'tightship_spaces';
		$rules_table  = $wpdb->prefix . 'tightship_rules';

		$now = current_time( 'mysql' );

		$spaces = array(
			array( 'Inbox', 'inbox', 'Upload landing zone (unclassified)', 'slate', 10 ),
			array( 'EU Projects', 'eu-projects', 'Project folders, work packages, annexes', 'indigo', 20 ),
			array( 'Budgets', 'budgets', 'Budgets, BoQs, financial attachments', 'emerald', 30 ),
			array( 'Contracts', 'contracts', 'Contracts, MoUs, NDAs, procurement', 'rose', 40 ),
			array( 'Partners', 'partners', 'Partner info, letters, contacts', 'amber', 50 ),
			array( 'Reports', 'reports', 'Interim/final reports, evidence packs', 'cyan', 60 ),
		);

		foreach ( $spaces as $s ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$spaces_table} WHERE slug=%s LIMIT 1",
					$s[1]
				)
			);

			if ( $exists ) {
				continue;
			}

			$wpdb->insert(
				$spaces_table,
				array(
					'name'        => $s[0],
					'slug'        => $s[1],
					'description' => $s[2],
					'color'       => $s[3],
					'sort'        => (int) $s[4],
					'created_at'  => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s' )
			);
		}

		$space_ids = $wpdb->get_results( "SELECT id, slug FROM {$spaces_table}", OBJECT_K );
		$slug_to_id = array();
		if ( is_array( $space_ids ) ) {
			foreach ( $space_ids as $row ) {
				$slug_to_id[ $row->slug ] = (int) $row->id;
			}
		}

		$defaults = array(
			array( 'Budget sheets', 'contains', 'budget', 'budgets', array( 'finance', 'xlsx' ) ),
			array( 'Contracts', 'contains', 'contract', 'contracts', array( 'legal' ) ),
			array( 'Partner letters', 'contains', 'partner', 'partners', array( 'outreach' ) ),
			array( 'Reporting', 'contains', 'report', 'reports', array( 'evidence' ) ),
			array( 'EU project codes', 'regex', '/\b(IPA|CERV|H2020|HEU|Erasmus)\b/i', 'eu-projects', array( 'project' ) ),
		);

		foreach ( $defaults as $r ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$rules_table} WHERE name=%s LIMIT 1",
					$r[0]
				)
			);

			if ( $exists ) {
				continue;
			}

			$dest_id = $slug_to_id[ $r[3] ] ?? 0;
			if ( $dest_id <= 0 ) {
				continue;
			}

			$wpdb->insert(
				$rules_table,
				array(
					'name'                 => $r[0],
					'active'               => 1,
					'match_type'           => $r[1],
					'pattern'              => $r[2],
					'destination_space_id' => $dest_id,
					'tags'                 => wp_json_encode( $r[4] ),
					'created_by'           => (int) get_current_user_id(),
					'created_at'           => $now,
					'updated_at'           => $now,
				),
				array( '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}
}
