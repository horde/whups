<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Move a ticket to a different queue (3-step wizard).
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
use Horde\Form\V3\HtmlRenderer;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Form\Ticket\QueueMoveForm;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\View\PrevNextView;
use Horde_Group_Base;
use Horde_Group_Exception;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Perms_Base;
use Horde_Registry;
use Horde\Core\Session\SessionAccess;
use Horde_Variables;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;

class QueueMoveController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

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
        private readonly Horde_Group_Base $groupService,
        private readonly Horde_Perms_Base $perms,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $id = $route['id'] ?? $request->getQueryParams()['id'] ?? null;
        $id = preg_replace('|\D|', '', (string) ($id ?? ''));

        $uid = $this->registry->getAuth() ?: '';

        if (!$id) {
            $this->notification->push(_("Invalid Ticket Id"), 'horde.error');
            return $this->redirectToDefault($uid);
        }

        try {
            $details = $this->driver->getTicketDetails($id);
            $ticket = new Whups_Ticket($id, $details);
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
            return $this->redirectToDefault($uid);
        }

        // Permission check: DELETE required for queue moves.
        if (!$this->permissions->hasQueuePermission($ticket->get('queue'), Horde_Perms::DELETE)) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($uid);
        }

        // Build form vars from PSR-7 request + route params.
        $formVars = ($request->getParsedBody() ?? []) + $request->getQueryParams();
        $formVars['id'] = $id;

        // Legacy Horde_Variables still needed for tabs/TicketDetails.
        $vars = Horde_Variables::getDefaultVariables();
        $vars->set('id', $id);
        foreach ($details as $varname => $value) {
            $vars->add($varname, $value);
        }

        // RSS feed link.
        $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
        $this->pageOutput->addLinkTag([
            'href' => $rssUrl,
            'title' => '[#' . $id . '] ' . $ticket->get('summary'),
        ]);

        // Load shared domain data.
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::EDIT,
        );
        $groups = $this->loadGroupEnum($uid);

        // Determine wizard step and process submission.
        $result = $this->processWizard($formVars, $queues, $groups, $ticket, $id);
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // $result is the QueueMoveForm to render.
        $form = $result;

        // Prev/next navigation.
        $ticketList = $this->session->getScoped('whups', 'tickets');
        $ticketList = is_array($ticketList) ? $ticketList : [];
        $lastSearch = (string) ($this->session->getScoped('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $title = sprintf(_("Set Queue for %s"), '[#' . $id . '] ' . $ticket->get('summary'));
        $actionUrl = $this->urlGenerator->urlFor('TicketQueue', ['id' => (int) $id]);

        $html = $this->renderChrome($title, function () use (
            $form,
            $actionUrl,
            $prevNext,
            $tabs,
        ) {
            $this->topbarSearch->apply();
            $this->notification->notify(['listeners' => 'status']);
            echo $prevNext->render();
            echo $tabs->render('queue');

            $renderer = new HtmlRenderer();
            echo $renderer->renderMixed($form, $actionUrl, 'post');
        });

        return $this->htmlResponse($html);
    }

    /**
     * Process wizard progression. Returns either:
     * - A QueueMoveForm to render (at the appropriate step)
     * - A ResponseInterface (redirect after successful submission)
     */
    private function processWizard(
        array $formVars,
        array $queues,
        array $groups,
        Whups_Ticket $ticket,
        string $id,
    ): QueueMoveForm|ResponseInterface {
        // Load domain data that depends on prior step selections.
        $queue = (int) ($formVars['queue'] ?? 0);
        $queueInfo = $queue ? $this->driver->getQueue($queue) : [];
        $versioned = !empty($queueInfo['versioned']);

        $types = $queue ? $this->driver->getTypes($queue) : [];
        $versions = $versioned ? $this->driver->getVersions($queue) : null;

        $type = (int) ($formVars['type'] ?? 0);
        $states = $type ? $this->driver->getStates($type) : [];
        $priorities = $type ? $this->driver->getPriorities($type) : [];

        // Progressive validation to determine the current step.
        // Step 1: always validate.
        $form = new QueueMoveForm($formVars, 1, $queues, $groups, $types, $versions, $states, $priorities);
        if (!$form->isSubmitted() || !$form->validate()) {
            return $form;
        }

        // Step 1 valid → check step 2.
        $form = new QueueMoveForm($formVars, 2, $queues, $groups, $types, $versions, $states, $priorities);
        if (!$form->validate()) {
            return $form;
        }

        // Step 2 valid → check step 3.
        $form = new QueueMoveForm($formVars, 3, $queues, $groups, $types, $versions, $states, $priorities);
        if (!$form->validate()) {
            return $form;
        }

        // All steps valid — process the move.
        return $this->processMove($form, $ticket, $id);
    }

    /**
     * Process the final form submission: apply the queue move.
     */
    private function processMove(
        QueueMoveForm $form,
        Whups_Ticket $ticket,
        string $id,
    ): ResponseInterface {
        $info = $form->getInfo();

        $ticket->change('queue', $info['queue']);
        $ticket->change('type', $info['type']);
        $ticket->change('state', $info['state']);
        $ticket->change('priority', $info['priority']);

        if (!empty($info['version'])) {
            $ticket->change('version', $info['version']);
        }
        if (!empty($info['newcomment'])) {
            $ticket->change('comment', $info['newcomment']);
        }
        if (!empty($info['group'])) {
            $ticket->change('comment-perms', $info['group']);
        }

        try {
            $ticket->commit();
            $this->notification->push(
                sprintf(_("Moved ticket %d to \"%s\""), $id, $ticket->get('queue_name')),
                'horde.success',
            );
            return $this->redirect(
                $this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]),
            );
        } catch (Whups_Exception $e) {
            $this->notification->push($e, 'horde.error');
        }

        // On error, redirect back to the form.
        return $this->redirect($this->urlGenerator->urlFor('TicketQueue', ['id' => (int) $id]));
    }

    /**
     * Load the group enum for comment visibility (admin or hiddenComments permission).
     *
     * @return array<int|string,string> Group id => name, with 0 => "Any Group" prepended
     */
    private function loadGroupEnum(string $uid): array
    {
        if (!$uid) {
            return [];
        }

        // Check admin or hiddenComments permission.
        $isAdmin = $this->registry->isAdmin([
            'permission' => 'whups:admin',
            'permlevel' => Horde_Perms::EDIT,
        ]);
        $hasHiddenPerm = $this->perms->hasPermission(
            'whups:hiddenComments',
            $uid,
            Horde_Perms::EDIT,
        );

        if (!$isAdmin && !$hasHiddenPerm) {
            return [];
        }

        try {
            $mygroups = $this->groupService->listGroups($uid);
        } catch (Horde_Group_Exception $e) {
            return [];
        }

        if (!$mygroups) {
            return [];
        }

        $grouplist = [];
        foreach (array_keys($mygroups) as $gid) {
            $grouplist[$gid] = $this->groupService->getName($gid, true);
        }
        asort($grouplist);

        return [0 => _("Any Group")] + $grouplist;
    }

    private function redirectToDefault(string $uid): ResponseInterface
    {
        $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
        return $this->redirect($this->urlGenerator->defaultViewUrl($defaultView));
    }
}
