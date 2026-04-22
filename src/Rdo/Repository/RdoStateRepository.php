<?php

declare(strict_types=1);

/**
 * Rdo-backed state repository.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Rdo\FieldType;
use Horde\Rdo\SqlRepository;
use Horde\Rdo\TypeSchema;
use Horde\Whups\Domain\State;
use Horde\Whups\Domain\StateCategory;
use Horde\Whups\Domain\StateRepositoryInterface;
use Horde\Whups\Rdo\EnumAwareHydrator;
use RuntimeException;

final class RdoStateRepository implements StateRepositoryInterface
{
    private SqlRepository $repo;

    public function __construct(
        private readonly Adapter $db,
    ) {
        $schema = (new TypeSchema(State::class, 'whups_states'))
            ->id('id', FieldType::INT, column: 'state_id')
            ->field('name', FieldType::STRING, column: 'state_name')
            ->field('description', FieldType::STRING, column: 'state_description')
            ->field('category', FieldType::STRING, column: 'state_category')
            ->field('typeId', FieldType::INT, column: 'type_id');

        $this->repo = new SqlRepository($this->db, $schema, new EnumAwareHydrator());
    }

    public function getStates(int $typeId, StateCategory|array|null $category = null): array
    {
        $sql = 'SELECT state_id, state_name FROM whups_states WHERE type_id = ?';
        $params = [$typeId];

        if ($category !== null) {
            $categories = is_array($category) ? $category : [$category];
            $placeholders = implode(', ', array_fill(0, count($categories), '?'));
            $sql .= ' AND state_category IN (' . $placeholders . ')';
            foreach ($categories as $cat) {
                $params[] = $cat->value;
            }
        }

        return $this->db->selectAssoc($sql, $params);
    }

    public function getState(int $id): State
    {
        $state = $this->repo->findOne($id);
        if ($state === null) {
            throw new RuntimeException('State not found: ' . $id);
        }

        return $state;
    }

    public function getDefaultState(int $typeId): ?int
    {
        $value = $this->db->selectValue(
            'SELECT state_id FROM whups_states WHERE type_id = ? AND state_default = 1',
            [$typeId],
        );

        return $value !== false && $value !== null ? (int) $value : null;
    }

    public function isCategory(StateCategory $category, int $stateId): bool
    {
        $value = $this->db->selectValue(
            'SELECT state_category FROM whups_states WHERE state_id = ?',
            [$stateId],
        );

        return $value === $category->value;
    }
}
