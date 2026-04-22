<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Delete an attachment or original message from a ticket.
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

use Horde;
use Horde\Core\Service\PrefsService;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde_Notification_Handler;
use Horde_Perms;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;

class DeleteAttachmentController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_Registry $registry,
        private readonly PrefsService $prefs,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $id = (int) ($route['id'] ?? 0);
        $queryParams = $request->getQueryParams();

        $uid = $this->registry->getAuth() ?: '';

        $details = $this->driver->getTicketDetails($id);
        $ticket = new Whups_Ticket($id, $details);

        if (!$this->permissions->hasQueuePermission($ticket->get('queue'), Horde_Perms::DELETE)) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($uid);
        }

        $file = basename($queryParams['file'] ?? '');
        if ($file) {
            $ticket->change('delete-attachment', $file);
        } else {
            $ticket->change('delete-message', (int) ($queryParams['message'] ?? 0));
        }

        try {
            $ticket->commit();
            if ($file) {
                $this->notification->push(
                    sprintf(_("Attachment %s deleted."), $file),
                    'horde.success',
                );
            } else {
                $this->notification->push(_("Original message deleted."), 'horde.success');
            }
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
        }

        $returnUrl = $queryParams['url'] ?? null;
        if ($returnUrl && Horde::verifySignedUrl($returnUrl)) {
            return $this->redirect($returnUrl);
        }

        return $this->redirectToDefault($uid);
    }

    private function redirectToDefault(string $uid): ResponseInterface
    {
        $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
        return $this->redirect($this->urlGenerator->defaultViewUrl($defaultView));
    }
}
