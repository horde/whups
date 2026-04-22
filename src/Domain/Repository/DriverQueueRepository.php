<?php

declare(strict_types=1);

/**
 * Queue repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\Queue;
use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde_Registry;
use Whups_Driver;

final class DriverQueueRepository implements QueueRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
    ) {}

    public function listQueues(): array
    {
        if ($this->registry->hasInterface('tickets') === 'whups') {
            return $this->driver->getQueuesInternal();
        }

        return $this->driver->getQueues();
    }

    public function getQueue(int $id): Queue
    {
        if ($this->registry->hasInterface('tickets') === 'whups') {
            return Queue::fromDriverArray($this->driver->getQueueInternal($id));
        }

        return Queue::fromDriverArray($this->driver->getQueue($id));
    }

    public function getQueueUsers(int $queueId): array
    {
        return $this->driver->getQueueUsers($queueId);
    }

    public function getQueueSummary(array $queueIds): array
    {
        return $this->driver->getQueueSummary($queueIds);
    }
}
