<?php
declare(strict_types=1);

namespace TightShip\Engine\Services;

use TightShip\Engine\Db\Tables;
use TightShip\Engine\Support\Json;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AuditService {

    public function log( string $action, string $entity, ?int $entity_id = null, array $meta = array(), ?int $actor_id = null ): void {
        global $wpdb;

        $actor_id = $actor_id ?? get_current_user_id();
        $wpdb->insert(
            Tables::audit(),
            array(
                'actor_id'  => $actor_id > 0 ? $actor_id : null,
                'action'    => $action,
                'entity'    => $entity,
                'entity_id' => $entity_id,
                'meta_json' => Json::encode( $meta ),
                'created_at'=> current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }
}
