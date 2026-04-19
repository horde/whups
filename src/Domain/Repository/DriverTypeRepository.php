<?php

declare(strict_types=1);

/**
 * Type repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\TypeRepositoryInterface;
use Whups_Driver;

final class DriverTypeRepository implements TypeRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function getTypesForQueue(int $queueId): array
    {
        return $this->driver->getTypes($queueId);
    }

    public function getDefaultType(int $queueId): ?int
    {
        $default = $this->driver->getDefaultType($queueId);

        return $default !== null ? (int) $default : null;
    }
}
