<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Display a single ticket with details and history.
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
use Horde\Whups\Service\UserFormatter;
use Horde\Whups\View\PrevNextView;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde_Session;
use Horde_Url;
use Horde_Variables;
use Horde_View_Topbar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Form_Renderer_Comment;
use Whups_Form_TicketDetails;
use Whups_Ticket;

class ViewController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly Horde_Session $session,
        private readonly Horde_View_Topbar $topbar,
        private readonly PrefsService $prefs,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
        private readonly UserFormatter $userFormatter,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $queryParams = $request->getQueryParams();

        // Ticket ID comes from route match or query string.
        $id = $route['id'] ?? $queryParams['id'] ?? $queryParams['searchfield'] ?? null;
        $id = preg_replace('|\D|', '', (string) ($id ?? ''));

        $webroot = $this->registry->get('webroot', 'whups');
        $uid = $this->registry->getAuth() ?: '';

        if (!$id) {
            $this->notification->push(_("Invalid Ticket Id"), 'horde.error');
            $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
            return $this->redirect($webroot . '/' . $defaultView);
        }

        try {
            $details = $this->driver->getTicketDetails($id);
            $ticket = new Whups_Ticket($id, $details);
        } catch (Whups_Exception $e) {
            if ($e->getCode() === 0) {
                $this->notification->push($e->getMessage(), 'horde.warning');
            } else {
                $this->notification->push($e->getMessage(), 'horde.error');
            }
            $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
            return $this->redirect($webroot . '/' . $defaultView);
        }

        $vars = Horde_Variables::getDefaultVariables();
        if ($vars->get('searchfield')) {
            $vars->set('id', $vars->get('searchfield'));
        }
        $ticket->setDetails($vars);

        $title = '[#' . $ticket->getId() . '] ' . $ticket->get('summary');

        // History with permission filtering.
        $form = new Whups_Form_TicketDetails($vars, $ticket);
        $history = $this->permissions->filterComments(
            $this->driver->getHistory($ticket->getId(), $form),
            Horde_Perms::READ,
        );

        // Comment sort direction.
        $commentSortDir = (int) $this->prefs->getValue($uid, 'whups', 'comment_sort_dir');

        // Prev/next navigation data from session.
        $ticketList = $this->session->get('whups', 'tickets', Horde_Session::TYPE_ARRAY);
        $lastSearch = (string) ($this->session->get('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView(
            (int) $ticket->getId(),
            $ticketList,
            $lastSearch,
            $this->urlGenerator,
        );

        // Ticket tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $html = $this->renderChrome($title, function () use (
            $title,
            $ticket,
            $vars,
            $form,
            $history,
            $commentSortDir,
            $prevNext,
            $tabs,
            $webroot,
        ) {
            // Feed links.
            $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $ticket->getId()]);
            $this->pageOutput->addLinkTag([
                'href' => $rssUrl,
                'title' => $title,
            ]);

            $openSearchUrl = (new Horde_Url($webroot . '/opensearch.php', true))->toString(true, false);
            $this->pageOutput->addLinkTag([
                'href' => $openSearchUrl,
                'rel' => 'search',
                'type' => 'application/opensearchdescription+xml',
                'title' => $this->registry->get('name') . ' (' . (new Horde_Url($webroot, true))->toString(true, false) . ')',
            ]);

            // Topbar search.
            $this->topbar->search = true;
            $this->topbar->searchAction = new Horde_Url($webroot . '/ticket');
            $this->topbar->searchLabel = $this->session->get('whups', 'search') ?: _("Ticket #Id");

            // Prev/next navigation.
            echo $prevNext->render();

            // Tabs.
            echo $tabs->render('history');

            // Ticket details form (inactive).
            $renderer = $form->getRenderer();
            $renderer->_name = $form->getName();
            $renderer->beginInactive($title);
            $renderer->renderFormInactive($form, $vars);
            $renderer->end();

            echo '<br class="spacer" />';

            // Comment history.
            $comment = new Whups_Form_Renderer_Comment();
            $comment->begin(_("History"));

            $chtml = [];
            foreach ($history as $transaction => $commentValues) {
                $chtml[] = $comment->render($transaction, new Horde_Variables($commentValues));
            }
            if ($commentSortDir) {
                $chtml = array_reverse($chtml);
            }
            echo implode('', $chtml);

            $comment->end();
        });

        return $this->htmlResponse($html);
    }

}
