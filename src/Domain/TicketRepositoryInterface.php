<?php

declare(strict_types=1);

/**
 * Repository interface for ticket query access.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface TicketRepositoryInterface
{
    /**
     * Query tickets by property criteria.
     *
     * @param array $criteria  Property filter (owner, requester, queue, nores, etc.)
     *
     * @return list<array>  Ticket data arrays (munged with joined names).
     */
    public function findByProperties(array $criteria): array;
}
