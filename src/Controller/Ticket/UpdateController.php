<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Update a ticket (edit fields + comment).
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
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Perms_Base;
use Horde_Registry;
use Horde_Session;
use Horde_Text_Flowed;
use Horde_Url;
use Horde_Variables;
use Horde_View_Topbar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Form_Ticket_Edit;
use Whups_Form_TicketDetails;
use Whups_Ticket;

class UpdateController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Perms_Base $perms,
        private readonly Horde_Registry $registry,
        private readonly Horde_Session $session,
        private readonly Horde_View_Topbar $topbar,
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

        // Permission check: 'update' required.
        if (!$this->permissions->hasQueuePermission($ticket->get('queue'), 'update')) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($webroot, $uid);
        }

        // Build form variables — setDetails with editable=true populates vars
        // with current ticket values for form defaults.
        $vars = Horde_Variables::getDefaultVariables();
        $ticket->setDetails($vars, true);

        // If replying to a specific transaction, pre-fill comment + group.
        $this->prefillQuotedComment($vars, $ticket);

        $title = '[#' . $id . '] ' . $ticket->get('summary');
        $editForm = new Whups_Form_Ticket_Edit($vars, $ticket, sprintf(_("Update %s"), $title));

        // Handle form submission.
        if ($vars->get('formname') == 'whups_form_ticket_edit'
            && $editForm->validate($vars)
        ) {
            $info = $editForm->getInfo($vars);

            $ticket->change('summary', $info['summary']);
            $ticket->change('state', $info['state']);
            $ticket->change('priority', $info['priority']);
            $ticket->change('due', $info['due']);

            if (!empty($info['version'])) {
                $ticket->change('version', $info['version']);
            }
            if (!empty($info['newcomment'])) {
                $ticket->change('comment', $info['newcomment']);
            }

            // Update user and group assignments.
            if ($this->permissions->hasQueuePermission($vars->get('queue'), 'assign')) {
                $ticket->change('owners', array_merge(
                    $info['owners'] ?? [],
                    $info['group_owners'] ?? [],
                ));
            }

            // Update attributes.
            $this->driver->setAttributes($info, $ticket);

            // Add attachment if one was uploaded.
            if (!empty($info['newattachment']['name'])) {
                $ticket->change('attachment', [
                    'name' => $info['newattachment']['name'],
                    'tmp_name' => $info['newattachment']['tmp_name'],
                ]);
            }

            // Comment group permissions.
            if (!empty($info['group'])) {
                $ticket->change('comment-perms', $info['group']);
            }

            try {
                $ticket->commit();
                $this->notification->push(_("Ticket Updated"), 'horde.success');
                return $this->redirect(
                    $this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]),
                );
            } catch (Whups_Exception $e) {
                $this->notification->push($e, 'horde.error');
            }
        }

        // RSS feed link.
        $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
        $this->pageOutput->addLinkTag([
            'href' => $rssUrl,
            'title' => $title,
        ]);

        // Prev/next navigation.
        $ticketList = $this->session->get('whups', 'tickets', Horde_Session::TYPE_ARRAY);
        $lastSearch = (string) ($this->session->get('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $html = $this->renderChrome($title, function () use (
            $title,
            $ticket,
            $vars,
            $editForm,
            $prevNext,
            $tabs,
            $webroot,
            $id,
        ) {
            // Topbar search.
            $this->topbar->search = true;
            $this->topbar->searchAction = new Horde_Url($webroot . '/ticket');
            $this->topbar->searchLabel = $this->session->get('whups', 'search') ?: _("Ticket #Id");

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Prev/next.
            echo $prevNext->render();

            // Tabs.
            echo $tabs->render('update');

            // Edit form.
            $editForm->renderActive(
                renderer: $editForm->getRenderer(),
                vars: $vars,
                action: new Horde_Url($webroot . '/ticket/' . $id . '/update'),
                method: 'post',
                enctype: 'multipart/form-data',
            );
            echo '<br class="spacer" />';

            // Ticket details (inactive).
            $detailsForm = new Whups_Form_TicketDetails($vars, $ticket, $title);
            $ticket->setDetails($vars);
            $detailsForm->renderInactive($detailsForm->getRenderer(), $vars);
        });

        return $this->htmlResponse($html);
    }

    /**
     * If a transaction ID is given, pre-fill the comment with the quoted
     * original and default group restriction to match the original comment.
     */
    private function prefillQuotedComment(Horde_Variables $vars, Whups_Ticket $ticket): void
    {
        $tid = $vars->get('transaction');
        if (!$tid) {
            return;
        }

        $history = $this->permissions->filterComments(
            $this->driver->getHistory($ticket->getId()),
            Horde_Perms::READ,
        );

        if (empty($history[$tid]['comment'])) {
            return;
        }

        // If the original comment was restricted, default the group selector
        // to that same group.
        foreach ($history[$tid]['changes'] as $change) {
            if (!empty($change['private'])) {
                try {
                    $permission = $this->perms->getPermission(
                        'whups:comments:' . $change['value'],
                    );
                    $groupPerms = $permission->getGroupPermissions();
                    $groupId = array_key_first($groupPerms);
                    if ($groupId !== null) {
                        $vars->set('group', $groupId);
                    }
                } catch (\Horde_Perms_Exception $e) {
                    // Permission not found — skip group prefill.
                }
                break;
            }
        }

        $flowed = new Horde_Text_Flowed(
            preg_replace("/\s*\n/U", "\n", $history[$tid]['comment']),
            'UTF-8',
        );
        $vars->set('newcomment', $flowed->toFlowed(true));
    }

    private function redirectToDefault(string $webroot, string $uid): ResponseInterface
    {
        $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
        return $this->redirect($webroot . '/' . $defaultView);
    }
}
