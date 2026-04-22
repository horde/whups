<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Service;

use Horde\Whups\Domain\QueueRepositoryInterface;
use Horde\Whups\Domain\TicketRepositoryInterface;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\TicketQueryService;
use Horde_Group_Base;
use Horde_Group_Exception;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TicketQueryService::class)]
class TicketQueryServiceTest extends TestCase
{
    private function buildService(
        ?TicketRepositoryInterface $tickets = null,
        ?QueueRepositoryInterface $queues = null,
        ?PermissionChecker $permissions = null,
        ?Horde_Group_Base $groups = null,
        ?Horde_Registry $registry = null,
    ): TicketQueryService {
        $tickets ??= $this->createMock(TicketRepositoryInterface::class);
        $queues ??= $this->createMock(QueueRepositoryInterface::class);
        $permissions ??= $this->createMock(PermissionChecker::class);
        $groups ??= $this->createMock(Horde_Group_Base::class);
        $registry ??= $this->createMock(Horde_Registry::class);

        return new TicketQueryService($tickets, $queues, $permissions, $groups, $registry);
    }

    private function mockQueuesAndPermissions(
        QueueRepositoryInterface $queues,
        PermissionChecker $permissions,
        array $allQueues,
        array $filteredQueues,
    ): void {
        $queues->method('listQueues')->willReturn($allQueues);
        $permissions->method('filterQueues')->with($allQueues)->willReturn($filteredQueues);
    }

    public function testGetMyTicketsBuildsOwnerCriteriaWithGroups(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);
        $groups = $this->createMock(Horde_Group_Base::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], [1 => 'Q1']);
        $groups->method('listGroups')->with('alice')->willReturn([5 => 'Devs', 9 => 'Ops']);

        $tickets->expects($this->once())
            ->method('findByProperties')
            ->with([
                'owner' => ['user:alice', 'group:5', 'group:9'],
                'nores' => true,
                'queue' => [1],
            ])
            ->willReturn([['id' => 42]]);

        $service = $this->buildService($tickets, $queues, $permissions, $groups);
        $result = $service->getMyTickets('alice');

        $this->assertSame([['id' => 42]], $result);
    }

    public function testGetMyTicketsReturnsEmptyWhenNoReadableQueues(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], []);

        $tickets->expects($this->never())->method('findByProperties');

        $service = $this->buildService($tickets, $queues, $permissions);
        $result = $service->getMyTickets('alice');

        $this->assertSame([], $result);
    }

    public function testGetMyTicketsFallsBackToRegistryAuth(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);
        $groups = $this->createMock(Horde_Group_Base::class);
        $registry = $this->createMock(Horde_Registry::class);

        $registry->method('getAuth')->willReturn('bob');
        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], [1 => 'Q1']);
        $groups->method('listGroups')->with('bob')->willReturn([]);

        $tickets->expects($this->once())
            ->method('findByProperties')
            ->with($this->callback(function (array $criteria): bool {
                return $criteria['owner'] === ['user:bob'];
            }))
            ->willReturn([]);

        $service = $this->buildService($tickets, $queues, $permissions, $groups, $registry);
        $service->getMyTickets();
    }

    public function testGetMyRequestsSetsCriteria(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [2 => 'Q2', 3 => 'Q3'], [2 => 'Q2', 3 => 'Q3']);

        $tickets->expects($this->once())
            ->method('findByProperties')
            ->with([
                'requester' => 'alice',
                'notowner' => 'user:alice',
                'nores' => true,
                'queue' => [2, 3],
            ])
            ->willReturn([['id' => 99]]);

        $service = $this->buildService($tickets, $queues, $permissions);
        $result = $service->getMyRequests('alice');

        $this->assertSame([['id' => 99]], $result);
    }

    public function testGetMyRequestsReturnsEmptyWhenNoReadableQueues(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], []);

        $tickets->expects($this->never())->method('findByProperties');

        $service = $this->buildService($tickets, $queues, $permissions);

        $this->assertSame([], $service->getMyRequests('alice'));
    }

    public function testGetQueueSummaryDelegatesToRepository(): void
    {
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1', 2 => 'Q2'], [1 => 'Q1']);

        $expected = [
            ['id' => 1, 'name' => 'Q1', 'type' => 'Bug', 'open_tickets' => 7],
        ];
        $queues->expects($this->once())
            ->method('getQueueSummary')
            ->with([1])
            ->willReturn($expected);

        $service = $this->buildService(queues: $queues, permissions: $permissions);
        $result = $service->getQueueSummary();

        $this->assertSame($expected, $result);
    }

    public function testGetQueueSummaryReturnsEmptyWhenNoReadableQueues(): void
    {
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], []);

        $queues->expects($this->never())->method('getQueueSummary');

        $service = $this->buildService(queues: $queues, permissions: $permissions);

        $this->assertSame([], $service->getQueueSummary());
    }

    public function testGetUnassignedTicketsBuildsCriteria(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1', 2 => 'Q2'], [1 => 'Q1', 2 => 'Q2']);

        $tickets->expects($this->once())
            ->method('findByProperties')
            ->with([
                'notowner' => true,
                'nores' => true,
                'queue' => [1, 2],
            ])
            ->willReturn([['id' => 55]]);

        $service = $this->buildService($tickets, $queues, $permissions);
        $result = $service->getUnassignedTickets();

        $this->assertSame([['id' => 55]], $result);
    }

    public function testGetUnassignedTicketsReturnsEmptyWhenNoReadableQueues(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], []);

        $tickets->expects($this->never())->method('findByProperties');

        $service = $this->buildService($tickets, $queues, $permissions);

        $this->assertSame([], $service->getUnassignedTickets());
    }

    public function testGetQueueTicketsReturnTicketsForReadableQueue(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1', 2 => 'Q2'], [1 => 'Q1', 2 => 'Q2']);

        $tickets->expects($this->once())
            ->method('findByProperties')
            ->with([
                'queue' => 1,
                'nores' => true,
            ])
            ->willReturn([['id' => 10], ['id' => 11]]);

        $service = $this->buildService($tickets, $queues, $permissions);
        $result = $service->getQueueTickets(1);

        $this->assertSame([['id' => 10], ['id' => 11]], $result);
    }

    public function testGetQueueTicketsReturnsEmptyForUnreadableQueue(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1', 2 => 'Q2'], [1 => 'Q1']);

        $tickets->expects($this->never())->method('findByProperties');

        $service = $this->buildService($tickets, $queues, $permissions);
        $result = $service->getQueueTickets(2);

        $this->assertSame([], $result);
    }

    public function testGetOwnerCriteriaIncludesGroupIds(): void
    {
        $groups = $this->createMock(Horde_Group_Base::class);
        $groups->method('listGroups')->with('alice')->willReturn([5 => 'Devs', 9 => 'Ops']);

        $service = $this->buildService(groups: $groups);

        $this->assertSame(['user:alice', 'group:5', 'group:9'], $service->getOwnerCriteria('alice'));
    }

    public function testGetOwnerCriteriaFallsBackOnGroupException(): void
    {
        $groups = $this->createMock(Horde_Group_Base::class);
        $groups->method('listGroups')->willThrowException(new Horde_Group_Exception('No backend'));

        $service = $this->buildService(groups: $groups);

        $this->assertSame(['user:alice'], $service->getOwnerCriteria('alice'));
    }

    public function testOwnerCriteriaHandlesGroupException(): void
    {
        $tickets = $this->createMock(TicketRepositoryInterface::class);
        $queues = $this->createMock(QueueRepositoryInterface::class);
        $permissions = $this->createMock(PermissionChecker::class);
        $groups = $this->createMock(Horde_Group_Base::class);

        $this->mockQueuesAndPermissions($queues, $permissions, [1 => 'Q1'], [1 => 'Q1']);
        $groups->method('listGroups')->willThrowException(new Horde_Group_Exception('No backend'));

        $tickets->expects($this->once())
            ->method('findByProperties')
            ->with($this->callback(function (array $criteria): bool {
                return $criteria['owner'] === ['user:alice'];
            }))
            ->willReturn([]);

        $service = $this->buildService($tickets, $queues, $permissions, $groups);
        $service->getMyTickets('alice');
    }
}
