<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain\Repository;

use Horde\Whups\Domain\AttributeDefinition;
use Horde\Whups\Domain\Repository\DriverAttributeRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Whups_Driver;
use Whups_Driver_Sql;

#[CoversClass(DriverAttributeRepository::class)]
class DriverAttributeRepositoryTest extends TestCase
{
    private function mockDriver(): Whups_Driver
    {
        return $this->createMock(Whups_Driver_Sql::class);
    }

    public function testGetAttributesForTypeReturnsValueObjects(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getAttributesForType')->with(5)->willReturn([
            42 => [
                'human_name' => 'Priority Level',
                'type' => 'enum',
                'required' => true,
                'readonly' => false,
                'desc' => 'Select a priority',
                'params' => ['values' => ['low', 'high']],
            ],
            43 => [
                'human_name' => 'Notes',
                'type' => 'text',
                'required' => false,
                'readonly' => false,
                'desc' => '',
                'params' => [],
            ],
        ]);

        $repo = new DriverAttributeRepository($driver);
        $attrs = $repo->getAttributesForType(5);

        $this->assertCount(2, $attrs);
        $this->assertArrayHasKey(42, $attrs);
        $this->assertArrayHasKey(43, $attrs);

        $this->assertInstanceOf(AttributeDefinition::class, $attrs[42]);
        $this->assertSame(42, $attrs[42]->id);
        $this->assertSame('Priority Level', $attrs[42]->name);
        $this->assertSame('enum', $attrs[42]->type);
        $this->assertTrue($attrs[42]->required);

        $this->assertInstanceOf(AttributeDefinition::class, $attrs[43]);
        $this->assertSame(43, $attrs[43]->id);
        $this->assertSame('Notes', $attrs[43]->name);
        $this->assertFalse($attrs[43]->required);
    }

    public function testEmptyResultReturnsEmptyArray(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getAttributesForType')->with(99)->willReturn([]);

        $repo = new DriverAttributeRepository($driver);

        $this->assertSame([], $repo->getAttributesForType(99));
    }
}
