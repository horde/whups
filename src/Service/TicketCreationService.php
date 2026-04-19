<?php

declare(strict_types=1);

/**
 * Orchestrates ticket creation.
 *
 * Resolves defaults (type, state, priority) via repository interfaces,
 * then delegates persistence, hooks, attachments and notifications to
 * Whups_Ticket::newTicket().
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Service;

use Horde\Whups\Domain\PriorityRepositoryInterface;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Domain\TypeRepositoryInterface;
use Whups_Exception;
use Whups_Ticket;

final class TicketCreationService
{
    public function __construct(
        private readonly TypeRepositoryInterface $types,
        private readonly StateRepositoryInterface $states,
        private readonly PriorityRepositoryInterface $priorities,
    ) {}

    /**
     * Create a ticket from the given info array.
     *
     * Resolves missing type/state/priority to their defaults before
     * delegating to Whups_Ticket::newTicket().
     *
     * @param array        $info      Ticket data (queue, type, state, priority, summary, etc.)
     * @param string|false $requester The authenticated user, or false for guest.
     *
     * @return Whups_Ticket The created ticket.
     * @throws Whups_Exception If required defaults are missing.
     */
    public function createTicket(array $info, string|false $requester): Whups_Ticket
    {
        $this->resolveDefaults($info);

        return Whups_Ticket::newTicket($info, $requester);
    }

    /**
     * Fill in default type, state, and priority when not provided.
     *
     * @throws Whups_Exception If a required default is not configured.
     */
    private function resolveDefaults(array &$info): void
    {
        if (!isset($info['type'])) {
            $default = $this->types->getDefaultType((int) $info['queue']);
            if ($default === null) {
                throw new Whups_Exception(
                    'No type for this ticket and no default type for the queue.',
                );
            }
            $info['type'] = $default;
        }

        if (!isset($info['state'])) {
            $default = $this->states->getDefaultState((int) $info['type']);
            if ($default === null) {
                throw new Whups_Exception(
                    'No state for this ticket and no default state for the ticket type.',
                );
            }
            $info['state'] = $default;
        }

        if (!isset($info['priority'])) {
            $default = $this->priorities->getDefaultPriority((int) $info['type']);
            if ($default === null) {
                throw new Whups_Exception(
                    'No priority for this ticket and no default priority for the ticket type.',
                );
            }
            $info['priority'] = $default;
        }
    }
}
