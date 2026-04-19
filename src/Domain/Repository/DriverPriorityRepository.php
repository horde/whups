<?php

declare(strict_types=1);

/**
 * Priority repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\PriorityRepositoryInterface;
use Whups_Driver;

final class DriverPriorityRepository implements PriorityRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function getPriorities(int $typeId): array
    {
        return $this->driver->getPriorities($typeId);
    }

    public function getDefaultPriority(int $typeId): ?int
    {
        $default = $this->driver->getDefaultPriority($typeId);

        return $default !== null ? (int) $default : null;
    }
}
