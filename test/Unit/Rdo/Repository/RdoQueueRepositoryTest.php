<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde\Whups\Rdo\Repository\RdoQueueRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RdoQueueRepository::class)]
class RdoQueueRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $repo = new RdoQueueRepository($adapter);

        $this->assertInstanceOf(QueueRepositoryInterface::class, $repo);
    }

    public function testListQueuesCallsSelectAssoc(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectAssoc')
            ->with($this->stringContains('whups_queues'))
            ->willReturn([1 => 'Support', 2 => 'Bugs']);

        $repo = new RdoQueueRepository($adapter);
        $result = $repo->listQueues();

        $this->assertSame([1 => 'Support', 2 => 'Bugs'], $result);
    }

    public function testGetQueueUsersCallsSelectValues(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectValues')
            ->with(
                $this->stringContains('whups_queues_users'),
                [7],
            )
            ->willReturn(['alice', 'bob']);

        $repo = new RdoQueueRepository($adapter);
        $result = $repo->getQueueUsers(7);

        $this->assertSame(['alice', 'bob'], $result);
    }

    public function testGetQueueSummaryReturnsEmptyForNoIds(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->never())->method('selectAll');

        $repo = new RdoQueueRepository($adapter);
        $result = $repo->getQueueSummary([]);

        $this->assertSame([], $result);
    }
}
