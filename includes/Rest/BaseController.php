<?php
declare(strict_types=1);

namespace TightShip\Engine\Rest;

use TightShip\Engine\Core\Capabilities;
use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class BaseController extends WP_REST_Controller {

    protected string $namespace = Routes::NAMESPACE;

    protected function can_access(): bool {
        return is_user_logged_in() && current_user_can( Capabilities::CAP_ACCESS );
    }

    protected function can_manage(): bool {
        return is_user_logged_in() && current_user_can( Capabilities::CAP_MANAGE );
    }

    public function permission_access( WP_REST_Request $request ): bool|WP_Error {
        return $this->can_access() ? true : new WP_Error( 'forbidden', 'Forbidden', array( 'status' => 403 ) );
    }

    public function permission_manage( WP_REST_Request $request ): bool|WP_Error {
        return $this->can_manage() ? true : new WP_Error( 'forbidden', 'Forbidden', array( 'status' => 403 ) );
    }
}
