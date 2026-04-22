<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Rdo;

use Horde\Db\Adapter;
use Horde\Whups\Rdo\RdoDriver;
use Horde\Whups\Rdo\Repository\RdoAttributeRepository;
use Horde\Whups\Rdo\Repository\RdoPriorityRepository;
use Horde\Whups\Rdo\Repository\RdoQueueRepository;
use Horde\Whups\Rdo\Repository\RdoStateRepository;
use Horde\Whups\Rdo\Repository\RdoTicketRepository;
use Horde\Whups\Rdo\Repository\RdoTypeRepository;
use Horde\Whups\Rdo\Repository\RdoVersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RdoDriver::class)]
class RdoDriverTest extends TestCase
{
    private function createDriver(): RdoDriver
    {
        $adapter = $this->createMock(Adapter::class);
        return new RdoDriver($adapter);
    }

    public function testQueuesReturnsRdoQueueRepository(): void
    {
        $this->assertInstanceOf(RdoQueueRepository::class, $this->createDriver()->queues());
    }

    public function testStatesReturnsRdoStateRepository(): void
    {
        $this->assertInstanceOf(RdoStateRepository::class, $this->createDriver()->states());
    }

    public function testTypesReturnsRdoTypeRepository(): void
    {
        $this->assertInstanceOf(RdoTypeRepository::class, $this->createDriver()->types());
    }

    public function testPrioritiesReturnsRdoPriorityRepository(): void
    {
        $this->assertInstanceOf(RdoPriorityRepository::class, $this->createDriver()->priorities());
    }

    public function testVersionsReturnsRdoVersionRepository(): void
    {
        $this->assertInstanceOf(RdoVersionRepository::class, $this->createDriver()->versions());
    }

    public function testAttributesReturnsRdoAttributeRepository(): void
    {
        $this->assertInstanceOf(RdoAttributeRepository::class, $this->createDriver()->attributes());
    }

    public function testTicketsReturnsRdoTicketRepository(): void
    {
        $this->assertInstanceOf(RdoTicketRepository::class, $this->createDriver()->tickets());
    }

    public function testLazilyCreatesRepositoryOnce(): void
    {
        $driver = $this->createDriver();

        $queues1 = $driver->queues();
        $queues2 = $driver->queues();

        $this->assertSame($queues1, $queues2);
    }
}
