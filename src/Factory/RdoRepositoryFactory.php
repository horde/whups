<?php

declare(strict_types=1);

/**
 * Factory for Rdo-backed repository implementations.
 *
 * Wraps the existing RdoDriver and exposes repository instances
 * via the same getter interface as DriverRepositoryFactory.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Whups\Factory;

use Horde\Db\Adapter;
use Horde\Whups\Domain\AttributeRepositoryInterface;
use Horde\Whups\Domain\PriorityRepositoryInterface;
use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Domain\TicketRepositoryInterface;
use Horde\Whups\Domain\TypeRepositoryInterface;
use Horde\Whups\Domain\VersionRepositoryInterface;
use Horde\Whups\Rdo\RdoDriver;

final class RdoRepositoryFactory
{
    private readonly RdoDriver $rdo;

    public function __construct(Adapter $db)
    {
        $this->rdo = new RdoDriver($db);
    }

    public function queues(): QueueRepositoryInterface
    {
        return $this->rdo->queues();
    }

    public function states(): StateRepositoryInterface
    {
        return $this->rdo->states();
    }

    public function types(): TypeRepositoryInterface
    {
        return $this->rdo->types();
    }

    public function priorities(): PriorityRepositoryInterface
    {
        return $this->rdo->priorities();
    }

    public function versions(): VersionRepositoryInterface
    {
        return $this->rdo->versions();
    }

    public function attributes(): AttributeRepositoryInterface
    {
        return $this->rdo->attributes();
    }

    public function tickets(): TicketRepositoryInterface
    {
        return $this->rdo->tickets();
    }
}
