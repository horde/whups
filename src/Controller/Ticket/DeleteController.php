<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Delete a ticket.
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

use Horde\Core\Service\PrefsService;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\View\PrevNextView;
use Horde_Exception_NotFound;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde\Core\Session\HordeSession;
use Horde_Url;
use Horde_Variables;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Form_Ticket_Delete;
use Whups_Form_TicketDetails;
use Whups_Ticket;

class DeleteController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly HordeSession $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly PrefsService $prefs,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $id = $route['id'] ?? $request->getQueryParams()['id'] ?? null;
        $id = preg_replace('|\D|', '', (string) ($id ?? ''));

        $webroot = $this->registry->get('webroot', 'whups');
        $uid = $this->registry->getAuth() ?: '';

        if (!$id) {
            $this->notification->push(_("Invalid Ticket Id"), 'horde.error');
            return $this->redirectToDefault($webroot, $uid);
        }

        try {
            $details = $this->driver->getTicketDetails($id);
            $ticket = new Whups_Ticket($id, $details);
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
            return $this->redirectToDefault($webroot, $uid);
        }

        // Permission check: DELETE required.
        if (!$this->permissions->hasQueuePermission($details['queue'], Horde_Perms::DELETE)) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($webroot, $uid);
        }

        // RSS feed link.
        $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
        $this->pageOutput->addLinkTag(['href' => $rssUrl, 'title' => '[#' . $id . '] ' . $ticket->get('summary')]);

        // Build form variables.
        $vars = Horde_Variables::getDefaultVariables();
        $vars->set('id', $id);
        foreach ($details as $varname => $value) {
            $vars->add($varname, $value);
        }

        $title = sprintf(_("Delete %s?"), '[#' . $id . '] ' . $ticket->get('summary'));
        $deleteForm = new Whups_Form_Ticket_Delete($vars, $title);

        // Handle form submission.
        if ($vars->get('formname') == 'whups_form_ticket_delete'
            && $deleteForm->validate($vars)
        ) {
            if ($vars->get('submitbutton') == _("Delete")) {
                try {
                    $ticket->delete();
                    $this->notification->push(
                        sprintf(_("Ticket %d has been deleted."), $id),
                        'horde.success',
                    );
                    return $this->redirectToDefault($webroot, $uid);
                } catch (Whups_Exception $e) {
                    $this->notification->push(
                        _("There was an error deleting the ticket:") . ' ' . $e->getMessage(),
                        'horde.error',
                    );
                } catch (Horde_Exception_NotFound $e) {
                    $this->notification->push(sprintf(_("Ticket %d not found."), $id));
                }
            } else {
                $this->notification->push(_("The ticket was not deleted."), 'horde.message');
            }
        }

        // Prev/next navigation.
        $ticketList = $this->session->getScoped('whups', 'tickets') ?? [];
        $lastSearch = (string) ($this->session->getScoped('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $html = $this->renderChrome($title, function () use (
            $title,
            $ticket,
            $vars,
            $deleteForm,
            $prevNext,
            $tabs,
            $webroot,
            $id,
        ) {
            // Topbar search.
            $this->topbarSearch->apply();

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Prev/next.
            echo $prevNext->render();

            // Tabs.
            echo $tabs->render('delete');

            // Delete confirmation form.
            $deleteForm->renderActive(
                $deleteForm->getRenderer(),
                $vars,
                new Horde_Url($webroot . '/ticket/' . $id . '/delete'),
                'post',
            );
            echo '<br />';

            // Ticket details (inactive).
            $detailsForm = new Whups_Form_TicketDetails($vars, $ticket);
            $ticket->setDetails($vars);
            $r = $detailsForm->getRenderer();
            $r->_name = $detailsForm->getName();
            $r->beginInactive($title);
            $r->renderFormInactive($detailsForm, $vars);
            $r->end();
        });

        return $this->htmlResponse($html);
    }

    private function redirectToDefault(string $webroot, string $uid): ResponseInterface
    {
        $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
        return $this->redirect($webroot . '/' . $defaultView);
    }
}
