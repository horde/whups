<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain\Repository;

use Horde\Whups\Domain\Repository\DriverTicketRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Whups_Driver;
use Whups_Driver_Sql;

#[CoversClass(DriverTicketRepository::class)]
class DriverTicketRepositoryTest extends TestCase
{
    private function mockDriver(): Whups_Driver
    {
        return $this->createMock(Whups_Driver_Sql::class);
    }

    public function testFindByPropertiesDelegatesToDriver(): void
    {
        $criteria = [
            'owner' => ['user:alice'],
            'nores' => true,
            'queue' => [1, 2],
        ];
        $expected = [
            ['id' => 10, 'summary' => 'Bug A', 'queue' => 1],
            ['id' => 11, 'summary' => 'Bug B', 'queue' => 2],
        ];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getTicketsByProperties')
            ->with($criteria)
            ->willReturn($expected);

        $repo = new DriverTicketRepository($driver);

        $this->assertSame($expected, $repo->findByProperties($criteria));
    }

    public function testFindByPropertiesReturnsEmptyArray(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getTicketsByProperties')->willReturn([]);

        $repo = new DriverTicketRepository($driver);

        $this->assertSame([], $repo->findByProperties(['queue' => []]));
    }
}
