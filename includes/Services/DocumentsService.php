<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DocumentsService {

    private AuditService $audit;
    private RulesService $rules;

    public function __construct() {
        $this->audit = new AuditService();
        $this->rules = new RulesService();
    }

    public function list( array $args = array() ): array {
        global $wpdb;

        $status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
        $q      = isset( $args['q'] ) ? sanitize_text_field( (string) $args['q'] ) : '';

        $where = array();
        $params = array();

        if ( $status !== '' ) {
            $where[] = 'd.status = %s';
            $params[] = $status;
        }

        if ( $q !== '' ) {
            $where[] = '(d.title LIKE %s OR d.project_code LIKE %s OR d.doc_type LIKE %s)';
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT d.*, s.name AS space_name, s.color AS space_color
                FROM " . Tables::documents() . " d
                LEFT JOIN " . Tables::spaces() . " s ON d.space_id = s.id";

        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $sql .= ' ORDER BY d.updated_at DESC, d.id DESC LIMIT 200';

        $prepared = ! empty( $params ) ? $wpdb->prepare( $sql, $params ) : $sql;
        $rows = $wpdb->get_results( $prepared, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        return array_map( array( $this, 'hydrate_document' ), $rows );
    }

    public function get( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT d.*, s.name AS space_name, s.color AS space_color
                 FROM " . Tables::documents() . " d
                 LEFT JOIN " . Tables::spaces() . " s ON d.space_id = s.id
                 WHERE d.id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return null;
        }

        $doc = $this->hydrate_document( $row );
        $doc['revisions'] = $this->list_revisions( $id );

        return $doc;
    }

    public function list_revisions( int $document_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, u.display_name AS author_name
                 FROM " . Tables::revisions() . " r
                 LEFT JOIN {$wpdb->users} u ON r.created_by = u.ID
                 WHERE r.document_id = %d
                 ORDER BY r.revision_number DESC",
                $document_id
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $out = array();
        foreach ( $rows as $r ) {
            $out[] = array(
                'id'      => (int) $r['id'],
                'number'  => (int) $r['revision_number'],
                'date'    => (string) $r['created_at'],
                'author'  => (string) ( $r['author_name'] ?? '' ),
                'notes'   => (string) ( $r['note'] ?? '' ),
                'attachment_id' => $r['attachment_id'] !== null ? (int) $r['attachment_id'] : null,
                'url'     => $r['attachment_id'] ? (string) wp_get_attachment_url( (int) $r['attachment_id'] ) : '',
                'fileSize'=> $r['attachment_id'] ? size_format( (int) filesize( get_attached_file( (int) $r['attachment_id'] ) ) ) : '',
              );
        }
        return $out;
    }

    public function upload_new( array $file, array $meta = array(), int $actor_id = 0 ): array|WP_Error {
        $actor_id = $actor_id ?: get_current_user_id();

        $attachment_id = $this->handle_upload_to_media( $file );
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        $title = sanitize_text_field( pathinfo( (string) get_post_field( 'post_title', $attachment_id ), PATHINFO_BASENAME ) );
        if ( $title === '' ) {
            $title = sanitize_text_field( (string) ( $file['name'] ?? 'Untitled' ) );
        }

        $doc_type = $this->detect_doc_type( (string) ( $file['name'] ?? '' ) );
        $project_code = $this->extract_project_code( $title );

        $doc = array(
            'title'         => $title,
            'doc_type'      => $doc_type,
            'status'        => 'inbox',
            'space_id'      => null,
            'project_code'  => $project_code,
            'tags'          => array(),
            'attachment_id' => (int) $attachment_id,
        );

        // Apply routing rules.
        $rules = $this->rules->list();
        $doc = $this->rules->apply_rules( $doc, $rules );

        $now = current_time( 'mysql' );

        global $wpdb;
        $wpdb->insert(
            Tables::documents(),
            array(
                'title'            => $doc['title'],
                'doc_type'         => $doc['doc_type'],
                'status'           => $doc['status'],
                'space_id'         => $doc['space_id'],
                'project_code'     => $doc['project_code'],
                'tags'             => Json::encode( $doc['tags'] ),
                'attachment_id'    => $doc['attachment_id'],
                'current_revision' => 1,
                'created_by'       => $actor_id,
                'updated_by'       => $actor_id,
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
        );

        $doc_id = (int) $wpdb->insert_id;

        $note = isset( $meta['note'] ) ? sanitize_textarea_field( (string) $meta['note'] ) : __( 'Initial upload', 'tightship-engine' );

        $wpdb->insert(
            Tables::revisions(),
            array(
                'document_id'     => $doc_id,
                'revision_number' => 1,
                'attachment_id'   => (int) $attachment_id,
                'note'            => $note,
                'created_by'      => $actor_id,
                'created_at'      => $now,
            ),
            array( '%d', '%d', '%d', '%s', '%d', '%s' )
        );

        $this->audit->log( 'document.upload', 'document', $doc_id, array( 'title' => $doc['title'] ), $actor_id );

        $out = $this->get( $doc_id );
        return is_array( $out ) ? $out : Json::error( 'not_found', 'Document not found after upload', 500 );
    }

    public function add_revision( int $document_id, array $file, string $note, int $actor_id = 0 ): array|WP_Error {
        global $wpdb;

        $actor_id = $actor_id ?: get_current_user_id();
        $doc = $this->get( $document_id );
        if ( ! is_array( $doc ) ) {
            return Json::error( 'not_found', 'Document not found', 404 );
        }

        $attachment_id = $this->handle_upload_to_media( $file );
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        $next = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COALESCE(MAX(revision_number), 0) + 1 FROM " . Tables::revisions() . " WHERE document_id = %d", $document_id )
        );

        $now = current_time( 'mysql' );

        $wpdb->insert(
            Tables::revisions(),
            array(
                'document_id'     => $document_id,
                'revision_number' => $next,
                'attachment_id'   => (int) $attachment_id,
                'note'            => sanitize_textarea_field( $note ),
                'created_by'      => $actor_id,
                'created_at'      => $now,
            ),
            array( '%d', '%d', '%d', '%s', '%d', '%s' )
        );

        $wpdb->update(
            Tables::documents(),
            array(
                'attachment_id'    => (int) $attachment_id,
                'current_revision' => $next,
                'updated_by'       => $actor_id,
                'updated_at'       => $now,
            ),
            array( 'id' => $document_id ),
            array( '%d', '%d', '%d', '%s' ),
            array( '%d' )
        );

        $this->audit->log( 'document.revise', 'document', $document_id, array( 'revision' => $next ), $actor_id );

        $out = $this->get( $document_id );
        return is_array( $out ) ? $out : Json::error( 'not_found', 'Document not found after revision', 500 );
    }

    public function update_status( int $id, string $status, int $actor_id = 0 ): array|WP_Error {
        global $wpdb;

        $actor_id = $actor_id ?: get_current_user_id();
        $allowed = array( 'inbox', 'draft', 'review', 'approved', 'submitted', 'awarded', 'reporting', 'closed' );
        $status = sanitize_key( $status );

        if ( ! in_array( $status, $allowed, true ) ) {
            return Json::error( 'invalid_status', 'Invalid status', 400 );
        }

        $updated = $wpdb->update(
            Tables::documents(),
            array(
                'status'     => $status,
                'updated_by' => $actor_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update document', 500 );
        }

        $this->audit->log( 'document.status', 'document', $id, array( 'status' => $status ), $actor_id );

        $out = $this->get( $id );
        return is_array( $out ) ? $out : Json::error( 'not_found', 'Document not found', 404 );
    }

    public function autoroute_inbox( int $actor_id = 0 ): array|\WP_Error {
        global $wpdb;

        $actor_id = $actor_id ?: get_current_user_id();
        $rules = $this->rules->list();

        if ( empty( $rules ) ) {
            return array( 'updated' => 0, 'skipped' => 0 );
        }

        $rows = $wpdb->get_results(
            "SELECT * FROM " . Tables::documents() . " WHERE status = 'inbox' ORDER BY updated_at DESC LIMIT 500",
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return array( 'updated' => 0, 'skipped' => 0 );
        }

        $updated_count = 0;
        $skipped_count = 0;

        foreach ( $rows as $row ) {
            $doc = array(
                'title'         => (string) $row['title'],
                'doc_type'      => (string) ( $row['doc_type'] ?? '' ),
                'status'        => (string) $row['status'],
                'space_id'      => $row['space_id'] !== null ? (int) $row['space_id'] : null,
                'project_code'  => (string) ( $row['project_code'] ?? '' ),
                'tags'          => Json::decode_array( (string) ( $row['tags'] ?? '' ) ),
                'attachment_id' => $row['attachment_id'] !== null ? (int) $row['attachment_id'] : null,
            );

            $applied = $this->rules->apply_rules( $doc, $rules );

            $changed = (
                (string) $applied['doc_type'] !== (string) $doc['doc_type'] ||
                (string) $applied['status'] !== (string) $doc['status'] ||
                (int) ( $applied['space_id'] ?? 0 ) !== (int) ( $doc['space_id'] ?? 0 ) ||
                Json::encode( $applied['tags'] ?? array() ) !== Json::encode( $doc['tags'] ?? array() )
            );

            if ( ! $changed ) {
                $skipped_count++;
                continue;
            }

            $wpdb->update(
                Tables::documents(),
                array(
                    'doc_type'    => (string) $applied['doc_type'],
                    'status'      => (string) $applied['status'],
                    'space_id'    => $applied['space_id'] !== null ? (int) $applied['space_id'] : null,
                    'tags'        => Json::encode( $applied['tags'] ?? array() ),
                    'updated_by'  => $actor_id,
                    'updated_at'  => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $row['id'] ),
                array( '%s', '%s', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            $updated_count++;
            $this->audit->log( 'document.autoroute', 'document', (int) $row['id'], array( 'status' => (string) $applied['status'], 'space_id' => $applied['space_id'] ), $actor_id );
        }

        return array(
            'updated' => $updated_count,
            'skipped' => $skipped_count,
        );
    }

    public function stats_summary(): array {
        global $wpdb;

        $docs = Tables::documents();
        $events = Tables::events();
        $audit = Tables::audit();

        $inbox = (int) $wpdb->get_var( "SELECT COUNT(1) FROM $docs WHERE status = 'inbox'" );
        $review = (int) $wpdb->get_var( "SELECT COUNT(1) FROM $docs WHERE status = 'review'" );
        $approved = (int) $wpdb->get_var( "SELECT COUNT(1) FROM $docs WHERE status = 'approved'" );

        $now_ts = (int) current_time( 'timestamp' );
        $start_dt = wp_date( 'Y-m-d H:i:s', $now_ts );
        $end_dt   = wp_date( 'Y-m-d H:i:s', $now_ts + ( 7 * DAY_IN_SECONDS ) );

        $due_week = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM $events WHERE completed = 0 AND starts_at >= %s AND starts_at < %s",
                $start_dt,
                $end_dt
            )
        );

        $recent = $wpdb->get_results(
            "SELECT a.*, u.display_name AS actor_name
             FROM $audit a
             LEFT JOIN {$wpdb->users} u ON a.actor_id = u.ID
             ORDER BY a.created_at DESC LIMIT 20",
            ARRAY_A
        );

        $activity = array();
        if ( is_array( $recent ) ) {
            foreach ( $recent as $r ) {
                $activity[] = array(
                    'id'     => (int) $r['id'],
                    'ts'     => (string) $r['created_at'],
                    'action' => (string) $r['action'],
                    'entity' => (string) $r['entity'],
                    'entity_id' => $r['entity_id'] !== null ? (int) $r['entity_id'] : null,
                    'actor'  => (string) ( $r['actor_name'] ?? '' ),
                    'meta'   => Json::decode_array( (string) ( $r['meta_json'] ?? '' ) ),
                );
            }
        }

        return array(
            'inbox'        => $inbox,
            'review'       => $review,
            'approved'     => $approved,
            'due_this_week'=> $due_week,
            'activity'     => $activity,
        );
    }

    private function hydrate_document( array $row ): array {
        $attachment_id = $row['attachment_id'] !== null ? (int) $row['attachment_id'] : null;

        $tags = Json::decode_array( (string) ( $row['tags'] ?? '' ) );
        $tags = array_values( array_filter( array_map( 'sanitize_text_field', is_array( $tags ) ? $tags : array() ) ) );

        $file_url = $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '';
        $file_path = $attachment_id ? get_attached_file( $attachment_id ) : '';
        $size = ( $file_path && is_readable( $file_path ) ) ? size_format( (int) filesize( $file_path ) ) : '';

        return array(
            'id'              => (int) $row['id'],
            'name'            => (string) $row['title'],
            'type'            => (string) ( $row['doc_type'] ?? '' ),
            'status'          => (string) $row['status'],
            'space_id'        => $row['space_id'] !== null ? (int) $row['space_id'] : null,
            'space_name'      => (string) ( $row['space_name'] ?? '' ),
            'space_color'     => (string) ( $row['space_color'] ?? 'slate' ),
            'projectCode'     => (string) ( $row['project_code'] ?? '' ),
            'tags'            => $tags,
            'uploadedAt'      => (string) $row['created_at'],
            'uploadedBy'      => $row['created_by'] ? (string) get_the_author_meta( 'display_name', (int) $row['created_by'] ) : '',
            'fileSize'        => $size,
            'currentRevision' => (int) $row['current_revision'],
            'attachment_id'   => $attachment_id,
            'url'             => $file_url,
            'metadata'        => array(
                'projectCode'   => (string) ( $row['project_code'] ?? '' ),
                'documentType'  => (string) ( $row['doc_type'] ?? '' ),
            ),
        );
    }

    private function detect_doc_type( string $filename ): string {
        $ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( $ext !== '' ) {
            return $ext;
        }
        return 'file';
    }

    private function extract_project_code( string $text ): string {
        // Common EU project code patterns: IPA-2025-XXX, RDP-2025, etc.
        if ( preg_match( '/\b([A-Z]{2,10}-\d{4}(?:-[A-Z0-9]{2,12})*)\b/', $text, $m ) ) {
            return (string) $m[1];
        }
        if ( preg_match( '/\b([A-Z]{2,10}-\d{4})\b/', $text, $m2 ) ) {
            return (string) $m2[1];
        }
        return '';
    }

    private function handle_upload_to_media( array $file ): int|WP_Error {
        if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
            return Json::error( 'invalid_file', 'Missing upload', 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = array(
            'test_form' => false,
            'mimes'     => null,
        );

        $uploaded = wp_handle_upload( $file, $overrides );

        if ( ! is_array( $uploaded ) || empty( $uploaded['file'] ) ) {
            return Json::error( 'upload_failed', 'Upload failed', 500 );
        }

        $file_path = (string) $uploaded['file'];
        $filetype  = wp_check_filetype( basename( $file_path ), null );

        $attachment = array(
            'post_mime_type' => (string) ( $filetype['type'] ?? 'application/octet-stream' ),
            'post_title'     => sanitize_file_name( (string) pathinfo( basename( $file_path ), PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment( $attachment, $file_path );
        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        if ( is_array( $attach_data ) ) {
            wp_update_attachment_metadata( $attach_id, $attach_data );
        }

        return (int) $attach_id;
    }
}
