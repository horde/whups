<?php

declare(strict_types=1);

/**
 * Strategy that selects between legacy and Rdo repository backends.
 *
 * Reads the configured backend from whups config via ConfigLoader
 * (no globals dependency) and lazily creates the appropriate
 * repository factory. Each create* method is designed as a
 * bindFactory target for Horde_Injector.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Whups\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Db\Adapter;
use Horde\Injector\Injector;
use Horde_Registry;
use Horde\Whups\Domain\AttributeRepositoryInterface;
use Horde\Whups\Domain\PriorityRepositoryInterface;
use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Domain\TicketRepositoryInterface;
use Horde\Whups\Domain\TypeRepositoryInterface;
use Horde\Whups\Domain\VersionRepositoryInterface;
use Whups_Driver;

final class RepositoryStrategy
{
    private ?DriverRepositoryFactory $driverFactory = null;
    private ?RdoRepositoryFactory $rdoFactory = null;
    private ?string $resolvedBackend = null;

    public function __construct(
        private readonly ConfigLoader $configLoader,
    ) {}

    private function backend(): string
    {
        if ($this->resolvedBackend === null) {
            $state = $this->configLoader->load('whups');
            $this->resolvedBackend = $state->get('tickets.repositories', 'legacy');
        }

        return $this->resolvedBackend;
    }

    private function driverFactory(Injector $injector): DriverRepositoryFactory
    {
        return $this->driverFactory ??= new DriverRepositoryFactory(
            $injector->getInstance(Whups_Driver::class),
            $injector->getInstance(Horde_Registry::class),
        );
    }

    private function rdoFactory(Injector $injector): RdoRepositoryFactory
    {
        return $this->rdoFactory ??= new RdoRepositoryFactory(
            $injector->getInstance(Adapter::class),
        );
    }

    public function createQueues(Injector $injector): QueueRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->queues()
            : $this->driverFactory($injector)->queues();
    }

    public function createStates(Injector $injector): StateRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->states()
            : $this->driverFactory($injector)->states();
    }

    public function createTypes(Injector $injector): TypeRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->types()
            : $this->driverFactory($injector)->types();
    }

    public function createPriorities(Injector $injector): PriorityRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->priorities()
            : $this->driverFactory($injector)->priorities();
    }

    public function createVersions(Injector $injector): VersionRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->versions()
            : $this->driverFactory($injector)->versions();
    }

    public function createAttributes(Injector $injector): AttributeRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->attributes()
            : $this->driverFactory($injector)->attributes();
    }

    public function createTickets(Injector $injector): TicketRepositoryInterface
    {
        return $this->backend() === 'rdo'
            ? $this->rdoFactory($injector)->tickets()
            : $this->driverFactory($injector)->tickets();
    }
}
