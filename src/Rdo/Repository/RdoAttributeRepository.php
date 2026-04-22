<?php

declare(strict_types=1);

/**
 * Rdo-backed attribute definition repository.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\FieldType;
use Horde\Rdo\SqlRepository;
use Horde\Rdo\TypeSchema;
use Horde\Whups\Domain\AttributeDefinition;
use Horde\Whups\Domain\AttributeRepositoryInterface;

final class RdoAttributeRepository implements AttributeRepositoryInterface
{
    private SqlRepository $repo;

    public function __construct(
        private readonly Adapter $db,
    ) {
        $schema = (new TypeSchema(AttributeDefinition::class, 'whups_attributes_desc'))
            ->id('id', FieldType::INT, column: 'attribute_id')
            ->field('name', FieldType::STRING, column: 'attribute_name')
            ->field('description', FieldType::STRING, column: 'attribute_description')
            ->field('type', FieldType::STRING, column: 'attribute_type')
            ->field('params', FieldType::SERIALIZED, column: 'attribute_params')
            ->field('required', FieldType::BOOL, column: 'attribute_required');

        $this->repo = new SqlRepository($this->db, $schema, new DefaultHydrator());
    }

    public function getAttributesForType(int $typeId): array
    {
        $rows = $this->repo->findRaw(
            'SELECT attribute_id, attribute_name, attribute_description,'
            . ' attribute_type, attribute_params, attribute_required'
            . ' FROM whups_attributes_desc WHERE type_id = ?',
            [$typeId],
        );

        $result = [];
        foreach ($rows as $attr) {
            $result[$attr->id] = $attr;
        }

        return $result;
    }
}
