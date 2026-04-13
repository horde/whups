<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Delete a history entry from a ticket.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Controller\Ticket;

use Horde\Whups\Controller\ResponseTrait;
use Horde_Notification_Handler;
use Horde_Perms;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;
use Horde;

class DeleteHistoryController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly string $defaultView,
        private readonly string $webroot,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $id = (int) ($route['id'] ?? 0);
        $queryParams = $request->getQueryParams();

        $ticket = Whups_Ticket::makeTicket($id);
        if (!Whups::hasPermission($ticket->get('queue'), 'queue', Horde_Perms::DELETE)) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirect($this->webroot . '/' . $this->defaultView);
        }

        $transaction = $queryParams['transaction'] ?? null;
        try {
            $this->driver->deleteHistory($transaction);
            $this->notification->push(_("Entry deleted."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
        }

        $returnUrl = $queryParams['url'] ?? null;
        if ($returnUrl && Horde::verifySignedUrl($returnUrl)) {
            return $this->redirect($returnUrl);
        }

        return $this->redirect($this->webroot . '/' . $this->defaultView);
    }
}
