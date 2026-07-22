<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Change a ticket's type (2-step wizard).
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
use Horde\Whups\Form\Ticket\TypeChangeForm;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\View\PrevNextView;
use Horde_Group_Base;
use Horde_Group_Exception;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde\Core\Session\SessionAccess;
use Horde_Variables;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;

class TypeChangeController implements RequestHandlerInterface
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

        // Permission check: 'update' required.
        if (!$this->permissions->hasQueuePermission($details['queue'], 'update')) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($uid);
        }

        // Build form vars from PSR-7 request + route params.
        $formVars = ($request->getParsedBody() ?? []) + $request->getQueryParams();
        $formVars['id'] = $id;

        // Legacy Horde_Variables still needed for TicketDetails/tabs.
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

        // Load domain data for forms.
        $types = $this->driver->getTypes($details['queue']);
        $groups = $this->loadGroupEnum($uid);

        // Determine wizard step and process submission.
        $result = $this->processWizard($formVars, $types, $groups, $ticket, $id);
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $form = $result;

        // Prev/next navigation.
        $ticketList = $this->session->getScoped('whups', 'tickets');
        $ticketList = is_array($ticketList) ? $ticketList : [];
        $lastSearch = (string) ($this->session->getScoped('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $title = sprintf(_("Set Type for %s"), '[#' . $id . '] ' . $ticket->get('summary'));
        $actionUrl = $this->urlGenerator->urlFor('TicketType', ['id' => (int) $id]);

        $html = $this->renderChrome($title, function () use (
            $form,
            $actionUrl,
            $prevNext,
            $tabs,
        ) {
            $this->topbarSearch->apply();
            $this->notification->notify(['listeners' => 'status']);
            echo $prevNext->render();
            echo $tabs->render('type');

            $renderer = new HtmlRenderer();
            echo $renderer->renderMixed($form, $actionUrl, 'post');
        });

        return $this->htmlResponse($html);
    }

    /**
     * Process wizard progression. Returns either:
     * - A TypeChangeForm to render (at the appropriate step)
     * - A ResponseInterface (redirect after successful submission)
     */
    private function processWizard(
        array $formVars,
        array $types,
        array $groups,
        Whups_Ticket $ticket,
        string $id,
    ): TypeChangeForm|ResponseInterface {
        $type = (int) ($formVars['type'] ?? 0);
        $states = $type ? $this->driver->getStates($type) : [];
        $priorities = $type ? $this->driver->getPriorities($type) : [];

        // Step 1: always validate.
        $form = new TypeChangeForm($formVars, 1, $types, $groups, $states, $priorities);
        if (!$form->isSubmitted() || !$form->validate()) {
            return $form;
        }

        // Step 1 valid → check step 2.
        $form = new TypeChangeForm($formVars, 2, $types, $groups, $states, $priorities);
        if (!$form->validate()) {
            return $form;
        }

        // Both steps valid — process the type change.
        return $this->processTypeChange($form, $ticket, $id);
    }

    /**
     * Process the final form submission: apply the type change.
     */
    private function processTypeChange(
        TypeChangeForm $form,
        Whups_Ticket $ticket,
        string $id,
    ): ResponseInterface {
        $info = $form->getInfo();

        $ticket->change('type', $info['type']);
        $ticket->change('state', $info['state']);
        $ticket->change('priority', $info['priority']);

        if (!empty($info['newcomment'])) {
            $ticket->change('comment', $info['newcomment']);
        }
        if (!empty($info['group'])) {
            $ticket->change('comment-perms', $info['group']);
        }

        try {
            $ticket->commit();
            $this->notification->push(_("Successfully changed ticket type."), 'horde.success');
            return $this->redirect(
                $this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]),
            );
        } catch (Whups_Exception $e) {
            $this->notification->push($e, 'horde.error');
        }

        return $this->redirect($this->urlGenerator->urlFor('TicketType', ['id' => (int) $id]));
    }

    /**
     * Load the group enum for comment visibility.
     *
     * @return array<int|string,string> Group id => name, with 0 => "Any Group" prepended
     */
    private function loadGroupEnum(string $uid): array
    {
        if (!$uid) {
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
