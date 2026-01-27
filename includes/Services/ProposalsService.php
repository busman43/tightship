<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProposalsService {

    private AuditService $audit;

    public function __construct() {
        $this->audit = new AuditService();
    }

    public function list(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT p.*, pr.code AS project_code, pr.name AS project_name
             FROM " . Tables::proposals() . " p
             LEFT JOIN " . Tables::projects() . " pr ON p.project_id = pr.id
             ORDER BY p.updated_at DESC, p.id DESC LIMIT 300",
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $out = array();
        foreach ( $rows as $r ) {
            $out[] = $this->hydrate( $r );
        }
        return $out;
    }

    public function create( array $data, int $actor_id ): int|\WP_Error {
        global $wpdb;

        $title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
        if ( $title === '' ) {
            return Json::error( 'invalid_proposal', 'Proposal title is required', 400 );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            Tables::proposals(),
            array(
                'title'      => $title,
                'project_id' => ! empty( $data['project_id'] ) ? (int) $data['project_id'] : null,
                'donor'      => sanitize_text_field( (string) ( $data['donor'] ?? '' ) ),
                'stage'      => sanitize_key( (string) ( $data['stage'] ?? 'concept-note' ) ),
                'due_date'   => ! empty( $data['due_date'] ) ? (string) $data['due_date'] : null,
                'amount'     => isset( $data['amount'] ) && $data['amount'] !== '' ? (string) (float) $data['amount'] : null,
                'probability'=> (int) ( $data['probability'] ?? 0 ),
                'notes'      => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
                'created_by' => $actor_id,
                'updated_by' => $actor_id,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
        );

        $id = (int) $wpdb->insert_id;
        $this->audit->log( 'proposal.create', 'proposal', $id, array( 'title' => $title ), $actor_id );

        return $id;
    }

    public function update( int $id, array $data, int $actor_id ): bool|\WP_Error {
        global $wpdb;

        $updated = $wpdb->update(
            Tables::proposals(),
            array(
                'title'      => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
                'project_id' => ! empty( $data['project_id'] ) ? (int) $data['project_id'] : null,
                'donor'      => sanitize_text_field( (string) ( $data['donor'] ?? '' ) ),
                'stage'      => sanitize_key( (string) ( $data['stage'] ?? 'concept-note' ) ),
                'due_date'   => ! empty( $data['due_date'] ) ? (string) $data['due_date'] : null,
                'amount'     => isset( $data['amount'] ) && $data['amount'] !== '' ? (string) (float) $data['amount'] : null,
                'probability'=> (int) ( $data['probability'] ?? 0 ),
                'notes'      => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
                'updated_by' => $actor_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            return Json::error( 'db_error', 'Failed to update proposal', 500 );
        }

        $this->audit->log( 'proposal.update', 'proposal', $id, array(), $actor_id );
        return true;
    }

    public function get( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . Tables::proposals() . " WHERE id = %d", $id ),
            ARRAY_A
        );

        return is_array( $row ) ? $this->hydrate( $row ) : null;
    }

    private function hydrate( array $r ): array {
        return array(
            'id'          => (int) $r['id'],
            'title'       => (string) $r['title'],
            'project_id'  => $r['project_id'] !== null ? (int) $r['project_id'] : null,
            'project_code'=> (string) ( $r['project_code'] ?? '' ),
            'project_name'=> (string) ( $r['project_name'] ?? '' ),
            'donor'       => (string) ( $r['donor'] ?? '' ),
            'stage'       => (string) ( $r['stage'] ?? 'concept-note' ),
            'due_date'    => $r['due_date'] ? (string) $r['due_date'] : null,
            'amount'      => $r['amount'] !== null ? (float) $r['amount'] : null,
            'probability' => (int) ( $r['probability'] ?? 0 ),
            'notes'       => (string) ( $r['notes'] ?? '' ),
        );
    }
}
