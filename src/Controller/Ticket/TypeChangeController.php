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
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\View\PrevNextView;
use Horde_Form_Renderer;
use Horde_Notification_Handler;
use Horde_PageOutput;
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
use Whups_Form_SetTypeStepOne;
use Whups_Form_SetTypeStepTwo;
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

        // Permission check: 'update' required.
        if (!$this->permissions->hasQueuePermission($details['queue'], 'update')) {
            $this->notification->push(_("Permission Denied"), 'horde.error');
            return $this->redirectToDefault($webroot, $uid);
        }

        $vars = Horde_Variables::getDefaultVariables();
        $vars->set('id', $id);
        foreach ($details as $varname => $value) {
            $vars->add($varname, $value);
        }

        $formName = $vars->get('formname');
        $action = $vars->get('action');

        // RSS feed link.
        $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
        $this->pageOutput->addLinkTag([
            'href' => $rssUrl,
            'title' => '[#' . $id . '] ' . $ticket->get('summary'),
        ]);

        // Process wizard step.
        $step = $this->processWizardStep($vars, $formName, $action, $ticket, $id);
        if ($step instanceof ResponseInterface) {
            return $step;
        }

        // Prev/next navigation.
        $ticketList = $this->session->getScoped('whups', 'tickets') ?? [];
        $lastSearch = (string) ($this->session->getScoped('whups', 'last_search') ?? '');
        $prevNext = new PrevNextView((int) $id, $ticketList, $lastSearch, $this->urlGenerator);

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);

        $title = sprintf(_("Set Type for %s"), '[#' . $id . '] ' . $ticket->get('summary'));

        $html = $this->renderChrome($title, function () use (
            $vars,
            $step,
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
            echo $tabs->render('type');

            // Wizard forms.
            $this->renderWizardStep($step, $vars, $webroot, $id);
        });

        return $this->htmlResponse($html);
    }

    /**
     * Process the wizard step. Returns the next step string or a redirect response.
     */
    private function processWizardStep(
        Horde_Variables $vars,
        ?string $formName,
        ?string $action,
        Whups_Ticket $ticket,
        string $id,
    ): string|ResponseInterface {
        if ($formName == 'whups_form_settypestepone') {
            $form = new Whups_Form_SetTypeStepOne($vars);
            if ($form->validate($vars)) {
                return 'st2';
            }
            return 'st';
        }

        if ($formName == 'whups_form_settypesteptwo') {
            $form = new Whups_Form_SetTypeStepTwo($vars);
            if ($form->validate($vars)) {
                $info = $form->getInfo($vars);

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
            } else {
                $this->notification->push(var_export($form->getErrors(), true), 'horde.error');
            }
            return 'st2';
        }

        return $action ?? '';
    }

    /**
     * Render the appropriate wizard step forms.
     */
    private function renderWizardStep(
        string $action,
        Horde_Variables $vars,
        string $webroot,
        string $id,
    ): void {
        $r = new Horde_Form_Renderer();
        $actionUrl = new Horde_Url($webroot . '/ticket/' . $id . '/type');

        switch ($action) {
            case 'st2':
                $form1 = new Whups_Form_SetTypeStepOne($vars, _("Set Type - Step 1"));
                $form2 = new Whups_Form_SetTypeStepTwo($vars, _("Set Type - Step 2"));
                $form1->renderInactive($r, $vars);
                echo '<br />';
                $form2->renderActive($r, $vars, $actionUrl, 'post');
                break;

            default:
                $form1 = new Whups_Form_SetTypeStepOne($vars, _("Set Type - Step 1"));
                $form1->renderActive($r, $vars, $actionUrl, 'post');
                break;
        }
    }

    private function redirectToDefault(string $webroot, string $uid): ResponseInterface
    {
        $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
        return $this->redirect($webroot . '/' . $defaultView);
    }
}
