<?php

declare(strict_types=1);

/**
 * Sends reminder emails for open tickets.
 *
 * Extracted from Whups::sendReminders() to use dependency injection
 * instead of global state.
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
use Horde\Date\Format as DateFormat;
use Horde_Registry;
use Horde_View;
use Whups_Driver;
use Whups_Exception;

class ReminderSender
{
    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly TicketSorter $sorter,
        private readonly UrlGenerator $urlGenerator,
        private readonly PrefsService $prefs,
        private readonly Horde_Registry $registry,
    ) {}

    /**
     * Send reminder emails, one per ticket owner.
     *
     * @param array $criteria  Selection criteria:
     *   - 'id' (int|string): ticket ID(s) (intlist), OR
     *   - 'queue' (int): queue ID, with optional:
     *     - 'category' (array): states to include
     *   - 'unassigned' (string): email for ownerless tickets
     *
     * @throws Whups_Exception  If no criteria or no matching tickets.
     */
    public function send(array $criteria): void
    {
        if (!empty($criteria['id'])) {
            $info = ['id' => $criteria['id']];
        } elseif (!empty($criteria['queue'])) {
            $info = ['queue' => $criteria['queue']];
            if (!empty($criteria['category'])) {
                $info['category'] = $criteria['category'];
            } else {
                $info['category'] = ['unconfirmed', 'new', 'assigned'];
            }
        } else {
            throw new Whups_Exception(
                _("You must select at least one queue to send reminders for.")
            );
        }

        $tickets = $this->driver->getTicketsByProperties($info);
        $this->sorter->sort($tickets);

        if (!count($tickets)) {
            throw new Whups_Exception(
                _("No tickets matched your search criteria.")
            );
        }

        $unassigned = $criteria['unassigned'] ?? '';
        $remind = [];
        foreach ($tickets as $ticket) {
            $ticket['link'] = $this->urlGenerator->absoluteUrlFor(
                'TicketView',
                ['id' => (int) $ticket['id']],
            );
            $owners = $this->driver->getOwners($ticket['id']);
            if (!empty($owners)) {
                foreach (reset($owners) as $owner) {
                    $remind[$owner][] = $ticket;
                }
            } elseif (!empty($unassigned)) {
                $remind['**' . $unassigned][] = $ticket;
            }
        }

        $view = new Horde_View(['templatePath' => WHUPS_BASE . '/config']);

        $uid = $this->registry->getAuth() ?: '';
        $dateFormat = $this->prefs->getValue($uid, 'whups', 'date_format');
        $view->date = DateFormat::formatDate(time(), $dateFormat);

        $messageFile = WHUPS_BASE . '/config/reminder_email.plain';
        if (file_exists($messageFile . '.local.php')) {
            $messageFile .= '.local.php';
        } else {
            $messageFile .= '.php';
        }
        $messageFile = basename($messageFile);

        foreach ($remind as $user => $utickets) {
            if (empty($user) || !count($utickets)) {
                continue;
            }
            $view->tickets = $utickets;
            $this->driver->mail([
                'recipients' => [$user => 'owner'],
                'subject' => _("Reminder: Your open tickets"),
                'view' => $view,
                'template' => $messageFile,
                'from' => $user,
            ]);
        }
    }
}
