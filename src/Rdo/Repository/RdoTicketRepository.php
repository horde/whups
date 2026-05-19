<?php

declare(strict_types=1);

/**
 * Rdo-backed ticket repository.
 *
 * Implements the TicketRepositoryInterface using direct SQL queries
 * against the Horde\Db\Adapter. The query-building logic mirrors the
 * core patterns from Whups_Driver_Sql::getTicketsByProperties() but
 * leaves munging (name resolution, formatting) to the service layer.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Whups\Domain\TicketRepositoryInterface;

final class RdoTicketRepository implements TicketRepositoryInterface
{
    public function __construct(
        private readonly Adapter $db,
    ) {}

    public function findByProperties(array $criteria): array
    {
        if (isset($criteria['queue']) && is_array($criteria['queue']) && count($criteria['queue']) === 0) {
            return [];
        }

        $where = [];
        $params = [];

        $this->addIntegerFilters($where, $params, $criteria);
        $this->addRequesterFilter($where, $params, $criteria);
        $this->addSummaryFilter($where, $params, $criteria);
        $this->addDateFilters($where, $params, $criteria);

        $joins = '';
        $groupBy = $this->baseGroupBy();

        $this->addStateCategoryFilters($where, $params, $criteria, $joins, $groupBy);
        $this->addOwnerFilters($where, $params, $criteria, $joins, $groupBy);

        $fields = $this->baseFields();

        // Join related tables for name resolution.
        $joins .= ' INNER JOIN whups_types ON whups_tickets.type_id = whups_types.type_id'
            . ' INNER JOIN whups_states ON whups_tickets.state_id = whups_states.state_id'
            . ' INNER JOIN whups_priorities ON whups_tickets.priority_id = whups_priorities.priority_id'
            . ' INNER JOIN whups_queues ON whups_tickets.queue_id = whups_queues.queue_id'
            . ' LEFT JOIN whups_versions ON whups_tickets.version_id = whups_versions.version_id';

        $fields .= ', whups_types.type_name AS type_name'
            . ', whups_states.state_name AS state_name'
            . ', whups_states.state_category AS state_category'
            . ', whups_priorities.priority_name AS priority_name'
            . ', whups_queues.queue_name AS queue_name'
            . ', whups_versions.version_name AS version_name';

        $groupBy .= ', whups_types.type_name, whups_states.state_name'
            . ', whups_states.state_category, whups_priorities.priority_name'
            . ', whups_queues.queue_name, whups_versions.version_name';

        $sql = 'SELECT ' . $fields . ' FROM whups_tickets' . $joins;
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY ' . $groupBy;

        $rows = $this->db->selectAll($sql, $params);

        $tickets = [];
        foreach ($rows as $row) {
            $tickets[$row['id']] = $row;
        }

        return array_values($tickets);
    }

    private function baseFields(): string
    {
        return 'whups_tickets.ticket_id AS id'
            . ', whups_tickets.ticket_summary AS summary'
            . ', whups_tickets.user_id_requester'
            . ', whups_tickets.state_id AS state'
            . ', whups_tickets.type_id AS type'
            . ', whups_tickets.priority_id AS priority'
            . ', whups_tickets.queue_id AS queue'
            . ', whups_tickets.version_id AS version'
            . ', whups_tickets.ticket_timestamp AS timestamp'
            . ', whups_tickets.ticket_due AS due'
            . ', whups_tickets.date_updated'
            . ', whups_tickets.date_assigned'
            . ', whups_tickets.date_resolved';
    }

    private function baseGroupBy(): string
    {
        return 'whups_tickets.ticket_id, whups_tickets.ticket_summary'
            . ', whups_tickets.user_id_requester, whups_tickets.state_id'
            . ', whups_tickets.type_id, whups_tickets.priority_id'
            . ', whups_tickets.queue_id, whups_tickets.version_id'
            . ', whups_tickets.ticket_timestamp, whups_tickets.ticket_due'
            . ', whups_tickets.date_updated, whups_tickets.date_assigned'
            . ', whups_tickets.date_resolved';
    }

    private function addIntegerFilters(array &$where, array &$params, array $criteria): void
    {
        $columns = [
            'ticket_id' => 'whups_tickets.ticket_id',
            'type_id' => 'whups_tickets.type_id',
            'state_id' => 'whups_tickets.state_id',
            'priority_id' => 'whups_tickets.priority_id',
            'queue' => 'whups_tickets.queue_id',
            'queue_id' => 'whups_tickets.queue_id',
        ];

        foreach ($columns as $key => $column) {
            if (!isset($criteria[$key])) {
                continue;
            }

            $value = $criteria[$key];
            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $where[] = $column . ' IN (' . $placeholders . ')';
                foreach ($value as $v) {
                    $params[] = (int) $v;
                }
            } else {
                $where[] = $column . ' = ?';
                $params[] = (int) $value;
            }
        }
    }

    private function addRequesterFilter(array &$where, array &$params, array $criteria): void
    {
        $value = $criteria['requester'] ?? $criteria['user_id_requester'] ?? null;
        if ($value === null) {
            return;
        }
        if (is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            $where[] = 'whups_tickets.user_id_requester IN (' . $placeholders . ')';
            $params = array_merge($params, $value);
        } else {
            $where[] = 'whups_tickets.user_id_requester = ?';
            $params[] = $value;
        }
    }

    private function addSummaryFilter(array &$where, array &$params, array $criteria): void
    {
        if (empty($criteria['summary'])) {
            return;
        }

        $where[] = 'LOWER(whups_tickets.ticket_summary) LIKE ?';
        $params[] = '%' . mb_strtolower($criteria['summary']) . '%';
    }

    private function addDateFilters(array &$where, array &$params, array $criteria): void
    {
        $dateColumns = [
            'ticket_timestamp' => 'whups_tickets.ticket_timestamp',
            'date_updated' => 'whups_tickets.date_updated',
            'date_assigned' => 'whups_tickets.date_assigned',
            'date_resolved' => 'whups_tickets.date_resolved',
            'ticket_due' => 'whups_tickets.ticket_due',
        ];

        foreach ($dateColumns as $key => $column) {
            if (empty($criteria[$key])) {
                continue;
            }

            $dateInfo = $criteria[$key];
            if (is_array($dateInfo)) {
                if (!empty($dateInfo['from'])) {
                    $where[] = $column . ' >= ?';
                    $params[] = (int) $dateInfo['from'];
                }
                if (!empty($dateInfo['to'])) {
                    $where[] = $column . ' <= ?';
                    $params[] = (int) $dateInfo['to'];
                }
            }
        }
    }

    private function addStateCategoryFilters(
        array &$where,
        array &$params,
        array $criteria,
        string &$joins,
        string &$groupBy,
    ): void {
        $categoryConditions = [];

        // Include categories.
        if (isset($criteria['category'])) {
            $cats = is_array($criteria['category']) ? $criteria['category'] : [$criteria['category']];
            $placeholders = implode(', ', array_fill(0, count($cats), '?'));
            $categoryConditions[] = 'whups_states.state_category IN (' . $placeholders . ')';
            $params = array_merge($params, $cats);
        }

        // Exclude categories.
        $excludeMap = [
            'nores' => 'resolved',
            'nouc' => 'unconfirmed',
            'nonew' => 'new',
            'noass' => 'assigned',
        ];
        foreach ($excludeMap as $key => $category) {
            if (isset($criteria[$key])) {
                $categoryConditions[] = 'whups_states.state_category != ?';
                $params[] = $category;
            }
        }

        // Include specific categories.
        $includeMap = [
            'res' => 'resolved',
            'uc' => 'unconfirmed',
            'new' => 'new',
            'ass' => 'assigned',
        ];
        foreach ($includeMap as $key => $category) {
            if (isset($criteria[$key])) {
                $categoryConditions[] = 'whups_states.state_category = ?';
                $params[] = $category;
            }
        }

        if (!empty($categoryConditions)) {
            $where[] = 'whups_tickets.type_id = whups_states.type_id'
                . ' AND whups_tickets.state_id = whups_states.state_id'
                . ' AND ' . implode(' AND ', $categoryConditions);
        }
    }

    private function addOwnerFilters(
        array &$where,
        array &$params,
        array $criteria,
        string &$joins,
        string &$groupBy,
    ): void {
        if (isset($criteria['owner'])) {
            $owners = is_array($criteria['owner']) ? $criteria['owner'] : [$criteria['owner']];
            $placeholders = implode(', ', array_fill(0, count($owners), '?'));
            $joins .= ' INNER JOIN whups_ticket_owners ON whups_tickets.ticket_id = whups_ticket_owners.ticket_id'
                . ' AND whups_ticket_owners.ticket_owner IN (' . $placeholders . ')';
            $params = array_merge($params, $owners);
        }

        if (isset($criteria['notowner'])) {
            if ($criteria['notowner'] === true) {
                $joins .= ' LEFT JOIN whups_ticket_owners ON whups_tickets.ticket_id = whups_ticket_owners.ticket_id';
                $where[] = 'whups_ticket_owners.ticket_id IS NULL';
            } else {
                $joins .= ' LEFT JOIN whups_ticket_owners ON whups_tickets.ticket_id = whups_ticket_owners.ticket_id'
                    . ' AND whups_ticket_owners.ticket_owner = ?';
                $params[] = $criteria['notowner'];
                $where[] = 'whups_ticket_owners.ticket_id IS NULL';
            }
        }
    }
}
