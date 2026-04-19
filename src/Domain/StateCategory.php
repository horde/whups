<?php

declare(strict_types=1);

/**
 * State categories that drive workflow behavior in Whups.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

enum StateCategory: string
{
    case Unconfirmed = 'unconfirmed';
    case New = 'new';
    case Assigned = 'assigned';
    case Resolved = 'resolved';
}
