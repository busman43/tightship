<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Documents {

	public const STATUSES = array(
		'inbox',
		'draft',
		'review',
		'approved',
		'submitted',
		'awarded',
		'reporting',
		'closed',
	);

	public static function list( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => null,
			'space_id' => null,
			'search'   => null,
			'limit'    => 50,
			'offset'   => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$table  = $wpdb->prefix . 'tightship_documents';
		$where  = array( '1=1' );
		$params = array();

		if ( is_string( $args['status'] ) && $args['status'] !== '' ) {
			$where[]  = 'status=%s';
			$params[] = $args['status'];
		}

		if ( is_numeric( $args['space_id'] ) && (int) $args['space_id'] > 0 ) {
			$where[]  = 'space_id=%d';
			$params[] = (int) $args['space_id'];
		}

		if ( is_string( $args['search'] ) && $args['search'] !== '' ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$limit  = min( 200, max( 1, (int) $args['limit'] ) );
		$offset = max( 0, (int) $args['offset'] );

		$sql = "SELECT id, space_id, status, title, attachment_id, mime, size, meta, created_by, created_at, updated_at
				FROM {$table}
				WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC, id DESC
				LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? array_map( array( __CLASS__, 'normalize_document_row' ), $rows ) : array();
	}

	public static function get( int $document_id ): ?array {
		global $wpdb;

		$doc_table = $wpdb->prefix . 'tightship_documents';
		$rev_table = $wpdb->prefix . 'tightship_revisions';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, space_id, status, title, attachment_id, mime, size, meta, created_by, created_at, updated_at
				FROM {$doc_table} WHERE id=%d LIMIT 1",
				$document_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$revs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attachment_id, rev_no, notes, created_by, created_at
				FROM {$rev_table} WHERE document_id=%d ORDER BY rev_no DESC",
				$document_id
			),
			ARRAY_A
		);

		$doc              = self::normalize_document_row( $row );
		$doc['revisions'] = is_array( $revs ) ? array_map( array( __CLASS__, 'normalize_revision_row' ), $revs ) : array();
		$doc['attachment'] = self::attachment_info( (int) $doc['attachment_id'] );

		return $doc;
	}

	public static function create_from_upload( array $file, array $meta = array() ): array {
		self::require_upload_libs();

		$space_id = self::get_space_id_for_inbox();

		$attachment_id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return array(
				'ok'      => false,
				'error'   => $attachment_id->get_error_message(),
				'code'    => (string) $attachment_id->get_error_code(),
			);
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		$path = get_attached_file( $attachment_id );
		$size = ( is_string( $path ) && file_exists( $path ) ) ? (int) filesize( $path ) : 0;

		$title = isset( $meta['title'] ) && is_string( $meta['title'] ) && $meta['title'] !== ''
			? sanitize_text_field( $meta['title'] )
			: get_the_title( $attachment_id );

		$doc_meta = array(
			'original_name' => isset( $file['name'] ) ? sanitize_text_field( (string) $file['name'] ) : '',
		);

		$doc_meta = array_merge( $doc_meta, self::extract_project_code_meta( $doc_meta['original_name'] ) );
		$doc_meta = array_merge( $doc_meta, is_array( $meta ) ? $meta : array() );

		global $wpdb;
		$doc_table = $wpdb->prefix . 'tightship_documents';
		$rev_table = $wpdb->prefix . 'tightship_revisions';

		$now = current_time( 'mysql' );
		$wpdb->insert(
			$doc_table,
			array(
				'space_id'     => $space_id,
				'status'       => 'inbox',
				'title'        => $title,
				'attachment_id'=> (int) $attachment_id,
				'mime'         => $mime,
				'size'         => $size,
				'meta'         => wp_json_encode( $doc_meta ),
				'created_by'   => (int) get_current_user_id(),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		$doc_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$rev_table,
			array(
				'document_id'  => $doc_id,
				'attachment_id'=> (int) $attachment_id,
				'rev_no'       => 1,
				'notes'        => isset( $meta['notes'] ) ? sanitize_text_field( (string) $meta['notes'] ) : null,
				'created_by'   => (int) get_current_user_id(),
				'created_at'   => $now,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s' )
		);

		Audit::log( 'document_uploaded', 'document', $doc_id, array( 'attachment_id' => (int) $attachment_id ) );

		return array(
			'ok'       => true,
			'document' => self::get( $doc_id ),
		);
	}

	public static function add_revision( int $document_id, array $file, array $meta = array() ): array {
		self::require_upload_libs();

		global $wpdb;
		$doc_table = $wpdb->prefix . 'tightship_documents';
		$rev_table = $wpdb->prefix . 'tightship_revisions';

		$doc = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title FROM {$doc_table} WHERE id=%d LIMIT 1",
				$document_id
			),
			ARRAY_A
		);

		if ( ! is_array( $doc ) ) {
			return array( 'ok' => false, 'error' => 'Document not found.' );
		}

		$attachment_id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return array(
				'ok'    => false,
				'error' => $attachment_id->get_error_message(),
				'code'  => (string) $attachment_id->get_error_code(),
			);
		}

		$rev_no = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MAX(rev_no),0) FROM {$rev_table} WHERE document_id=%d",
				$document_id
			)
		);
		$rev_no++;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$rev_table,
			array(
				'document_id'   => $document_id,
				'attachment_id' => (int) $attachment_id,
				'rev_no'        => $rev_no,
				'notes'         => isset( $meta['notes'] ) ? sanitize_text_field( (string) $meta['notes'] ) : null,
				'created_by'    => (int) get_current_user_id(),
				'created_at'    => $now,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s' )
		);

		$wpdb->update(
			$doc_table,
			array(
				'attachment_id' => (int) $attachment_id,
				'updated_at'    => $now,
			),
			array( 'id' => $document_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		Audit::log( 'document_revision_added', 'document', $document_id, array( 'attachment_id' => (int) $attachment_id, 'rev_no' => $rev_no ) );

		return array(
			'ok'       => true,
			'document' => self::get( $document_id ),
		);
	}

	public static function update_status( int $document_id, string $status, ?int $space_id = null ): array {
		$status = sanitize_key( $status );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return array( 'ok' => false, 'error' => 'Invalid status.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tightship_documents';

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		$fmt = array( '%s', '%s' );

		if ( $space_id !== null && $space_id > 0 ) {
			$data['space_id'] = (int) $space_id;
			$fmt[]            = '%d';
		}

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $document_id ),
			$fmt,
			array( '%d' )
		);

		Audit::log( 'document_status_changed', 'document', $document_id, array( 'status' => $status, 'space_id' => $space_id ) );

		return array(
			'ok'       => true,
			'document' => self::get( $document_id ),
		);
	}

	public static function autoroute_inbox(): array {
		global $wpdb;

		$spaces_table = $wpdb->prefix . 'tightship_spaces';
		$docs_table   = $wpdb->prefix . 'tightship_documents';
		$rules_table  = $wpdb->prefix . 'tightship_rules';

		$inbox_id = self::get_space_id_for_inbox();

		$rules = $wpdb->get_results(
			"SELECT id, active, match_type, pattern, destination_space_id, tags FROM {$rules_table} WHERE active=1 ORDER BY id ASC",
			ARRAY_A
		);

		$docs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, meta FROM {$docs_table} WHERE space_id=%d AND status='inbox' ORDER BY id ASC",
				$inbox_id
			),
			ARRAY_A
		);

		$applied = 0;

		foreach ( $docs as $d ) {
			$meta = array();
			if ( isset( $d['meta'] ) && is_string( $d['meta'] ) && $d['meta'] !== '' ) {
				$tmp = json_decode( $d['meta'], true );
				if ( is_array( $tmp ) ) {
					$meta = $tmp;
				}
			}

			$original = isset( $meta['original_name'] ) && is_string( $meta['original_name'] ) ? $meta['original_name'] : $d['title'];
			$matched  = false;

			foreach ( $rules as $r ) {
				$pattern = (string) $r['pattern'];
				$type    = (string) $r['match_type'];

				if ( $type === 'regex' ) {
					$ok = @preg_match( $pattern, $original ) === 1;
				} else {
					$ok = stripos( $original, $pattern ) !== false;
				}

				if ( $ok ) {
					$wpdb->update(
						$docs_table,
						array(
							'space_id'    => (int) $r['destination_space_id'],
							'updated_at'  => current_time( 'mysql' ),
						),
						array( 'id' => (int) $d['id'] ),
						array( '%d', '%s' ),
						array( '%d' )
					);

					Audit::log(
						'document_routed',
						'document',
						(int) $d['id'],
						array(
							'rule_id'   => (int) $r['id'],
							'to_space'  => (int) $r['destination_space_id'],
							'filename'  => $original,
						)
					);

					$applied++;
					$matched = true;
					break;
				}
			}

			if ( ! $matched ) {
				continue;
			}
		}

		$spaces = $wpdb->get_results( "SELECT id, name, slug, description, color, sort FROM {$spaces_table} ORDER BY sort ASC, id ASC", ARRAY_A );

		return array(
			'ok'       => true,
			'applied'  => $applied,
			'spaces'   => is_array( $spaces ) ? $spaces : array(),
		);
	}

	private static function normalize_document_row( array $row ): array {
		$meta = array();
		if ( isset( $row['meta'] ) && is_string( $row['meta'] ) && $row['meta'] !== '' ) {
			$tmp = json_decode( $row['meta'], true );
			if ( is_array( $tmp ) ) {
				$meta = $tmp;
			}
		}

		$created_by = (int) ( $row['created_by'] ?? 0 );
		$user       = $created_by > 0 ? get_user_by( 'id', $created_by ) : null;

		return array(
			'id'            => (int) $row['id'],
			'space_id'      => (int) $row['space_id'],
			'status'        => (string) $row['status'],
			'title'         => (string) $row['title'],
			'attachment_id' => (int) $row['attachment_id'],
			'mime'          => (string) $row['mime'],
			'size'          => (int) $row['size'],
			'meta'          => $meta,
			'created_by'    => $created_by,
			'created_by_name' => $user instanceof \WP_User ? $user->display_name : '',
			'created_at'    => (string) $row['created_at'],
			'updated_at'    => (string) $row['updated_at'],
			'download_url'  => wp_get_attachment_url( (int) $row['attachment_id'] ),
		);
	}

	private static function normalize_revision_row( array $row ): array {
		$created_by = (int) ( $row['created_by'] ?? 0 );
		$user       = $created_by > 0 ? get_user_by( 'id', $created_by ) : null;

		return array(
			'id'            => (int) $row['id'],
			'attachment_id' => (int) $row['attachment_id'],
			'rev_no'        => (int) $row['rev_no'],
			'notes'         => isset( $row['notes'] ) ? (string) $row['notes'] : '',
			'created_by'    => $created_by,
			'created_by_name' => $user instanceof \WP_User ? $user->display_name : '',
			'created_at'    => (string) $row['created_at'],
			'download_url'  => wp_get_attachment_url( (int) $row['attachment_id'] ),
		);
	}

	private static function attachment_info( int $attachment_id ): array {
		$url  = wp_get_attachment_url( $attachment_id );
		$post = get_post( $attachment_id );

		return array(
			'id'    => $attachment_id,
			'url'   => is_string( $url ) ? $url : '',
			'title' => $post instanceof \WP_Post ? (string) $post->post_title : '',
		);
	}

	private static function extract_project_code_meta( string $filename ): array {
		// Very light heuristic: capture common EU program tokens and project codes.
		$meta = array();

		if ( preg_match( '/\b(IPA|CERV|H2020|HEU|Erasmus)\b/i', $filename, $m ) ) {
			$meta['program'] = strtoupper( $m[1] );
		}

		if ( preg_match( '/\b([A-Z]{2,6}-\d{2,6})\b/', $filename, $m ) ) {
			$meta['project_code'] = strtoupper( $m[1] );
		}

		return $meta;
	}

	private static function get_space_id_for_inbox(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'tightship_spaces';
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", 'inbox' ) );

		return $id > 0 ? $id : 1;
	}

	private static function require_upload_libs(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}
