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

use Horde;
use Horde\Core\Service\PrefsService;
use Horde\Form\V3\HtmlRenderer;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Form\Ticket\EditTicketForm;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\View\PrevNextView;
use Horde_Core_Hooks;
use Horde_Exception;
use Horde_Exception_HookNotSet;
use Horde_Group_Base;
use Horde_Group_Exception;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Perms_Base;
use Horde_Perms_Exception;
use Horde_Registry;
use Horde\Core\Session\SessionAccess;
use Horde_Text_Flowed;
use Horde_Variables;
use Horde\Util\Variables;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
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
        private readonly SessionAccess $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly PrefsService $prefs,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
        private readonly Horde_Group_Base $groupService,
        private readonly Horde_Core_Hooks $hooks,
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
        if (!$this->permissions->hasQueuePermission($ticket->get('queue'), 'update')) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($uid);
        }

        // Build form variables — setDetails with editable=true populates vars
        // with current ticket values for form defaults.
        $vars = Horde_Variables::getDefaultVariables();
        $ticket->setDetails($vars, true);

        $formVars = ($request->getParsedBody() ?? []) + $request->getQueryParams();
        $formVars['id'] = $id;
        $formVars['type'] = $vars->get('type');

        // Merge ticket defaults into formVars for initial display.
        foreach ($details as $varname => $value) {
            if (!isset($formVars[$varname])) {
                $formVars[$varname] = $value;
            }
        }

        // If replying to a specific transaction, pre-fill comment + group.
        $this->prefillQuotedComment($formVars, $vars, $ticket);

        // Build field data for the form.
        $fieldData = $this->buildFieldData($vars, $ticket);
        $groupedFields = $this->getGroupedFields($ticket, $fieldData);

        $title = '[#' . $id . '] ' . $ticket->get('summary');
        $editForm = new EditTicketForm($formVars, $fieldData, $groupedFields, sprintf(_("Update %s"), $title));

        // Handle form submission.
        if ($editForm->isSubmitted() && $editForm->validate()) {
            // Auth check.
            if (!$this->registry->getAuth()) {
                $this->notification->push(_("Permission Denied."), 'horde.error');
            } else {
                $info = $editForm->getInfo();

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
        }

        // Handle form reply reload: if a reply was selected, append its text.
        $this->applyFormReply($formVars, $vars);

        // RSS feed link.
        $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
        $this->pageOutput->addLinkTag([
            'href' => $rssUrl,
            'title' => $title,
        ]);

        // Prev/next navigation.
        $ticketList = $this->session->getScoped('whups', 'tickets');
        $ticketList = is_array($ticketList) ? $ticketList : [];
        $lastSearch = (string) ($this->session->getScoped('whups', 'last_search') ?? '');
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
            $id,
        ) {
            // Topbar search.
            $this->topbarSearch->apply();

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Prev/next.
            echo $prevNext->render();

            // Tabs.
            echo $tabs->render('update');

            // Edit form.
            $renderer = new HtmlRenderer();
            echo $renderer->render($editForm, $this->urlGenerator->urlFor('TicketUpdate', ['id' => (int) $id]), 'post');
            echo '<br class="spacer" />';

            // Ticket details (inactive).
            $detailsForm = new Whups_Form_TicketDetails($vars, $ticket, $title);
            $ticket->setDetails($vars);
            $detailsForm->renderInactive($detailsForm->getRenderer(), $vars);
        });

        return $this->htmlResponse($html);
    }

    /**
     * Build the field data map for the edit form.
     *
     * Each field name maps to an array with label, type, params, etc.
     * The form constructor uses this to add variables.
     *
     * @return array<string,array>
     */
    private function buildFieldData(Horde_Variables|Variables $vars, Whups_Ticket $ticket): array
    {
        $type = $vars->get('type');
        $queue = $vars->get('queue');
        $conf = $GLOBALS['conf'] ?? [];

        $startYear = (int) date('Y');
        $due = $vars->get('due');
        if (is_numeric($due)) {
            $startYear = min($startYear, (int) date('Y', (int) $due));
        }

        $fields = [];

        // Summary.
        $fields['summary'] = [
            'label' => _("Summary"),
            'varName' => 'summary',
            'type' => 'text',
            'required' => true,
        ];

        // Version (if queue is versioned).
        $qinfo = $this->driver->getQueue($queue);
        if (!empty($qinfo['versioned'])) {
            $versions = $this->driver->getVersions($queue);
            if (count($versions) === 0) {
                $fields['version'] = [
                    'label' => _("Queue Version"),
                    'varName' => 'version',
                    'type' => 'invalid',
                    'required' => true,
                    'params' => [_("This queue requires that you specify a version, but there are no versions associated with it. Until versions are created for this queue, you will not be able to create tickets.")],
                ];
            } else {
                $fields['version'] = [
                    'label' => _("Queue Version"),
                    'varName' => 'version',
                    'kind' => 'enum',
                    'type' => 'enum',
                    'required' => true,
                    'values' => $versions,
                ];
            }
        }

        // State.
        $fields['state'] = [
            'label' => _("State"),
            'varName' => 'state',
            'kind' => 'enum',
            'type' => 'enum',
            'required' => true,
            'values' => $this->driver->getStates($type),
        ];

        // Priority.
        $fields['priority'] = [
            'label' => _("Priority"),
            'varName' => 'priority',
            'kind' => 'enum',
            'type' => 'enum',
            'required' => true,
            'values' => $this->driver->getPriorities($type),
        ];

        // Due date.
        $fields['due'] = [
            'label' => _("Due Date"),
            'varName' => 'due',
            'type' => 'monthdayyear',
            'required' => false,
            'params' => [$startYear],
        ];

        // Ticket attributes.
        try {
            $attributes = $ticket->addAttributes();
        } catch (Whups_Exception $e) {
            $attributes = [];
        }
        foreach ($attributes as $attribute) {
            $fieldName = 'attribute_' . $attribute['id'];
            $fields[$fieldName] = [
                'label' => $attribute['human_name'],
                'varName' => $fieldName,
                'kind' => 'attribute',
                'type' => $attribute['type'],
                'required' => $attribute['required'],
                'readonly' => $attribute['readonly'],
                'description' => $attribute['desc'],
                'params' => $attribute['params'],
                'default' => $attribute['value'],
            ];
        }

        // Owners (permission-gated).
        if (Whups::hasPermission($queue, 'queue', 'assign')) {
            $users = $this->driver->getQueueUsers($queue);
            $fUsers = [];
            foreach ($users as $user) {
                $fUsers['user:' . $user] = Whups::formatUser($user);
            }

            try {
                $assignAllGroups = !empty($conf['prefs']['assign_all_groups']);
                $mygroups = $this->groupService->listAll(
                    $assignAllGroups ? null : $this->registry->getAuth(),
                );
                asort($mygroups);
            } catch (Horde_Group_Exception $e) {
                $mygroups = [];
            }

            $fGroups = [];
            foreach (array_keys($mygroups) as $gid) {
                $fGroups['group:' . $gid] = $this->groupService->getName($gid);
            }

            if ($fUsers) {
                asort($fUsers);
                $fields['owner'] = [
                    'label' => _("Owners"),
                    'varName' => 'owners',
                    'kind' => 'multienum',
                    'values' => $fUsers,
                ];
            }

            if ($fGroups) {
                asort($fGroups);
                $fields['group_owner'] = [
                    'label' => _("Group Owners"),
                    'varName' => 'group_owners',
                    'kind' => 'multienum',
                    'values' => $fGroups,
                ];
            }
        }

        // Attachment.
        $fields['attachments'] = [
            'label' => _("Attachment"),
            'varName' => 'newattachment',
            'type' => 'file',
            'required' => false,
        ];

        // Comment.
        $fields['comment'] = [
            'label' => _("Comment"),
            'varName' => 'newcomment',
            'type' => 'longtext',
            'required' => false,
        ];

        // Form replies.
        try {
            $replies = Whups::permissionsFilter(
                $this->driver->getReplies($type),
                'reply',
            );
        } catch (Whups_Exception $e) {
            $replies = [];
        }
        if ($replies) {
            $replyParams = [];
            foreach ($replies as $key => $reply) {
                $replyParams[$key] = $reply['reply_name'];
            }
            $fields['reply'] = [
                'label' => _("Form Reply:"),
                'varName' => 'reply',
                'kind' => 'enum',
                'type' => 'enum',
                'required' => false,
                'values' => $replyParams,
                'params' => [$replyParams, true],
            ];
        }

        // Comment visibility groups.
        $uid = $this->registry->getAuth() ?: '';
        $commentGroups = $this->loadCommentGroupEnum($uid);
        if ($commentGroups) {
            $fields['group'] = [
                'label' => _("Make this comment visible only to members of a group?"),
                'varName' => 'group',
                'kind' => 'enum',
                'type' => 'enum',
                'required' => false,
                'values' => $commentGroups,
            ];
        }

        return $fields;
    }

    /**
     * Get hook-based field grouping, or null if no hook is set.
     *
     * @return array<string,list<string>>|null
     */
    private function getGroupedFields(Whups_Ticket $ticket, array $fieldData): ?array
    {
        $fieldNames = array_keys($fieldData);

        try {
            $grouped = $this->hooks->callHook(
                'group_fields',
                'whups',
                [$ticket->get('type'), $fieldNames],
            );
            return $grouped;
        } catch (Horde_Exception_HookNotSet $e) {
            return null;
        } catch (Horde_Exception $e) {
            Horde::log($e, 'ERR');
            return null;
        }
    }

    /**
     * Apply form reply selection: append reply text to comment.
     */
    private function applyFormReply(array &$formVars, Horde_Variables|Variables $vars): void
    {
        $reply = $formVars['reply'] ?? $vars->get('reply');
        if (!$reply) {
            return;
        }

        $type = $formVars['type'] ?? $vars->get('type');
        try {
            $replies = $this->driver->getReplies($type);
        } catch (Whups_Exception $e) {
            return;
        }

        if (!isset($replies[$reply])) {
            return;
        }

        $comment = (string) ($formVars['newcomment'] ?? $vars->get('newcomment') ?? '');
        if (strlen($comment)) {
            $comment .= "\n\n";
        }
        $comment .= $replies[$reply]['reply_text'];

        $formVars['newcomment'] = $comment;
        $vars->set('newcomment', $comment);
        unset($formVars['reply']);
        $vars->remove('reply');
    }

    /**
     * If a transaction ID is given, pre-fill the comment with the quoted
     * original and default group restriction to match the original comment.
     */
    private function prefillQuotedComment(array &$formVars, Horde_Variables|Variables $vars, Whups_Ticket $ticket): void
    {
        $tid = $formVars['transaction'] ?? $vars->get('transaction');
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
                        $formVars['group'] = $groupId;
                        $vars->set('group', $groupId);
                    }
                } catch (Horde_Perms_Exception $e) {
                    // Permission not found — skip group prefill.
                }
                break;
            }
        }

        $flowed = new Horde_Text_Flowed(
            preg_replace("/\s*\n/U", "\n", $history[$tid]['comment']),
            'UTF-8',
        );
        $quoted = $flowed->toFlowed(true);
        $formVars['newcomment'] = $quoted;
        $vars->set('newcomment', $quoted);
    }

    /**
     * Load the group enum for comment visibility.
     *
     * @return array<int|string,string> Group id => name, with 0 => "visible to everyone" prepended
     */
    private function loadCommentGroupEnum(string $uid): array
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

        return [0 => _("This comment is visible to everyone")] + $grouplist;
    }

    private function redirectToDefault(string $uid): ResponseInterface
    {
        $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
        return $this->redirect($this->urlGenerator->defaultViewUrl($defaultView));
    }
}
