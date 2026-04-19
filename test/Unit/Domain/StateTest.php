<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain;

use Horde\Whups\Domain\State;
use Horde\Whups\Domain\StateCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(State::class)]
class StateTest extends TestCase
{
    public function testFromDriverArray(): void
    {
        $state = State::fromDriverArray([
            'id' => 3,
            'name' => 'In Progress',
            'description' => 'Being worked on',
            'category' => 'assigned',
            'type' => 5,
        ]);

        $this->assertSame(3, $state->id);
        $this->assertSame('In Progress', $state->name);
        $this->assertSame('Being worked on', $state->description);
        $this->assertSame(StateCategory::Assigned, $state->category);
        $this->assertSame(5, $state->typeId);
    }

    public function testCategoryConvenienceMethods(): void
    {
        $unconfirmed = State::fromDriverArray([
            'id' => 1, 'name' => '', 'description' => '',
            'category' => 'unconfirmed', 'type' => 1,
        ]);
        $new = State::fromDriverArray([
            'id' => 2, 'name' => '', 'description' => '',
            'category' => 'new', 'type' => 1,
        ]);
        $assigned = State::fromDriverArray([
            'id' => 3, 'name' => '', 'description' => '',
            'category' => 'assigned', 'type' => 1,
        ]);
        $resolved = State::fromDriverArray([
            'id' => 4, 'name' => '', 'description' => '',
            'category' => 'resolved', 'type' => 1,
        ]);

        $this->assertTrue($unconfirmed->isUnconfirmed());
        $this->assertFalse($unconfirmed->isAssigned());

        $this->assertTrue($new->isNew());
        $this->assertFalse($new->isResolved());

        $this->assertTrue($assigned->isAssigned());
        $this->assertFalse($assigned->isNew());

        $this->assertTrue($resolved->isResolved());
        $this->assertFalse($resolved->isUnconfirmed());
    }

    public function testInvalidCategoryThrows(): void
    {
        $this->expectException(ValueError::class);

        State::fromDriverArray([
            'id' => 1, 'name' => '', 'description' => '',
            'category' => 'invalid', 'type' => 1,
        ]);
    }
}
