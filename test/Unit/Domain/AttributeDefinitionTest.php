<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Domain;

use Horde\Whups\Domain\AttributeDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeDefinition::class)]
class AttributeDefinitionTest extends TestCase
{
    public function testFromDriverArrayWithArrayParams(): void
    {
        $attr = AttributeDefinition::fromDriverArray(42, [
            'human_name' => 'Priority Level',
            'type' => 'enum',
            'required' => true,
            'readonly' => false,
            'desc' => 'Select a priority',
            'params' => ['values' => ['low', 'medium', 'high']],
        ]);

        $this->assertSame(42, $attr->id);
        $this->assertSame('Priority Level', $attr->name);
        $this->assertSame('Select a priority', $attr->description);
        $this->assertSame('enum', $attr->type);
        $this->assertSame(['values' => ['low', 'medium', 'high']], $attr->params);
        $this->assertTrue($attr->required);
        $this->assertFalse($attr->readonly);
    }

    public function testFromDriverArrayWithSerializedParams(): void
    {
        $serialized = serialize(['key' => 'value']);

        $attr = AttributeDefinition::fromDriverArray(10, [
            'human_name' => 'Custom Field',
            'type' => 'text',
            'required' => false,
            'readonly' => false,
            'desc' => '',
            'params' => $serialized,
        ]);

        $this->assertSame(['key' => 'value'], $attr->params);
    }

    public function testFromDriverArrayWithMissingParams(): void
    {
        $attr = AttributeDefinition::fromDriverArray(1, [
            'human_name' => 'Simple',
            'type' => 'text',
            'required' => false,
            'readonly' => false,
            'desc' => '',
        ]);

        $this->assertSame([], $attr->params);
    }

    public function testIdPassedSeparately(): void
    {
        $attr = AttributeDefinition::fromDriverArray(99, [
            'human_name' => 'Test',
            'type' => 'text',
            'required' => false,
            'readonly' => false,
            'desc' => '',
        ]);

        $this->assertSame(99, $attr->id);
    }
}
