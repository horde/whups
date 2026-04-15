<?php

declare(strict_types=1);

/**
 * Injectable permission checker for Whups.
 *
 * Encapsulates the Whups permission tree logic for queues, replies, and
 * comments.  Replaces the static Whups::hasPermission() and
 * Whups::permissionsFilter() methods with an injectable service.
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

use Horde_Perms;
use Horde_Perms_Base;
use Horde_Registry;

class PermissionChecker
{
    public function __construct(
        private readonly Horde_Perms_Base $perms,
        private readonly Horde_Registry $registry,
    ) {}

    /**
     * Check whether a user has a certain permission on a single queue.
     *
     * @param mixed          $queueId    The queue ID.
     * @param string|int     $permission 'assign', 'update', 'requester', or
     *                                   a Horde_Perms:: constant.
     * @param string|null    $user       User name (null = current user).
     */
    public function hasQueuePermission(
        mixed $queueId,
        string|int $permission,
        ?string $user = null,
    ): bool {
        $user ??= $this->registry->getAuth();

        $adminPerm = match ($permission) {
            'update', 'assign', 'requester' => Horde_Perms::EDIT,
            default => $permission,
        };

        if ($this->isAdmin($adminPerm, $user)) {
            return true;
        }

        // Standard SHOW/READ/EDIT/DELETE bitmask permissions.
        if (is_int($permission)) {
            return $this->perms->hasPermission(
                'whups:queues:' . $queueId,
                $user,
                $permission,
            );
        }

        // Named sub-permissions: assign, update, requester.
        $subPerm = 'whups:queues:' . $queueId . ':' . $permission;

        if ($this->perms->exists($subPerm)) {
            return (bool) $this->perms->getPermissions($subPerm, $user);
        }

        // Sub-permission not defined — fall back to EDIT on the queue,
        // but lock out guests and the 'requester' capability.
        if ($permission !== 'requester'
            && $this->registry->getAuth()
            && $this->perms->hasPermission(
                'whups:queues:' . $queueId,
                $user,
                Horde_Perms::EDIT,
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Filter a list of queues by permission.
     *
     * Input is keyed by queue ID (queueId => name).
     *
     * @return array  Filtered subset, same key structure.
     */
    public function filterQueues(
        array $queues,
        int $permission = Horde_Perms::READ,
        ?string $user = null,
        ?string $creator = null,
    ): array {
        $user ??= $this->registry->getAuth();

        if ($this->isAdmin($permission, $user)) {
            return $queues;
        }

        $out = [];
        foreach ($queues as $queueId => $name) {
            if (!$this->perms->exists('whups:queues:' . $queueId)
                || $this->perms->hasPermission(
                    'whups:queues:' . $queueId,
                    $user,
                    $permission,
                    $creator,
                )
            ) {
                $out[$queueId] = $name;
            }
        }

        return $out;
    }

    /**
     * Filter a flat list of queue IDs by permission.
     *
     * @param int[] $queueIds
     *
     * @return int[]  Filtered list.
     */
    public function filterQueueIds(
        array $queueIds,
        int $permission = Horde_Perms::READ,
        ?string $user = null,
        ?string $creator = null,
    ): array {
        $user ??= $this->registry->getAuth();

        if ($this->isAdmin($permission, $user)) {
            return $queueIds;
        }

        $out = [];
        foreach ($queueIds as $queueId) {
            if (!$this->perms->exists('whups:queues:' . $queueId)
                || $this->perms->hasPermission(
                    'whups:queues:' . $queueId,
                    $user,
                    $permission,
                    $creator,
                )
            ) {
                $out[] = $queueId;
            }
        }

        return $out;
    }

    /**
     * Filter a list of form replies by permission.
     *
     * Input is keyed by reply ID (replyId => name).
     */
    public function filterReplies(
        array $replies,
        int $permission = Horde_Perms::READ,
        ?string $user = null,
        ?string $creator = null,
    ): array {
        $user ??= $this->registry->getAuth();

        if ($this->isAdmin($permission, $user)) {
            return $replies;
        }

        $out = [];
        foreach ($replies as $replyId => $name) {
            if (!$this->perms->exists('whups:replies:' . $replyId)
                || $this->perms->hasPermission(
                    'whups:replies:' . $replyId,
                    $user,
                    $permission,
                    $creator,
                )
            ) {
                $out[$replyId] = $name;
            }
        }

        return $out;
    }

    /**
     * Filter ticket history, marking private comments and hiding content
     * the user cannot read.
     *
     * @param array       $history   History rows from Whups_Driver::getHistory().
     * @param int         $permission  Horde_Perms:: constant.
     * @param string|null $user        User name (null = current user).
     * @param string|null $creator     Ticket creator (for creator-based perms).
     *
     * @return array  Filtered history.
     */
    public function filterComments(
        array $history,
        int $permission = Horde_Perms::READ,
        ?string $user = null,
        ?string $creator = null,
    ): array {
        $user ??= $this->registry->getAuth();
        $admin = $this->isAdmin($permission, $user);
        $out = [];

        foreach ($history as $key => $row) {
            foreach ($row as $rkey => $rval) {
                if ($rkey !== 'changes') {
                    $out[$key][$rkey] = $rval;
                    continue;
                }
                foreach ($rval as $i => $change) {
                    if ($change['type'] !== 'comment'
                        || !$this->perms->exists('whups:comments:' . $change['value'])
                    ) {
                        $out[$key][$rkey][$i] = $change;
                        if (isset($change['comment'])) {
                            $out[$key]['comment_text'] = $change['comment'];
                        }
                    } else {
                        $change['private'] = true;
                        $out[$key][$rkey][$i] = $change;
                        if (isset($change['comment'])) {
                            if ($admin
                                || $this->perms->hasPermission(
                                    'whups:comments:' . $change['value'],
                                    $user,
                                    Horde_Perms::READ,
                                    $creator,
                                )
                            ) {
                                $out[$key]['comment_text'] = $change['comment'];
                            } else {
                                $out[$key][$rkey][$i]['comment'] = _("[Hidden]");
                            }
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Check whether the user is a Whups admin.
     */
    public function isAdmin(
        int $permLevel = Horde_Perms::EDIT,
        ?string $user = null,
    ): bool {
        return $this->registry->isAdmin([
            'permission' => 'whups:admin',
            'permlevel' => $permLevel,
            'user' => $user,
        ]);
    }
}
