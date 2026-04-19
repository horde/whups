<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Add a comment to a ticket.
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
use Horde\Whups\Form\Ticket\AddCommentForm;
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
use Horde\Core\Session\HordeSession;
use Horde_Text_Flowed;
use Horde_Variables;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;

class CommentController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Perms_Base $perms,
        private readonly Horde_Registry $registry,
        private readonly HordeSession $session,
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

        $isGuest = !$this->registry->getAuth();
        $conf = $GLOBALS['conf'] ?? [];
        $useCaptcha = $isGuest && !empty($conf['guests']['captcha']);
        $captchaFont = $conf['guests']['figlet_font'] ?? null;

        // Build form vars from PSR-7 request + route params.
        $formVars = ($request->getParsedBody() ?? []) + $request->getQueryParams();
        $formVars['id'] = $id;

        // Legacy Horde_Variables still needed for tabs/TicketDetails.
        $vars = Horde_Variables::getDefaultVariables();
        $vars->set('id', $id);
        foreach ($details as $varname => $value) {
            $vars->add($varname, $value);
        }

        // If replying to a specific transaction, pre-fill the comment.
        $this->prefillQuotedComment($formVars, $vars, $ticket);

        // Load domain data.
        $groups = $this->loadGroupEnum($uid);
        $captchaText = $useCaptcha ? Whups::getCAPTCHA(!$this->isFormSubmitted($formVars)) : null;

        $title = sprintf(_("Comment on %s"), '[#' . $id . '] ' . $ticket->get('summary'));
        $commentForm = new AddCommentForm($formVars, $isGuest, $captchaText, $captchaFont, $groups, $title);

        // Handle form submission.
        if ($commentForm->isSubmitted()) {
            if ($commentForm->validate()) {
                $info = $commentForm->getInfo();

                if (!empty($info['newcomment'])) {
                    $ticket->change('comment', $info['newcomment']);
                }
                if (!empty($info['user_email'])) {
                    $ticket->change('comment-email', $info['user_email']);
                }
                if (!empty($info['newattachment']['name'])) {
                    $ticket->change('attachment', [
                        'name' => $info['newattachment']['name'],
                        'tmp_name' => $info['newattachment']['tmp_name'],
                    ]);
                }
                if (!empty($info['add_watch'])) {
                    $this->driver->addListener($ticket->getId(), '**' . $info['user_email']);
                }
                if (!empty($info['group'])) {
                    $ticket->change('comment-perms', $info['group']);
                }

                try {
                    $ticket->commit();
                    $this->notification->push(_("Comment added"), 'horde.success');
                    return $this->redirect(
                        $this->urlGenerator->urlFor('TicketView', ['id' => (int) $id]),
                    );
                } catch (Whups_Exception $e) {
                    $this->notification->push($e->getMessage(), 'horde.error');
                }
            }

            // Validation failed — regenerate CAPTCHA for next render.
            if ($useCaptcha) {
                $captchaText = Whups::getCAPTCHA(true);
                unset($formVars['captcha']);
                $commentForm = new AddCommentForm($formVars, $isGuest, $captchaText, $captchaFont, $groups, $title);
            }
        }

        // RSS feed link.
        $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
        $this->pageOutput->addLinkTag([
            'href' => $rssUrl,
            'title' => '[#' . $id . '] ' . $ticket->get('summary'),
        ]);

        // Prev/next navigation.
        $ticketList = $this->session->getScoped('whups', 'tickets') ?? [];
        $lastSearch = (string) ($this->session->getScoped('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $html = $this->renderChrome($title, function () use (
            $commentForm,
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
            echo $tabs->render('comment');

            // Comment form.
            $renderer = new HtmlRenderer();
            echo $renderer->render($commentForm, $webroot . '/ticket/' . $id . '/comment', 'post');
        });

        return $this->htmlResponse($html);
    }

    /**
     * Check if the form was submitted (without full form validation).
     */
    private function isFormSubmitted(array $formVars): bool
    {
        // V3 forms include their name as a hidden field.
        // If the form name token is present, the form was submitted.
        return isset($formVars['submitbutton'])
            || isset($formVars['formname']);
    }

    /**
     * If a transaction ID is given, pre-fill the comment with the quoted
     * original (respecting private comment permissions).
     */
    private function prefillQuotedComment(array &$formVars, Horde_Variables $vars, Whups_Ticket $ticket): void
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

        // Check if any change in this transaction is private and inaccessible.
        $authUser = $this->registry->getAuth();
        foreach ($history[$tid]['changes'] as $change) {
            if (!empty($change['private'])) {
                if (!$this->perms->hasPermission(
                    'whups:comments:' . $change['value'],
                    $authUser,
                    Horde_Perms::READ,
                )) {
                    return;
                }
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
     * Load the group enum for comment visibility (admin or hiddenComments permission).
     *
     * @return array<int|string,string> Group id => name, with 0 => "visible to everyone" prepended
     */
    private function loadGroupEnum(string $uid): array
    {
        if (!$uid) {
            return [];
        }

        $isAdmin = $this->registry->isAdmin(['permission' => 'whups:admin']);
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

        return [0 => _("This comment is visible to everyone")] + $grouplist;
    }
}
