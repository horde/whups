<?php

declare(strict_types=1);

/**
 * Repository interface for queue data access.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface QueueRepositoryInterface
{
    /**
     * Return all queues as id => name pairs.
     *
     * @return array<int,string>
     */
    public function listQueues(): array;

    /**
     * Return a single queue by ID.
     */
    public function getQueue(int $id): Queue;

    /**
     * Return the list of users assigned to a queue.
     *
     * @return list<string>
     */
    public function getQueueUsers(int $queueId): array;

    /**
     * Return queue summary data: open ticket counts by queue and type.
     *
     * @param int[] $queueIds
     *
     * @return list<array{id: int, slug: string, name: string, description: string, type: string, open_tickets: int}>
     */
    public function getQueueSummary(array $queueIds): array;
}
