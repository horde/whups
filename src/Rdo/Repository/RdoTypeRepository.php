<?php

declare(strict_types=1);

/**
 * Rdo-backed ticket type repository.
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
use Horde\Whups\Domain\TicketType;
use Horde\Whups\Domain\TypeRepositoryInterface;

final class RdoTypeRepository implements TypeRepositoryInterface
{
    private SqlRepository $repo;
    private TypeSchema $schema;

    public function __construct(
        private readonly Adapter $db,
    ) {
        $this->schema = (new TypeSchema(TicketType::class, 'whups_types'))
            ->id('id', FieldType::INT, column: 'type_id')
            ->field('name', FieldType::STRING, column: 'type_name')
            ->field('description', FieldType::STRING, column: 'type_description');

        $this->repo = new SqlRepository($this->db, $this->schema, new DefaultHydrator());
    }

    public function getTypesForQueue(int $queueId): array
    {
        return $this->db->selectAssoc(
            'SELECT t.type_id, t.type_name'
            . ' FROM whups_types t'
            . ' INNER JOIN whups_types_queues tq ON t.type_id = tq.type_id'
            . ' WHERE tq.queue_id = ?',
            [$queueId],
        );
    }

    public function getDefaultType(int $queueId): ?int
    {
        $value = $this->db->selectValue(
            'SELECT t.type_id'
            . ' FROM whups_types t'
            . ' INNER JOIN whups_types_queues tq ON t.type_id = tq.type_id'
            . ' WHERE tq.queue_id = ? AND tq.type_default = 1',
            [$queueId],
        );

        return $value !== false && $value !== null ? (int) $value : null;
    }
}
