<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Delete multiple tickets.
 *
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
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
use Horde\Form\V3\HtmlRenderer;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Form\Ticket\DeleteMultipleForm;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde_Exception_NotFound;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde\Core\Session\SessionAccess;
use Horde_Url;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;

class DeleteMultipleController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly SessionAccess $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly PrefsService $prefs,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uid = $this->registry->getAuth() ?: '';
        $params = ($request->getParsedBody() ?? []) + $request->getQueryParams();

        // Collect deletable tickets — filter by DELETE permission.
        $ticketIds = (array) ($params['ticket'] ?? []);
        $allowed = $this->filterDeletableTickets($ticketIds);

        $formVars = $params;
        $formVars['tickets'] = $params['tickets'] ?? serialize(array_keys($allowed));
        $formVars['url'] = $params['url'] ?? Horde::signUrl(Horde::selfUrl(true));

        $deleteForm = new DeleteMultipleForm($formVars, $allowed);

        // Handle form submission.
        if ($deleteForm->isSubmitted() && $deleteForm->validate()) {
            if ($deleteForm->getClickedButton() === _("Delete")) {
                $info = $deleteForm->getInfo();
                $tickets = @unserialize($info['tickets']);
                foreach ((array) $tickets as $id) {
                    try {
                        $details = $this->driver->getTicketDetails($id);
                        (new Whups_Ticket($id, $details))->delete();
                        $this->notification->push(
                            sprintf(_("Ticket %d has been deleted."), $id),
                            'horde.success',
                        );
                    } catch (Whups_Exception $e) {
                        $this->notification->push(
                            _("There was an error deleting the ticket:") . ' ' . $e->getMessage(),
                            'horde.error',
                        );
                    } catch (Horde_Exception_NotFound $e) {
                        $this->notification->push(sprintf(_("Ticket %d not found."), $id));
                    }
                }
            } else {
                $this->notification->push(_("The tickets were not deleted."), 'horde.message');
            }

            $url = Horde::verifySignedUrl($params['url'] ?? '');
            if (!$url) {
                $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
                $url = $this->urlGenerator->defaultViewUrl($defaultView);
            }
            return $this->redirect((new Horde_Url($url, true))->toString());
        }

        // Render the confirmation form.
        $title = sprintf(_("Delete %d tickets?"), count($allowed));

        $html = $this->renderChrome($title, function () use ($deleteForm) {
            $this->topbarSearch->apply();
            $this->notification->notify(['listeners' => 'status']);

            $renderer = new HtmlRenderer();
            echo $renderer->render($deleteForm, $this->urlGenerator->urlFor('TicketDeleteMultiple'), 'post');
        });

        return $this->htmlResponse($html);
    }

    /**
     * Filter ticket IDs to only those the user has DELETE permission on.
     *
     * @param list<int|string> $ticketIds Raw ticket IDs from request
     * @return array<int,string> Ticket id => summary for deletable tickets
     */
    private function filterDeletableTickets(array $ticketIds): array
    {
        $allowed = [];
        foreach ($ticketIds as $id) {
            $id = (int) $id;
            try {
                $ticket = $this->driver->getTicketDetails($id, false);
            } catch (Whups_Exception $e) {
                continue;
            }
            if ($this->permissions->hasQueuePermission($ticket['queue'], Horde_Perms::DELETE)) {
                $allowed[$id] = $ticket['summary'];
            }
        }
        return $allowed;
    }
}
