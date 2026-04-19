<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Service;

use Horde\Whups\Domain\PriorityRepositoryInterface;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Domain\TypeRepositoryInterface;
use Horde\Whups\Service\TicketCreationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Whups_Exception;

#[CoversClass(TicketCreationService::class)]
class TicketCreationServiceTest extends TestCase
{
    private function makeService(
        ?TypeRepositoryInterface $types = null,
        ?StateRepositoryInterface $states = null,
        ?PriorityRepositoryInterface $priorities = null,
    ): TicketCreationService {
        return new TicketCreationService(
            $types ?? $this->createStub(TypeRepositoryInterface::class),
            $states ?? $this->createStub(StateRepositoryInterface::class),
            $priorities ?? $this->createStub(PriorityRepositoryInterface::class),
        );
    }

    /**
     * Invoke the private resolveDefaults() method, preserving by-reference semantics.
     */
    private function callResolveDefaults(TicketCreationService $service, array &$info): void
    {
        $method = new ReflectionMethod($service, 'resolveDefaults');
        $args = [&$info];
        $method->invokeArgs($service, $args);
        $info = $args[0];
    }

    public function testResolveDefaultTypeWhenMissing(): void
    {
        $types = $this->createMock(TypeRepositoryInterface::class);
        $types->expects($this->once())
            ->method('getDefaultType')
            ->with(1)
            ->willReturn(10);

        $states = $this->createStub(StateRepositoryInterface::class);
        $states->method('getDefaultState')->willReturn(20);

        $priorities = $this->createStub(PriorityRepositoryInterface::class);
        $priorities->method('getDefaultPriority')->willReturn(30);

        $service = new TicketCreationService($types, $states, $priorities);

        $info = ['queue' => 1, 'summary' => 'Test'];
        $this->callResolveDefaults($service, $info);

        $this->assertSame(10, $info['type']);
        $this->assertSame(20, $info['state']);
        $this->assertSame(30, $info['priority']);
    }

    public function testDoesNotOverrideExplicitType(): void
    {
        $types = $this->createMock(TypeRepositoryInterface::class);
        $types->expects($this->never())->method('getDefaultType');

        $states = $this->createStub(StateRepositoryInterface::class);
        $states->method('getDefaultState')->willReturn(20);

        $priorities = $this->createStub(PriorityRepositoryInterface::class);
        $priorities->method('getDefaultPriority')->willReturn(30);

        $service = new TicketCreationService($types, $states, $priorities);

        $info = ['queue' => 1, 'type' => 5, 'summary' => 'Test'];
        $this->callResolveDefaults($service, $info);

        $this->assertSame(5, $info['type']);
    }

    public function testThrowsWhenNoDefaultType(): void
    {
        $types = $this->createStub(TypeRepositoryInterface::class);
        $types->method('getDefaultType')->willReturn(null);

        $service = $this->makeService(types: $types);

        $info = ['queue' => 1, 'summary' => 'Test'];

        $this->expectException(Whups_Exception::class);
        $this->expectExceptionMessage('No type');
        $this->callResolveDefaults($service, $info);
    }

    public function testThrowsWhenNoDefaultState(): void
    {
        $types = $this->createStub(TypeRepositoryInterface::class);
        $types->method('getDefaultType')->willReturn(10);

        $states = $this->createStub(StateRepositoryInterface::class);
        $states->method('getDefaultState')->willReturn(null);

        $service = $this->makeService(types: $types, states: $states);

        $info = ['queue' => 1, 'summary' => 'Test'];

        $this->expectException(Whups_Exception::class);
        $this->expectExceptionMessage('No state');
        $this->callResolveDefaults($service, $info);
    }

    public function testThrowsWhenNoDefaultPriority(): void
    {
        $types = $this->createStub(TypeRepositoryInterface::class);
        $types->method('getDefaultType')->willReturn(10);

        $states = $this->createStub(StateRepositoryInterface::class);
        $states->method('getDefaultState')->willReturn(20);

        $priorities = $this->createStub(PriorityRepositoryInterface::class);
        $priorities->method('getDefaultPriority')->willReturn(null);

        $service = $this->makeService(
            types: $types,
            states: $states,
            priorities: $priorities,
        );

        $info = ['queue' => 1, 'summary' => 'Test'];

        $this->expectException(Whups_Exception::class);
        $this->expectExceptionMessage('No priority');
        $this->callResolveDefaults($service, $info);
    }

    public function testAllDefaultsProvided(): void
    {
        $service = $this->makeService();

        $info = ['queue' => 1, 'type' => 5, 'state' => 10, 'priority' => 20, 'summary' => 'Test'];
        $this->callResolveDefaults($service, $info);

        $this->assertSame(5, $info['type']);
        $this->assertSame(10, $info['state']);
        $this->assertSame(20, $info['priority']);
    }
}
