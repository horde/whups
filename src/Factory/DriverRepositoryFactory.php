<?php

declare(strict_types=1);

/**
 * Factory for legacy Whups_Driver-backed repository implementations.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Whups\Factory;

use Horde\Whups\Domain\AttributeRepositoryInterface;
use Horde\Whups\Domain\PriorityRepositoryInterface;
use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde\Whups\Domain\Repository\DriverAttributeRepository;
use Horde\Whups\Domain\Repository\DriverPriorityRepository;
use Horde\Whups\Domain\Repository\DriverQueueRepository;
use Horde\Whups\Domain\Repository\DriverStateRepository;
use Horde\Whups\Domain\Repository\DriverTicketRepository;
use Horde\Whups\Domain\Repository\DriverTypeRepository;
use Horde\Whups\Domain\Repository\DriverVersionRepository;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Domain\TicketRepositoryInterface;
use Horde\Whups\Domain\TypeRepositoryInterface;
use Horde\Whups\Domain\VersionRepositoryInterface;
use Horde_Registry;
use Whups_Driver;

final class DriverRepositoryFactory
{
    private ?DriverQueueRepository $queues = null;
    private ?DriverStateRepository $states = null;
    private ?DriverTypeRepository $types = null;
    private ?DriverPriorityRepository $priorities = null;
    private ?DriverVersionRepository $versions = null;
    private ?DriverAttributeRepository $attributes = null;
    private ?DriverTicketRepository $tickets = null;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
    ) {}

    public function queues(): QueueRepositoryInterface
    {
        return $this->queues ??= new DriverQueueRepository($this->driver, $this->registry);
    }

    public function states(): StateRepositoryInterface
    {
        return $this->states ??= new DriverStateRepository($this->driver);
    }

    public function types(): TypeRepositoryInterface
    {
        return $this->types ??= new DriverTypeRepository($this->driver);
    }

    public function priorities(): PriorityRepositoryInterface
    {
        return $this->priorities ??= new DriverPriorityRepository($this->driver);
    }

    public function versions(): VersionRepositoryInterface
    {
        return $this->versions ??= new DriverVersionRepository($this->driver, $this->registry);
    }

    public function attributes(): AttributeRepositoryInterface
    {
        return $this->attributes ??= new DriverAttributeRepository($this->driver);
    }

    public function tickets(): TicketRepositoryInterface
    {
        return $this->tickets ??= new DriverTicketRepository($this->driver);
    }
}
