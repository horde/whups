<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain;

use Horde\Whups\Domain\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Queue::class)]
class QueueTest extends TestCase
{
    public function testFromDriverArray(): void
    {
        $queue = Queue::fromDriverArray([
            'id' => 7,
            'name' => 'Support',
            'description' => 'General support queue',
            'versioned' => 1,
            'slug' => 'support',
            'email' => 'support@example.com',
            'readonly' => false,
        ]);

        $this->assertSame(7, $queue->id);
        $this->assertSame('Support', $queue->name);
        $this->assertSame('General support queue', $queue->description);
        $this->assertTrue($queue->versioned);
        $this->assertSame('support', $queue->slug);
        $this->assertSame('support@example.com', $queue->email);
        $this->assertFalse($queue->readonly);
    }

    public function testFromDriverArrayWithMissingOptionalFields(): void
    {
        $queue = Queue::fromDriverArray([
            'id' => 1,
        ]);

        $this->assertSame(1, $queue->id);
        $this->assertSame('', $queue->name);
        $this->assertSame('', $queue->description);
        $this->assertFalse($queue->versioned);
        $this->assertSame('', $queue->slug);
        $this->assertSame('', $queue->email);
        $this->assertFalse($queue->readonly);
    }

    public function testVersionedCastsTruthy(): void
    {
        $queue = Queue::fromDriverArray([
            'id' => 1,
            'versioned' => '1',
        ]);

        $this->assertTrue($queue->versioned);
    }
}
