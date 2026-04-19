<?php

declare(strict_types=1);

/**
 * Version repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\VersionRepositoryInterface;
use Whups_Driver;

final class DriverVersionRepository implements VersionRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function getVersionsForQueue(int $queueId): array
    {
        return $this->driver->getVersions($queueId);
    }
}
