<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain\Repository;

use Horde\Whups\Domain\Repository\DriverStateRepository;
use Horde\Whups\Domain\State;
use Horde\Whups\Domain\StateCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Whups_Driver;
use Whups_Driver_Sql;

#[CoversClass(DriverStateRepository::class)]
class DriverStateRepositoryTest extends TestCase
{
    private function mockDriver(): Whups_Driver
    {
        return $this->createMock(Whups_Driver_Sql::class);
    }

    public function testGetStatesConvertsSingleCategory(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getStates')
            ->with(5, 'assigned')
            ->willReturn([10 => 'In Progress']);

        $repo = new DriverStateRepository($driver);

        $this->assertSame(
            [10 => 'In Progress'],
            $repo->getStates(5, StateCategory::Assigned),
        );
    }

    public function testGetStatesConvertsCategoryArray(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getStates')
            ->with(5, ['new', 'assigned'])
            ->willReturn([10 => 'Open', 11 => 'In Progress']);

        $repo = new DriverStateRepository($driver);

        $this->assertSame(
            [10 => 'Open', 11 => 'In Progress'],
            $repo->getStates(5, [StateCategory::New, StateCategory::Assigned]),
        );
    }

    public function testGetStatesPassesEmptyStringForNull(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getStates')
            ->with(5, '')
            ->willReturn([10 => 'All states']);

        $repo = new DriverStateRepository($driver);

        $this->assertSame(
            [10 => 'All states'],
            $repo->getStates(5),
        );
    }

    public function testGetStateReturnsValueObject(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getState')->with(3)->willReturn([
            'id' => 3,
            'name' => 'In Progress',
            'description' => 'Being worked on',
            'category' => 'assigned',
            'type' => 5,
        ]);

        $repo = new DriverStateRepository($driver);
        $state = $repo->getState(3);

        $this->assertInstanceOf(State::class, $state);
        $this->assertSame(3, $state->id);
        $this->assertSame(StateCategory::Assigned, $state->category);
    }

    public function testIsCategoryConvertsEnumToString(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('isCategory')
            ->with('resolved', 42)
            ->willReturn(true);

        $repo = new DriverStateRepository($driver);

        $this->assertTrue($repo->isCategory(StateCategory::Resolved, 42));
    }
}
