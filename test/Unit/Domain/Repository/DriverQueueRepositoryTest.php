<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain\Repository;

use Horde\Whups\Domain\Queue;
use Horde\Whups\Domain\Repository\DriverQueueRepository;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Whups_Driver;
use Whups_Driver_Sql;

#[CoversClass(DriverQueueRepository::class)]
class DriverQueueRepositoryTest extends TestCase
{
    private function mockDriver(): Whups_Driver
    {
        return $this->createMock(Whups_Driver_Sql::class);
    }

    private function mockRegistry(string $ticketsApp = 'whups'): Horde_Registry
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('hasInterface')
            ->with('tickets')
            ->willReturn($ticketsApp);

        return $registry;
    }

    public function testListQueuesReturnsDriverResult(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getQueuesInternal')->willReturn([1 => 'Support', 2 => 'Dev']);

        $repo = new DriverQueueRepository($driver, $this->mockRegistry());

        $this->assertSame([1 => 'Support', 2 => 'Dev'], $repo->listQueues());
    }

    public function testGetQueueReturnsValueObject(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getQueueInternal')->with(7)->willReturn([
            'id' => 7,
            'name' => 'Support',
            'description' => 'General support',
            'versioned' => true,
            'slug' => 'support',
            'email' => 'support@example.com',
            'readonly' => false,
        ]);

        $repo = new DriverQueueRepository($driver, $this->mockRegistry());
        $queue = $repo->getQueue(7);

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertSame(7, $queue->id);
        $this->assertSame('Support', $queue->name);
        $this->assertTrue($queue->versioned);
    }

    public function testGetQueueUsersReturnsDriverResult(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getQueueUsers')->with(3)->willReturn(['alice', 'bob']);

        $repo = new DriverQueueRepository($driver, $this->mockRegistry());

        $this->assertSame(['alice', 'bob'], $repo->getQueueUsers(3));
    }

    public function testGetQueueSummaryDelegatesToDriver(): void
    {
        $expected = [
            ['id' => 1, 'slug' => 'support', 'name' => 'Support', 'description' => '', 'type' => 'Bug', 'open_tickets' => 5],
            ['id' => 2, 'slug' => 'dev', 'name' => 'Dev', 'description' => '', 'type' => 'Feature', 'open_tickets' => 3],
        ];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getQueueSummary')
            ->with([1, 2])
            ->willReturn($expected);

        $repo = new DriverQueueRepository($driver, $this->mockRegistry());

        $this->assertSame($expected, $repo->getQueueSummary([1, 2]));
    }
}
