<?php

declare(strict_types=1);

/**
 * Rdo-backed queue repository.
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
use Horde\Whups\Domain\Queue;
use Horde\Whups\Domain\QueueRepositoryInterface;
use RuntimeException;

final class RdoQueueRepository implements QueueRepositoryInterface
{
    private SqlRepository $repo;

    public function __construct(
        private readonly Adapter $db,
    ) {
        $schema = (new TypeSchema(Queue::class, 'whups_queues'))
            ->id('id', FieldType::INT, column: 'queue_id')
            ->field('name', FieldType::STRING, column: 'queue_name')
            ->field('description', FieldType::STRING, column: 'queue_description')
            ->field('versioned', FieldType::BOOL, column: 'queue_versioned')
            ->field('slug', FieldType::STRING, column: 'queue_slug')
            ->field('email', FieldType::STRING, column: 'queue_email');

        $this->repo = new SqlRepository($this->db, $schema, new DefaultHydrator());
    }

    public function listQueues(): array
    {
        return $this->db->selectAssoc(
            'SELECT queue_id, queue_name FROM whups_queues ORDER BY queue_name',
        );
    }

    public function getQueue(int $id): Queue
    {
        $queue = $this->repo->findOne($id);
        if ($queue === null) {
            throw new RuntimeException('Queue not found: ' . $id);
        }

        return $queue;
    }

    public function getQueueUsers(int $queueId): array
    {
        return $this->db->selectValues(
            'SELECT user_uid FROM whups_queues_users WHERE queue_id = ?',
            [$queueId],
        );
    }

    public function getQueueSummary(array $queueIds): array
    {
        if (empty($queueIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($queueIds), '?'));

        $sql = 'SELECT q.queue_id AS id, q.queue_slug AS slug,'
            . ' q.queue_name AS name, q.queue_description AS description,'
            . ' t.type_name AS type,'
            . ' COUNT(tk.ticket_id) AS open_tickets'
            . ' FROM whups_queues q'
            . ' INNER JOIN whups_types_queues tq ON q.queue_id = tq.queue_id'
            . ' INNER JOIN whups_types t ON tq.type_id = t.type_id'
            . ' LEFT JOIN whups_tickets tk ON tk.queue_id = q.queue_id'
            . '   AND tk.type_id = t.type_id'
            . ' LEFT JOIN whups_states s ON tk.state_id = s.state_id'
            . '   AND s.state_category != ?'
            . ' WHERE q.queue_id IN (' . $placeholders . ')'
            . ' GROUP BY q.queue_id, q.queue_slug, q.queue_name,'
            . '   q.queue_description, t.type_name'
            . ' ORDER BY q.queue_name, t.type_name';

        $params = array_merge(['resolved'], $queueIds);

        return $this->db->selectAll($sql, $params);
    }
}
