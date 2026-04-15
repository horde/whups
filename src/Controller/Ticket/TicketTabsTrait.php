<?php

declare(strict_types=1);

/**
 * Shared ticket action tabs for ticket controllers.
 *
 * Requires the consuming class to have these properties:
 *   - PermissionChecker  $this->permissions
 *   - UrlGenerator       $this->urlGenerator
 *   - Horde_Registry     $this->registry
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

namespace Horde\Whups\Controller\Ticket;

use Horde_Core_Ui_Tabs;
use Horde_Perms;
use Horde_Variables;
use Whups_Ticket;

trait TicketTabsTrait
{
    /**
     * Build ticket action tabs using injected services.
     */
    private function buildTicketTabs(Horde_Variables $vars, Whups_Ticket $ticket): Horde_Core_Ui_Tabs
    {
        $tabs = new Horde_Core_Ui_Tabs(null, $vars);
        $id = $ticket->getId();
        $queue = $ticket->get('queue');

        $tabs->addTab(
            _("_History"),
            $this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]),
            'history',
        );
        $tabs->addTab(
            _("_Attachments"),
            $this->urlGenerator->urlFor('TicketAttachments', ['id' => (int) $id]),
            'attachments',
        );

        if ($this->permissions->hasQueuePermission($queue, 'update')) {
            $tabs->addTab(
                _("_Update"),
                $this->urlGenerator->urlFor('TicketUpdate', ['id' => (int) $id]),
                'update',
            );
        } else {
            $tabs->addTab(
                _("_Comment"),
                $this->urlGenerator->urlFor('TicketComment', ['id' => (int) $id]),
                'comment',
            );
        }

        $tabs->addTab(
            _("_Watch"),
            $this->urlGenerator->urlFor('TicketWatch', ['id' => (int) $id]),
            'watch',
        );

        if ($this->permissions->hasQueuePermission($queue, Horde_Perms::DELETE)) {
            $tabs->addTab(
                _("S_et Queue"),
                $this->urlGenerator->urlFor('TicketQueue', ['id' => (int) $id]),
                'queue',
            );
        }

        if ($this->permissions->hasQueuePermission($queue, 'update')) {
            $tabs->addTab(
                _("Set _Type"),
                $this->urlGenerator->urlFor('TicketType', ['id' => (int) $id]),
                'type',
            );
        }

        if ($this->permissions->hasQueuePermission($queue, Horde_Perms::DELETE)) {
            $tabs->addTab(
                _("_Delete"),
                $this->urlGenerator->urlFor('TicketDelete', ['id' => (int) $id]),
                'delete',
            );
        }

        $tabs->addTab(
            _("Download"),
            $this->registry->downloadUrl(
                _("ticket") . $id . '.html',
                ['actionID' => 'ticket', 'id' => $id],
            ),
        );

        return $tabs;
    }
}
