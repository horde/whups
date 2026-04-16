<?php

declare(strict_types=1);

/**
 * Injectable ticket list sorting and column definitions.
 *
 * Replaces the static Whups::sortTickets() and
 * Whups::getSearchResultColumns() methods.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Service;

use Horde\Core\Service\PrefsService;
use Horde_Registry;
use Horde_String;

class TicketSorter
{
    public function __construct(
        private readonly PrefsService $prefs,
        private readonly Horde_Registry $registry,
    ) {}

    /**
     * Sort an array of tickets in place.
     *
     * @param array       $tickets  The tickets to sort (modified in place).
     * @param string|array|null $by  Sort field(s). Null = user preference.
     * @param int|array|null    $dir Sort direction(s). Null = user preference.
     */
    public function sort(array &$tickets, string|array|null $by = null, int|array|null $dir = null): void
    {
        $uid = $this->registry->getAuth() ?: '';

        if ($by === null) {
            $by = $this->prefs->getValue($uid, 'whups', 'sortby');
        }
        if ($dir === null) {
            $dir = $this->prefs->getValue($uid, 'whups', 'sortdir');
        }

        $sortBy = $by;
        $sortDir = $dir;

        $tickets = array_map(
            static function (array $ticket) use ($sortBy): array {
                return self::prepareSort($ticket, $sortBy);
            },
            $tickets,
        );

        usort($tickets, static function (array $a, array $b) use ($sortBy, $sortDir): int {
            return self::compare($a, $b, $sortBy, $sortDir);
        });
    }

    /**
     * Return the available search-result column definitions.
     *
     * @param string|null $searchType  Pass 'block' to filter to a subset.
     * @param array|null  $columns     Column keys to include (for 'block').
     *
     * @return array<string, string>  Label => field name.
     */
    public static function getSearchResultColumns(
        ?string $searchType = null,
        ?array $columns = null,
    ): array {
        $all = [
            _("Id")        => 'id',
            _("Summary")   => 'summary',
            _("State")     => 'state_name',
            _("Type")      => 'type_name',
            _("Priority")  => 'priority_name',
            _("Queue")     => 'queue_name',
            _("Requester") => 'user_id_requester',
            _("Owners")    => 'owners',
            _("Created")   => 'timestamp',
            _("Updated")   => 'date_updated',
            _("Assigned")  => 'date_assigned',
            _("Due")       => 'due',
            _("Resolved")  => 'date_resolved',
        ];

        if ($searchType !== 'block') {
            return $all;
        }

        if ($columns === null) {
            $columns = ['summary', 'priority_name', 'state_name'];
        }

        $result = [_("Id") => 'id'];
        foreach ($columns as $param) {
            if (($label = array_search($param, $all)) !== false) {
                $result[$label] = $param;
            }
        }

        return $result;
    }

    /**
     * Prepare a ticket for sorting by adding normalised sort_by keys.
     */
    private static function prepareSort(array $ticket, string|array|null $by): array
    {
        $ticket['sort_by'] = [];

        if (is_array($by)) {
            foreach ($by as $field) {
                if (!isset($ticket[$field])) {
                    $ticket['sort_by'][$field] = '';
                } else {
                    $ticket['sort_by'][$field] = Horde_String::lower($ticket[$field], true, 'UTF-8');
                }
            }
        } elseif ($by !== null) {
            if (!isset($ticket[$by])) {
                $ticket['sort_by'][$by] = '';
            } elseif (is_array($ticket[$by])) {
                natcasesort($ticket[$by]);
                $ticket['sort_by'][$by] = implode('', $ticket[$by]);
            } else {
                $ticket['sort_by'][$by] = Horde_String::lower($ticket[$by], true, 'UTF-8');
            }
        }

        return $ticket;
    }

    /**
     * Compare two tickets for usort().
     */
    private static function compare(
        array $a,
        array $b,
        string|array|null $sortby = null,
        int|array|null $sortdir = null,
    ): int {
        if (is_array($sortby)) {
            if (!count($sortby)) {
                return 0;
            }

            $field = $sortby[0];
            $aVal = $a['sort_by'][$field] ?? null;
            $bVal = $b['sort_by'][$field] ?? null;

            if ($aVal > $bVal) {
                return $sortdir[0] ? -1 : 1;
            }
            if ($aVal === $bVal) {
                array_shift($sortby);
                array_shift($sortdir);
                return self::compare($a, $b, $sortby, $sortdir);
            }
            return $sortdir[0] ? 1 : -1;
        }

        $aVal = $a['sort_by'][$sortby] ?? null;
        $bVal = $b['sort_by'][$sortby] ?? null;

        if ($aVal === $bVal) {
            return 0;
        }

        if ((is_numeric($aVal) || $aVal === null)
            && (is_numeric($bVal) || $bVal === null)
        ) {
            return (int) ($sortdir ? ($bVal > $aVal) : ($aVal > $bVal));
        }

        if (is_array($aVal) || is_array($bVal)) {
            $aVal = implode('', (array) $aVal);
            $bVal = implode('', (array) $bVal);
        }

        return $sortdir ? strcoll($bVal, $aVal) : strcoll($aVal, $bVal);
    }
}
