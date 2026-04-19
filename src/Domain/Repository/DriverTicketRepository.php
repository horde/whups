<?php

declare(strict_types=1);

/**
 * Ticket repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\TicketRepositoryInterface;
use Whups_Driver;

final class DriverTicketRepository implements TicketRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function findByProperties(array $criteria): array
    {
        return $this->driver->getTicketsByProperties($criteria);
    }
}
