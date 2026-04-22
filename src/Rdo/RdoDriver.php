<?php

declare(strict_types=1);

/**
 * Rdo-backed repository factory.
 *
 * Lazily creates repository instances backed by the modern Horde\Rdo
 * stack (TypeSchema + SqlRepository + Hydrator) using Horde\Db\Adapter.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Rdo;

use Horde\Db\Adapter;
use Horde\Whups\Rdo\Repository\RdoAttributeRepository;
use Horde\Whups\Rdo\Repository\RdoPriorityRepository;
use Horde\Whups\Rdo\Repository\RdoQueueRepository;
use Horde\Whups\Rdo\Repository\RdoStateRepository;
use Horde\Whups\Rdo\Repository\RdoTicketRepository;
use Horde\Whups\Rdo\Repository\RdoTypeRepository;
use Horde\Whups\Rdo\Repository\RdoVersionRepository;

final class RdoDriver
{
    private ?RdoQueueRepository $queues = null;
    private ?RdoStateRepository $states = null;
    private ?RdoTypeRepository $types = null;
    private ?RdoPriorityRepository $priorities = null;
    private ?RdoVersionRepository $versions = null;
    private ?RdoAttributeRepository $attributes = null;
    private ?RdoTicketRepository $tickets = null;

    public function __construct(
        private readonly Adapter $db,
    ) {}

    public function queues(): RdoQueueRepository
    {
        return $this->queues ??= new RdoQueueRepository($this->db);
    }

    public function states(): RdoStateRepository
    {
        return $this->states ??= new RdoStateRepository($this->db);
    }

    public function types(): RdoTypeRepository
    {
        return $this->types ??= new RdoTypeRepository($this->db);
    }

    public function priorities(): RdoPriorityRepository
    {
        return $this->priorities ??= new RdoPriorityRepository($this->db);
    }

    public function versions(): RdoVersionRepository
    {
        return $this->versions ??= new RdoVersionRepository($this->db);
    }

    public function attributes(): RdoAttributeRepository
    {
        return $this->attributes ??= new RdoAttributeRepository($this->db);
    }

    public function tickets(): RdoTicketRepository
    {
        return $this->tickets ??= new RdoTicketRepository($this->db);
    }
}
