<?php
declare(strict_types=1);

namespace TightShip\Engine\Db;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tables {

    public static function documents(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_documents';
    }

    public static function revisions(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_revisions';
    }

    public static function spaces(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_spaces';
    }

    public static function rules(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_rules';
    }

    public static function events(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_events';
    }

    public static function projects(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_projects';
    }

    public static function proposals(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_proposals';
    }

    public static function contacts(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_contacts';
    }

    public static function templates(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_templates';
    }

    public static function audit(): string {
        global $wpdb;
        return $wpdb->prefix . 'tightship_audit';
    }
}
