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
use Horde_Registry;
use Whups_Driver;

final class DriverVersionRepository implements VersionRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
    ) {}

    public function getVersionsForQueue(int $queueId): array
    {
        if ($queueId === 0) {
            return [];
        }

        // If another app provides the tickets interface, go through
        // the registry so it can supply version data. Only use the
        // internal shortcut when whups itself is the provider.
        if ($this->registry->hasInterface('tickets') !== 'whups') {
            return $this->driver->getVersions($queueId);
        }

        $versionInfo = $this->driver->getVersionInfoInternal($queueId);
        $versions = [];
        foreach ($versionInfo as $row) {
            if (!empty($row['version_active'])) {
                $versions[(int) $row['version_id']] = $row['version_name'];
            }
        }

        return $versions;
    }
}
