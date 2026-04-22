<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Whups\Domain\VersionRepositoryInterface;
use Horde\Whups\Rdo\Repository\RdoVersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RdoVersionRepository::class)]
class RdoVersionRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $repo = new RdoVersionRepository($adapter);

        $this->assertInstanceOf(VersionRepositoryInterface::class, $repo);
    }

    public function testGetVersionsForQueueCallsSelectAssoc(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())
            ->method('selectAssoc')
            ->with(
                $this->stringContains('whups_versions'),
                [42],
            )
            ->willReturn([1 => 'v1.0', 2 => 'v2.0']);

        $repo = new RdoVersionRepository($adapter);
        $result = $repo->getVersionsForQueue(42);

        $this->assertSame([1 => 'v1.0', 2 => 'v2.0'], $result);
    }
}
