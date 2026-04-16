<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Manage ticket watchers (listeners).
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
use Horde\Whups\Service\UserFormatter;
use Horde\Whups\View\PrevNextView;
use Horde_Form_Renderer;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_Session;
use Horde_Themes_Image;
use Horde_Url;
use Horde_Variables;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Form_AddListener;
use Whups_Form_DeleteListener;
use Whups_Form_TicketDetails;
use Whups_Ticket;

class WatchController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly Horde_Session $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly PrefsService $prefs,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
        private readonly UserFormatter $userFormatter,
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
            $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
            return $this->redirect($webroot . '/' . $defaultView);
        }

        try {
            $details = $this->driver->getTicketDetails($id);
            $ticket = new Whups_Ticket($id, $details);
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
            $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
            return $this->redirect($webroot . '/' . $defaultView);
        }

        // Build form variables.
        $vars = Horde_Variables::getDefaultVariables();
        $vars->set('id', $id);
        foreach ($details as $varname => $value) {
            $vars->add($varname, $value);
        }

        $addForm = new Whups_Form_AddListener($vars, _("Add Watcher"));
        $delForm = new Whups_Form_DeleteListener($vars, _("Remove Watcher"));

        // Handle add listener.
        if ($vars->get('formname') == 'whups_form_addlistener'
            && $addForm->validate($vars)
        ) {
            $info = $addForm->getInfo($vars);
            try {
                $this->driver->addListener($id, '**' . $info['add_listener']);
                $ticket->notify(
                    $info['add_listener'],
                    false,
                    ['**' . $info['add_listener'] => 'listener'],
                );
                $this->notification->push(
                    sprintf(_("%s will be notified when this ticket is updated."), $info['add_listener']),
                    'horde.success',
                );
                return $this->redirect($this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]));
            } catch (Whups_Exception $e) {
                $this->notification->push($e, 'horde.error');
            }
        } elseif ($listener = $vars->get('del_listener')) {
            // Handle delete listener.
            try {
                $this->driver->deleteListener($id, '**' . $listener);
                $this->notification->push(
                    sprintf(_("%s will no longer receive updates for this ticket."), $listener),
                    'horde.success',
                );
                return $this->redirect($this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]));
            } catch (Whups_Exception $e) {
                $this->notification->push($e, 'horde.error');
            }
        }

        // Prepare watcher list data.
        $listeners = array_keys($this->driver->getListeners($id, false, false, false));
        array_walk($listeners, function (&$l) {
            $l = preg_replace('/^\*\*/', '', $l);
        });

        $owners = $this->driver->getOwners($id);
        $owners = $owners ? reset($owners) : [];

        $delUrl = (new Horde_Url($webroot . '/ticket/' . $id . '/watch'))->add('id', $id);
        $delImg = Horde_Themes_Image::tag('delete.png');

        // Prev/next navigation.
        $ticketList = $this->session->get('whups', 'tickets', Horde_Session::TYPE_ARRAY);
        $lastSearch = (string) ($this->session->get('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $title = sprintf(_("Watchers for %s"), '[#' . $id . '] ' . $ticket->get('summary'));
        $isAuthenticated = (bool) $this->registry->getAuth();

        $html = $this->renderChrome($title, function () use (
            $ticket,
            $vars,
            $addForm,
            $delForm,
            $listeners,
            $owners,
            $delUrl,
            $delImg,
            $prevNext,
            $tabs,
            $webroot,
            $id,
            $isAuthenticated,
        ) {
            // Topbar search.
            $this->topbarSearch->apply();

            // RSS feed link.
            $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
            $this->pageOutput->addLinkTag([
                'href' => $rssUrl,
                'title' => '[#' . $id . '] ' . $ticket->get('summary'),
            ]);

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Prev/next.
            echo $prevNext->render();

            // Tabs.
            echo $tabs->render('watch');

            // Watchers list.
            $this->renderWatchersList($isAuthenticated, $listeners, $owners, $delUrl, $delImg);

            // Add listener form.
            $r = new Horde_Form_Renderer();
            $addForm->renderActive($r, $vars, new Horde_Url($webroot . '/ticket/' . $id . '/watch'), 'post');
            echo '<br class="spacer" />';

            // Delete listener form (only for unauthenticated users).
            if (!$isAuthenticated) {
                $delForm->renderActive($r, $vars, new Horde_Url($webroot . '/ticket/' . $id . '/watch'), 'post');
                echo '<br class="spacer" />';
            }

            // Ticket details (inactive).
            $detailsForm = new Whups_Form_TicketDetails(
                $vars,
                $ticket,
                '[#' . $id . '] ' . $ticket->get('summary'),
            );
            $ticket->setDetails($vars);
            $detailsForm->renderInactive($detailsForm->getRenderer(), $vars);
        });

        return $this->htmlResponse($html);
    }

    /**
     * Render the watchers/owners status section.
     */
    private function renderWatchersList(
        bool $isAuthenticated,
        array $listeners,
        array $owners,
        Horde_Url $delUrl,
        string $delImg,
    ): void {
        echo '<h1 class="header">' . htmlspecialchars(_("Status")) . '</h1>' . "\n";

        if ($isAuthenticated) {
            echo '<br class="spacer" />' . "\n"
                . '<table class="horde-table">' . "\n"
                . '  <tr><th colspan="2">' . htmlspecialchars(_("People watching")) . '</th></tr>' . "\n";

            if ($listeners) {
                foreach ($listeners as $listener) {
                    echo '  <tr><td>' . htmlspecialchars($listener) . '</td>'
                        . '<td>' . $delUrl->add('del_listener', $listener)->link() . $delImg . '</a></td>'
                        . '</tr>' . "\n";
                }
            } else {
                echo '  <tr><td><em>' . htmlspecialchars(_("No people watching")) . '</em></td></tr>' . "\n";
            }

            echo '</table>' . "\n"
                . '<br class="spacer" />' . "\n"
                . '<table class="horde-table">' . "\n"
                . '  <tr><th>' . htmlspecialchars(_("People responsible")) . '</th></tr>' . "\n";

            if ($owners) {
                foreach ($owners as $owner) {
                    echo '  <tr><td>' . htmlspecialchars($this->userFormatter->format($owner)) . '</td></tr>' . "\n";
                }
            } else {
                echo '  <tr><td><em>' . htmlspecialchars(_("No people responsible")) . '</em></td></tr>' . "\n";
            }

            echo '</table>' . "\n";
        } else {
            echo '<p class="horde-content">' . "\n";
            printf(
                _("%d people watching, %d people responsible"),
                count($listeners),
                count($owners),
            );
            echo '</p>' . "\n";
        }

        echo '<br class="spacer" />' . "\n";
    }
}
