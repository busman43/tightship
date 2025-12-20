<?php
declare(strict_types=1);

namespace TightShip\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities {
	public const CAP_ACCESS = 'tightship_access';
	public const CAP_MANAGE = 'tightship_manage';
}
