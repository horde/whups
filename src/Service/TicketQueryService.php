<?php

declare(strict_types=1);

/**
 * Service for querying tickets with built-in permission filtering.
 *
 * Encapsulates the "get my tickets" / "get my requests" / "get queue
 * summary" use cases that the MyBugs portal blocks previously assembled
 * from globals and static helpers.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Service;

use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde\Whups\Domain\TicketRepositoryInterface;
use Horde_Group_Base;
use Horde_Group_Exception;
use Horde_Registry;

final class TicketQueryService
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly QueueRepositoryInterface $queues,
        private readonly PermissionChecker $permissions,
        private readonly Horde_Group_Base $groups,
        private readonly Horde_Registry $registry,
    ) {}

    /**
     * Get tickets assigned to the current user (including group ownership).
     *
     * Excludes resolved tickets. Filters to readable queues.
     *
     * @return list<array>
     */
    public function getMyTickets(?string $user = null): array
    {
        $user ??= $this->registry->getAuth();
        $queueIds = $this->getReadableQueueIds();
        if (!$queueIds) {
            return [];
        }

        $criteria = [
            'owner' => $this->getOwnerCriteria($user),
            'nores' => true,
            'queue' => $queueIds,
        ];

        return $this->tickets->findByProperties($criteria);
    }

    /**
     * Get tickets requested by the current user (where user is NOT owner).
     *
     * Excludes resolved tickets. Filters to readable queues.
     *
     * @return list<array>
     */
    public function getMyRequests(?string $user = null): array
    {
        $user ??= $this->registry->getAuth();
        $queueIds = $this->getReadableQueueIds();
        if (!$queueIds) {
            return [];
        }

        $criteria = [
            'requester' => $user,
            'notowner' => 'user:' . $user,
            'nores' => true,
            'queue' => $queueIds,
        ];

        return $this->tickets->findByProperties($criteria);
    }

    /**
     * Get queue summary (open ticket counts by queue and type).
     *
     * Filtered to readable queues.
     *
     * @return list<array{id: int, slug: string, name: string, description: string, type: string, open_tickets: int}>
     */
    public function getQueueSummary(): array
    {
        $queueIds = $this->getReadableQueueIds();
        if (!$queueIds) {
            return [];
        }

        return $this->queues->getQueueSummary($queueIds);
    }

    /**
     * Get readable queue IDs for the current user.
     *
     * @return int[]
     */
    private function getReadableQueueIds(): array
    {
        $allQueues = $this->queues->listQueues();
        $filtered = $this->permissions->filterQueues($allQueues);

        return array_keys($filtered);
    }

    /**
     * Get unassigned tickets across all readable queues.
     *
     * Excludes resolved tickets.
     *
     * @return list<array>
     */
    public function getUnassignedTickets(): array
    {
        $queueIds = $this->getReadableQueueIds();
        if (!$queueIds) {
            return [];
        }

        return $this->tickets->findByProperties([
            'notowner' => true,
            'nores' => true,
            'queue' => $queueIds,
        ]);
    }

    /**
     * Get open tickets in a specific queue.
     *
     * Returns empty array if queue is not readable by the current user.
     *
     * @return list<array>
     */
    public function getQueueTickets(int $queueId): array
    {
        $readable = $this->getReadableQueueIds();
        if (!in_array($queueId, $readable, true)) {
            return [];
        }

        return $this->tickets->findByProperties([
            'queue' => $queueId,
            'nores' => true,
        ]);
    }

    /**
     * Build owner criteria including group memberships.
     *
     * Replaces static Whups::getOwnerCriteria().
     *
     * @return list<string>  Owner strings like "user:alice", "group:5".
     */
    public function getOwnerCriteria(string $user): array
    {
        $criteria = ['user:' . $user];

        try {
            $mygroups = $this->groups->listGroups($user);
            foreach ($mygroups as $id => $group) {
                $criteria[] = 'group:' . $id;
            }
        } catch (Horde_Group_Exception $e) {
            // No group support — user-only criteria.
        }

        return $criteria;
    }
}
