<?php

/**
 * Interface for drivers that support SQL-level aggregate reporting.
 *
 * Drivers implementing this interface can compute report aggregates
 * (counts, averages, min/max) directly in SQL rather than loading all
 * tickets into PHP memory.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Whups
 */
interface Whups_Driver_DriverBasedReporting
{
    /**
     * Returns aggregate time statistics for resolved tickets.
     *
     * Computes the time between ticket creation and resolution,
     * aggregated by the specified operation, optionally grouped by
     * a ticket property.
     *
     * @param string $operation  'avg', 'min', or 'max'
     * @param string $state      The time interval to measure, e.g. 'open'
     *                           (creation to resolution).
     * @param array $queueIds    Queue IDs the user has permission to view.
     * @param string|null $groupBy  Optional ticket property to group by
     *                              (e.g. 'type_name', 'queue_name').
     *
     * @return int|float|array  A single value (days), or an associative
     *                          array of group_label => days if $groupBy
     *                          is specified.
     *
     * @throws Whups_Exception
     */
    public function getAggregateTime(
        string $operation,
        string $state,
        array $queueIds,
        ?string $groupBy = null,
    ): int|float|array;

    /**
     * Returns ticket count grouped by a field.
     *
     * @param string $type      'open', 'closed', or 'all'
     * @param string $field     The ticket field to group by (e.g.
     *                          'queue_name', 'state_name', 'type_name',
     *                          'priority_name', 'user_id_requester', 'owner').
     * @param array $queueIds   Queue IDs the user has permission to view.
     *
     * @return array  Associative array of field_value => count.
     *
     * @throws Whups_Exception
     */
    public function getTicketCountByField(
        string $type,
        string $field,
        array $queueIds,
    ): array;
}
