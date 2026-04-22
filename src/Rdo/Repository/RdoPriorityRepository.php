<?php

declare(strict_types=1);

/**
 * Rdo-backed priority repository.
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
use Horde\Whups\Domain\Priority;
use Horde\Whups\Domain\PriorityRepositoryInterface;

final class RdoPriorityRepository implements PriorityRepositoryInterface
{
    private SqlRepository $repo;
    private TypeSchema $schema;

    public function __construct(
        private readonly Adapter $db,
    ) {
        $this->schema = (new TypeSchema(Priority::class, 'whups_priorities'))
            ->id('id', FieldType::INT, column: 'priority_id')
            ->field('name', FieldType::STRING, column: 'priority_name')
            ->field('description', FieldType::STRING, column: 'priority_description')
            ->field('typeId', FieldType::INT, column: 'type_id');

        $this->repo = new SqlRepository($this->db, $this->schema, new DefaultHydrator());
    }

    public function getPriorities(int $typeId): array
    {
        return $this->db->selectAssoc(
            'SELECT priority_id, priority_name FROM whups_priorities WHERE type_id = ?',
            [$typeId],
        );
    }

    public function getDefaultPriority(int $typeId): ?int
    {
        $value = $this->db->selectValue(
            'SELECT priority_id FROM whups_priorities WHERE type_id = ? AND priority_default = 1',
            [$typeId],
        );

        return $value !== false && $value !== null ? (int) $value : null;
    }
}
