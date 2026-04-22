<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Whups\Domain\StateCategory;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Rdo\Repository\RdoStateRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RdoStateRepository::class)]
class RdoStateRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $repo = new RdoStateRepository($adapter);

        $this->assertInstanceOf(StateRepositoryInterface::class, $repo);
    }

    public function testGetStatesWithoutCategoryFilter(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectAssoc')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('whups_states'),
                    $this->logicalNot($this->stringContains('state_category IN')),
                ),
                [3],
            )
            ->willReturn([1 => 'Open', 2 => 'Closed']);

        $repo = new RdoStateRepository($adapter);
        $result = $repo->getStates(3);

        $this->assertSame([1 => 'Open', 2 => 'Closed'], $result);
    }

    public function testGetStatesWithSingleCategoryFilter(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectAssoc')
            ->with(
                $this->stringContains('state_category IN'),
                [3, 'resolved'],
            )
            ->willReturn([5 => 'Fixed']);

        $repo = new RdoStateRepository($adapter);
        $result = $repo->getStates(3, StateCategory::Resolved);

        $this->assertSame([5 => 'Fixed'], $result);
    }

    public function testGetStatesWithArrayCategoryFilter(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectAssoc')
            ->with(
                $this->stringContains('state_category IN (?, ?)'),
                [3, 'new', 'assigned'],
            )
            ->willReturn([1 => 'New', 2 => 'In Progress']);

        $repo = new RdoStateRepository($adapter);
        $result = $repo->getStates(3, [StateCategory::New, StateCategory::Assigned]);

        $this->assertSame([1 => 'New', 2 => 'In Progress'], $result);
    }

    public function testGetDefaultStateReturnsNullWhenNoneSet(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectValue')
            ->willReturn(false);

        $repo = new RdoStateRepository($adapter);

        $this->assertNull($repo->getDefaultState(1));
    }

    public function testGetDefaultStateReturnsInt(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectValue')
            ->willReturn('7');

        $repo = new RdoStateRepository($adapter);

        $this->assertSame(7, $repo->getDefaultState(1));
    }

    public function testIsCategoryMatchesCorrectly(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectValue')
            ->with(
                $this->stringContains('whups_states'),
                [42],
            )
            ->willReturn('resolved');

        $repo = new RdoStateRepository($adapter);

        $this->assertTrue($repo->isCategory(StateCategory::Resolved, 42));
    }

    public function testIsCategoryReturnsFalseOnMismatch(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectValue')
            ->willReturn('new');

        $repo = new RdoStateRepository($adapter);

        $this->assertFalse($repo->isCategory(StateCategory::Resolved, 42));
    }
}
