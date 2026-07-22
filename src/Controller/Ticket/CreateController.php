<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Multi-step ticket creation wizard.
 *
 * Copyright 2001-2026 Robert E. Coyle <robertecoyle@hotmail.com>
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Controller\Ticket;

use Horde;
use Horde\Core\Session\SessionAccess;
use Horde\Form\V3\HtmlRenderer;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Domain\StateCategory;
use Horde\Whups\Form\Ticket\CreateTicketForm;
use Horde\Whups\Service\TicketCreationService;
use Horde\Whups\Service\TopbarSearch;
use Horde\Whups\Service\UrlGenerator;
use Horde_Group_Base;
use Horde_Group_Exception;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;

class CreateController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly SessionAccess $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly UrlGenerator $urlGenerator,
        private readonly Horde_Group_Base $groupService,
        private readonly TicketCreationService $ticketCreation,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actionUrl = $this->urlGenerator->urlFor('TicketCreate');
        $uid = $this->registry->getAuth() ?: '';
        $isGuest = !$uid;
        $conf = $GLOBALS['conf'] ?? [];

        $formVars = ($request->getParsedBody() ?? []) + $request->getQueryParams();
        $formname = $formVars['formname'] ?? '';

        // Load queue list for step 1.
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::EDIT,
        );

        // Step 1: queue selection.
        $form = new CreateTicketForm($formVars, 1, $queues);
        if (!$form->validate()) {
            return $this->renderWizard($actionUrl, $form);
        }

        // Step 1 valid — load data for step 2.
        $queue = (int) ($formVars['queue'] ?? 0);
        $types = $this->driver->getTypes($queue);
        $queueInfo = $this->driver->getQueue($queue);
        $versioned = !empty($queueInfo['versioned']);
        $versions = $versioned ? $this->driver->getVersions($queue) : null;
        $defaultType = $this->driver->getDefaultType($queue);

        // Step 2: type/version selection.
        $form = new CreateTicketForm(
            $formVars,
            2,
            $queues,
            $types,
            $defaultType ? (int) $defaultType : null,
            $versions,
        );
        if (!$form->validate()) {
            return $this->renderWizard($actionUrl, $form);
        }

        // Step 2 valid — load data for step 3.
        $type = (int) ($formVars['type'] ?? 0);
        $states = $this->loadStates($type, !$isGuest);
        $defaultState = $this->driver->getDefaultState($type);
        $priorities = $this->driver->getPriorities($type);
        $defaultPriority = $this->driver->getDefaultPriority($type);
        $attributes = $this->driver->getAttributesForType($type);
        $canSetRequester = Whups::hasPermission($queue, 'queue', 'requester');
        $groups = $isGuest ? [] : $this->loadGroupEnum($uid);
        $useCaptcha = $isGuest && !empty($conf['guests']['captcha']);
        $captchaFont = $conf['guests']['figlet_font'] ?? null;

        // CAPTCHA: generate new text on first display, use existing for validation.
        $form3Name = 'horde_whups_form_ticket_createticketform';
        $form3IsActive = $formname === $form3Name;
        $captchaText = $useCaptcha ? Whups::getCAPTCHA(!$form3IsActive) : null;

        // Step 3: ticket details.
        $form = new CreateTicketForm(
            $formVars,
            3,
            $queues,
            $types,
            $defaultType ? (int) $defaultType : null,
            $versions,
            $states,
            $defaultState ? (int) $defaultState : null,
            $priorities,
            $defaultPriority ? (int) $defaultPriority : null,
            $attributes,
            $isGuest,
            $canSetRequester,
            $captchaText,
            $captchaFont,
            $groups,
        );
        $valid3 = $form->validate();

        if (!$valid3) {
            // Regenerate CAPTCHA on validation failure.
            if ($form3IsActive && $useCaptcha) {
                $captchaText = Whups::getCAPTCHA(true);
                unset($formVars['captcha']);
                $form = new CreateTicketForm(
                    $formVars,
                    3,
                    $queues,
                    $types,
                    $defaultType ? (int) $defaultType : null,
                    $versions,
                    $states,
                    $defaultState ? (int) $defaultState : null,
                    $priorities,
                    $defaultPriority ? (int) $defaultPriority : null,
                    $attributes,
                    $isGuest,
                    $canSetRequester,
                    $captchaText,
                    $captchaFont,
                    $groups,
                );
            }
            return $this->renderWizard($actionUrl, $form);
        }

        // Check whether step 4 (owner assignment) is needed.
        $doAssignForm = !$isGuest
            && $this->driver->isCategory(StateCategory::Assigned->value, $formVars['state'] ?? null);

        if ($doAssignForm) {
            // Preserve attachment upload from step 3 to step 4.
            if ($form3IsActive) {
                $this->preserveAttachment($formVars, $form);
            }

            $ownerData = $this->loadOwnerData($queue);

            // Step 4: owner assignment.
            $form = new CreateTicketForm(
                $formVars,
                4,
                $queues,
                $types,
                $defaultType ? (int) $defaultType : null,
                $versions,
                $states,
                $defaultState ? (int) $defaultState : null,
                $priorities,
                $defaultPriority ? (int) $defaultPriority : null,
                $attributes,
                $isGuest,
                $canSetRequester,
                $captchaText,
                $captchaFont,
                $groups,
                $ownerData['users'],
                $ownerData['groups'],
            );
            $valid4 = $form->isSubmitted() && $form->validate();

            if (!$valid4) {
                return $this->renderWizard($actionUrl, $form);
            }
        }

        // All steps valid — collect info and create ticket.
        $info = $form->getInfo();

        return $this->processSubmission($info, $actionUrl);
    }

    /**
     * Process validated form data and create the ticket.
     */
    private function processSubmission(array $info, string $actionUrl): ResponseInterface
    {
        try {
            $ticket = $this->ticketCreation->createTicket(
                $info,
                $this->registry->getAuth(),
            );
        } catch (Whups_Exception $e) {
            Horde::log($e, 'ERR');
            $this->notification->push(
                sprintf(_("Adding your ticket failed: %s."), $e->getMessage()),
                'horde.error',
            );
            return $this->redirect($actionUrl);
        }

        $this->notification->push(
            sprintf(
                _("Your ticket ID is %s. An appropriate person has been notified of this request."),
                $ticket->getId(),
            ),
            'horde.success',
        );

        $ticketUrl = $this->urlGenerator->absoluteUrlFor('TicketView', ['id' => (int) $ticket->getId()]);
        return $this->redirect($ticketUrl);
    }

    /**
     * Render the wizard at the current step.
     */
    private function renderWizard(
        string $actionUrl,
        CreateTicketForm $form,
    ): ResponseInterface {
        $html = $this->renderChrome(_("New Ticket"), function () use (
            $actionUrl,
            $form,
        ) {
            $this->topbarSearch->apply();

            $renderer = new HtmlRenderer();
            echo $renderer->renderMixed($form, $actionUrl, 'post');
        });

        return $this->htmlResponse($html);
    }

    /**
     * Load states available for ticket creation.
     *
     * Guests see only 'unconfirmed' states. Authenticated users also
     * see 'new' and 'assigned' states.
     */
    private function loadStates(int $type, bool $authenticated): array
    {
        $states = $this->driver->getStates($type, StateCategory::Unconfirmed->value);

        if ($authenticated) {
            $extra = $this->driver->getStates(
                $type,
                [StateCategory::New->value, StateCategory::Assigned->value],
            );
            if (is_array($extra)) {
                $states = $states + $extra;
            }
        }

        return $states;
    }

    /**
     * Load user and group owner lists for step 4.
     *
     * @return array{users: array<string,string>, groups: array<string,string>}
     */
    private function loadOwnerData(int $queue): array
    {
        $conf = $GLOBALS['conf'] ?? [];

        $users = $this->driver->getQueueUsers($queue);
        $fUsers = [];
        foreach ($users as $user) {
            $fUsers['user:' . $user] = Whups::formatUser($user);
        }
        if ($fUsers) {
            asort($fUsers);
        }

        $fGroups = [];
        try {
            $assignAllGroups = !empty($conf['prefs']['assign_all_groups']);
            $mygroups = $this->groupService->listAll(
                $assignAllGroups ? null : $this->registry->getAuth(),
            );
            asort($mygroups);
            foreach (array_keys($mygroups) as $gid) {
                $fGroups['group:' . $gid] = $this->groupService->getName($gid);
            }
        } catch (Horde_Group_Exception $e) {
            // Group service unavailable — skip group owners.
        }

        return ['users' => $fUsers, 'groups' => $fGroups];
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

        return [0 => _("This comment is visible to everyone")] + $mygroups;
    }

    /**
     * Preserve an uploaded file attachment from step 3 for step 4.
     *
     * Moves the uploaded file to a temp location and stores the path
     * in the session. The filename is passed to step 4 via formVars.
     */
    private function preserveAttachment(array &$formVars, CreateTicketForm $form): void
    {
        $info = $form->getInfo();
        if (empty($info['newattachment']['name'])) {
            return;
        }

        $fileName = $info['newattachment']['name'];
        $tmpFilePath = Horde::getTempFile('whups', false);
        if (move_uploaded_file($info['newattachment']['tmp_name'], $tmpFilePath)) {
            $this->session->setScoped('whups', 'deferred_attachment/' . $fileName, $tmpFilePath);
            $formVars['deferred_attachment'] = $fileName;
        }
    }
}
