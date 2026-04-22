<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Rdo;

use Horde\Rdo\FieldType;
use Horde\Rdo\Hydrator;
use Horde\Rdo\TypeSchema;
use Horde\Whups\Domain\State;
use Horde\Whups\Domain\StateCategory;
use Horde\Whups\Domain\Version;
use Horde\Whups\Rdo\EnumAwareHydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumAwareHydrator::class)]
class EnumAwareHydratorTest extends TestCase
{
    private function stateSchema(): TypeSchema
    {
        return (new TypeSchema(State::class, 'whups_states'))
            ->id('id', FieldType::INT, column: 'state_id')
            ->field('name', FieldType::STRING, column: 'state_name')
            ->field('description', FieldType::STRING, column: 'state_description')
            ->field('category', FieldType::STRING, column: 'state_category')
            ->field('typeId', FieldType::INT, column: 'type_id');
    }

    private function versionSchema(): TypeSchema
    {
        return (new TypeSchema(Version::class, 'whups_versions'))
            ->id('id', FieldType::INT, column: 'version_id')
            ->field('name', FieldType::STRING, column: 'version_name')
            ->field('description', FieldType::STRING, column: 'version_description')
            ->field('active', FieldType::BOOL, column: 'version_active');
    }

    public function testHydratesBackedEnumFromString(): void
    {
        $hydrator = new EnumAwareHydrator();
        $data = [
            'state_id' => '7',
            'state_name' => 'Resolved',
            'state_description' => 'Fixed',
            'state_category' => 'resolved',
            'type_id' => '1',
        ];

        $state = $hydrator->hydrate($data, $this->stateSchema());

        $this->assertInstanceOf(State::class, $state);
        $this->assertSame(StateCategory::Resolved, $state->category);
        $this->assertSame(7, $state->id);
        $this->assertSame('Resolved', $state->name);
    }

    public function testPassesThroughWhenNoEnums(): void
    {
        $hydrator = new EnumAwareHydrator();
        $data = [
            'version_id' => '3',
            'version_name' => 'v1.0',
            'version_description' => 'First release',
            'version_active' => '1',
        ];

        $version = $hydrator->hydrate($data, $this->versionSchema());

        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame(3, $version->id);
        $this->assertSame('v1.0', $version->name);
        $this->assertTrue($version->active);
    }

    public function testExtractsBackedEnumToValue(): void
    {
        $hydrator = new EnumAwareHydrator();
        $state = new State(
            id: 5,
            name: 'New',
            description: 'Newly filed',
            category: StateCategory::New,
            typeId: 2,
        );

        $data = $hydrator->extract($state, $this->stateSchema());

        $this->assertSame('new', $data['state_category']);
        $this->assertSame(5, $data['state_id']);
    }

    public function testDelegatesExtractToInner(): void
    {
        $inner = $this->createMock(Hydrator::class);
        $inner->expects($this->once())
            ->method('extract')
            ->willReturn([
                'version_id' => 3,
                'version_name' => 'v1.0',
                'version_description' => '',
                'version_active' => 1,
            ]);

        $hydrator = new EnumAwareHydrator($inner);
        $version = new Version(id: 3, name: 'v1.0', description: '', active: true);

        $data = $hydrator->extract($version, $this->versionSchema());

        $this->assertSame(3, $data['version_id']);
    }

    public function testHandlesEnumAlreadyHydrated(): void
    {
        $hydrator = new EnumAwareHydrator();
        $data = [
            'state_id' => '1',
            'state_name' => 'Assigned',
            'state_description' => '',
            'state_category' => StateCategory::Assigned,
            'type_id' => '1',
        ];

        $state = $hydrator->hydrate($data, $this->stateSchema());

        $this->assertSame(StateCategory::Assigned, $state->category);
    }
}
