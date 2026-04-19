<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain;

use Horde\Whups\Domain\StateCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateCategory::class)]
class StateCategoryTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = StateCategory::cases();

        $this->assertCount(4, $cases);
        $this->assertSame('unconfirmed', StateCategory::Unconfirmed->value);
        $this->assertSame('new', StateCategory::New->value);
        $this->assertSame('assigned', StateCategory::Assigned->value);
        $this->assertSame('resolved', StateCategory::Resolved->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(StateCategory::Assigned, StateCategory::from('assigned'));
        $this->assertSame(StateCategory::Resolved, StateCategory::from('resolved'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(StateCategory::tryFrom('bogus'));
    }
}
