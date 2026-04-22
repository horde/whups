<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Whups administration.
 *
 * Handles queue, type, matrix, and reminder administration.
 * Forms use Horde\Form\V3 and read directly from PSR-7 ServerRequestInterface.
 *
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller;

use Horde\Form\V3\HtmlRenderer;
use Horde\Whups\Form\Admin\AddAttributeForm;
use Horde\Whups\Form\Admin\AddPriorityForm;
use Horde\Whups\Form\Admin\AddQueueForm;
use Horde\Whups\Form\Admin\AddReplyForm;
use Horde\Whups\Form\Admin\AddStateForm;
use Horde\Whups\Form\Admin\AddTypeForm;
use Horde\Whups\Form\Admin\AddUserForm;
use Horde\Whups\Form\Admin\AddVersionForm;
use Horde\Whups\Form\Admin\CloneTypeForm;
use Horde\Whups\Form\Admin\DefaultPriorityForm;
use Horde\Whups\Form\Admin\DefaultStateForm;
use Horde\Whups\Form\Admin\DeleteAttributeForm;
use Horde\Whups\Form\Admin\DeletePriorityForm;
use Horde\Whups\Form\Admin\DeleteQueueForm;
use Horde\Whups\Form\Admin\DeleteReplyForm;
use Horde\Whups\Form\Admin\DeleteStateForm;
use Horde\Whups\Form\Admin\DeleteTypeForm;
use Horde\Whups\Form\Admin\DeleteVersionForm;
use Horde\Whups\Form\Admin\EditAttributeStepOneForm;
use Horde\Whups\Form\Admin\EditAttributeStepTwoForm;
use Horde\Whups\Form\Admin\EditPriorityStepOneForm;
use Horde\Whups\Form\Admin\EditPriorityStepTwoForm;
use Horde\Whups\Form\Admin\EditQueueStepOneForm;
use Horde\Whups\Form\Admin\EditQueueStepTwoForm;
use Horde\Whups\Form\Admin\EditReplyStepOneForm;
use Horde\Whups\Form\Admin\EditReplyStepTwoForm;
use Horde\Whups\Form\Admin\EditStateStepOneForm;
use Horde\Whups\Form\Admin\EditStateStepTwoForm;
use Horde\Whups\Form\Admin\EditTypeStepOneForm;
use Horde\Whups\Form\Admin\EditTypeStepTwoForm;
use Horde\Whups\Form\Admin\EditUserForm;
use Horde\Whups\Form\Admin\EditVersionStepOneForm;
use Horde\Whups\Form\Admin\EditVersionStepTwoForm;
use Horde\Whups\Form\Admin\SendReminderForm;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\ReminderSender;
use Horde\Whups\Service\TopbarSearch;
use Horde\Whups\Service\UrlGenerator;
use Horde_Auth_Base;
use Horde_Auth_Exception;
use Horde_Core_Ui_Tabs;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde_Url;
use Horde_Variables;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Horde_Exception;

class AdminController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly PermissionChecker $permissions,
        private readonly TopbarSearch $topbarSearch,
        private readonly ReminderSender $reminderSender,
        private readonly UrlGenerator $urlGenerator,
        private readonly Horde_Auth_Base $auth,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->isAdmin()) {
            $this->notification->push(_("Permission denied."), 'horde.error');
            return $this->redirect($this->urlGenerator->getWebroot());
        }

        $action = $request->getQueryParams()['action'] ?? 'queue';
        $adminUrl = $this->urlGenerator->getWebroot() . '/admin/';
        $renderer = new HtmlRenderer();

        $formHtml = match ($action) {
            'queue' => $this->handleQueueSubmission($request, $adminUrl, $renderer),
            'type' => $this->handleTypeSubmission($request, $adminUrl, $renderer),
            'mtmatrix' => $this->handleMatrixSubmission($request, $adminUrl),
            'reminders' => $this->handleRemindersSubmission($request, $adminUrl, $renderer),
            default => null,
        };

        $html = $this->renderChrome(_("Administration"), function () use (
            $action,
            $adminUrl,
            $formHtml,
            $renderer,
        ) {
            $this->topbarSearch->apply();
            echo $this->renderTabs($action, $adminUrl);

            if ($formHtml !== null) {
                echo $formHtml;
            } else {
                echo $this->renderDefaultTab($action, $adminUrl, $renderer);
            }
        });

        return $this->htmlResponse($html);
    }

    private function renderTabs(string $action, string $adminUrl): string
    {
        $tabVars = new Horde_Variables(['action' => $action]);
        $tabs = new Horde_Core_Ui_Tabs('action', $tabVars);
        $hUrl = new Horde_Url($adminUrl);
        $tabs->addTab(_("_Edit Queues"), $hUrl, 'queue');
        $tabs->addTab(_("Edit _Types"), $hUrl, 'type');
        $tabs->addTab(_("Queue/Type Matri_x"), $hUrl, 'mtmatrix');
        $tabs->addTab(_("Sen_d Reminders"), $hUrl, 'reminders');

        return $tabs->render($action);
    }

    private function handleQueueSubmission(
        ServerRequestInterface $request,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): ?string {
        // Sub-entity handlers: versions, users (mirroring type tab's sub-actions).
        $query = $request->getQueryParams();
        $subaction = $query['subaction'] ?? '';
        $subQueueId = (int) ($query['queue'] ?? 0);

        if ($subaction && $subQueueId) {
            $subUrl = $adminUrl . '?action=queue&subaction=' . $subaction . '&queue=' . $subQueueId;
            return match ($subaction) {
                'editversions' => $this->handleVersionSubmission($request, $subUrl, $adminUrl, $renderer, $subQueueId),
                'editusers' => $this->handleUserSubmission($request, $subUrl, $adminUrl, $renderer, $subQueueId),
                default => null,
            };
        }

        // AddQueue
        $form = new AddQueueForm($request, $this->urlGenerator->getWebroot());
        if ($form->isSubmitted()) {
            return $this->processAddQueue($form, $adminUrl, $renderer);
        }

        // EditQueueStepOne
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::EDIT,
        );
        $canDelete = ($this->registry->hasMethod('tickets/listQueues') == $this->registry->getApp());
        $form = new EditQueueStepOneForm($request, $queues, $canDelete);
        if ($form->isSubmitted()) {
            return $this->processEditQueueStepOne($form, $adminUrl, $renderer);
        }

        // EditQueueStepTwo / DeleteQueue need a queueId from the POST body.
        $body = $request->getParsedBody() ?? [];
        $queueId = (int) ($body['queue'] ?? 0);
        if ($queueId) {
            $form = $this->buildEditQueueStepTwoForm($request, $queueId);
            if ($form->isSubmitted()) {
                return $this->processEditQueueStepTwo($form, $adminUrl, $renderer);
            }

            $queueInfo = $this->driver->getQueue($queueId);
            $form = new DeleteQueueForm(
                $request,
                $queueInfo['name'],
                $queueInfo['description'],
            );
            if ($form->isSubmitted()) {
                return $this->processDeleteQueue($form, $adminUrl, $renderer);
            }
        }

        return null;
    }

    private function processAddQueue(
        AddQueueForm $form,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $adminUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $queueId = (int) $this->driver->addQueue(
                $info['name'],
                $info['description'],
                $info['slug'] ?? '',
                $info['email'] ?? '',
            );

            $this->notification->push(
                sprintf(_("The queue \"%s\" has been created."), $info['name']),
                'horde.success',
            );

            $form2 = $this->buildEditQueueStepTwoForm(['queue' => $queueId], $queueId);
            return $renderer->render($form2, $adminUrl, 'post');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error creating the queue:") . ' ' . $e->getMessage(),
                'horde.error',
            );
            return $renderer->render($form, $adminUrl, 'post');
        }
    }

    private function processEditQueueStepOne(
        EditQueueStepOneForm $form,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $adminUrl, 'post');
        }

        $info = $form->getInfo();
        $queueId = $info['queue'];

        $button = $form->getClickedButton();
        if ($button === _("Delete Queue")) {
            $queueInfo = $this->driver->getQueue($queueId);
            $deleteForm = new DeleteQueueForm(
                ['queue' => $queueId],
                $queueInfo['name'],
                $queueInfo['description'],
            );
            return $renderer->render($deleteForm, $adminUrl, 'post');
        }

        $editForm = $this->buildEditQueueStepTwoForm(['queue' => $queueId], $queueId);
        return $renderer->render($editForm, $adminUrl, 'post');
    }

    private function processEditQueueStepTwo(
        EditQueueStepTwoForm $form,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $adminUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updateQueue(
                $info['queue'],
                $info['name'],
                $info['description'],
                $info['types'] ?? [],
                $info['versioned'] ?? 0,
                $info['slug'] ?? '',
                $info['email'] ?? '',
                $info['default'] ?? null,
            );

            $this->notification->push(_("The queue has been modified."), 'horde.success');

            $renderer->setMode('inactive');
            return $renderer->render($form, $adminUrl, 'post');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error editing the queue:") . ' ' . $e->getMessage(),
                'horde.error',
            );
            return $renderer->render($form, $adminUrl, 'post');
        }
    }

    private function processDeleteQueue(
        DeleteQueueForm $form,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $adminUrl, 'post');
        }

        $info = $form->getInfo();

        if (($info['yesno'] ?? 0) == 1) {
            try {
                $this->driver->deleteQueue($info['queue']);
                $this->notification->push(_("The queue has been deleted."), 'horde.success');
            } catch (Horde_Exception $e) {
                $this->notification->push(
                    _("There was an error deleting the queue:") . ' ' . $e->getMessage(),
                    'horde.error',
                );
            }
        } else {
            $this->notification->push(_("The queue was not deleted."), 'horde.message');
        }

        return $this->renderDefaultTab('queue', $adminUrl, $renderer);
    }

    /**
     * Build an EditQueueStepTwoForm with all data fetched from collaborators.
     */
    private function buildEditQueueStepTwoForm(
        ServerRequestInterface|array $vars,
        int $queueId,
    ): EditQueueStepTwoForm {
        $webroot = $this->urlGenerator->getWebroot();
        $queueInfo = $this->driver->getQueue($queueId);

        $allTypes = $this->driver->getAllTypes();
        $queueTypeIds = array_keys($this->driver->getTypes($queueId));
        $defaultType = $this->driver->getDefaultType($queueId) ?: null;

        $users = $this->driver->getQueueUsers($queueId);
        $formattedUsers = [];
        foreach ($users as $user) {
            $formattedUsers[$user] = Whups::formatUser($user);
        }
        asort($formattedUsers);

        $versionEditUrl = null;
        if ($this->registry->hasMethod('tickets/listVersions') == $this->registry->getApp()) {
            $versionEditUrl = $webroot . '/admin/?action=queue&subaction=editversions&queue=' . $queueId;
        }

        $userEditUrl = $webroot . '/admin/?action=queue&subaction=editusers&queue=' . $queueId;

        $permsEditUrl = null;
        if ($this->registry->isAdmin(['permission' => 'whups:admin', 'permlevel' => Horde_Perms::EDIT])) {
            $permsEditUrl = $this->registry->get('webroot', 'horde')
                . '/admin/perms/edit.php?category=' . urlencode('whups:queues:' . $queueId)
                . '&autocreate=1';
        }

        return new EditQueueStepTwoForm(
            $vars,
            $queueInfo,
            $allTypes,
            $queueTypeIds,
            $defaultType,
            $formattedUsers,
            $webroot,
            $queueId,
            $versionEditUrl,
            $permsEditUrl,
            $userEditUrl,
        );
    }

    // ── Version management (sub-entity of queue) ─────────────────────

    private function handleVersionSubmission(
        ServerRequestInterface $request,
        string $subUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        // AddVersion
        $form = new AddVersionForm($request);
        if ($form->isSubmitted()) {
            return $this->processAddVersion($form, $subUrl, $renderer, $queueId);
        }

        // EditVersionStepOne
        $versions = $this->driver->getVersions($queueId, true);
        $form = new EditVersionStepOneForm($request, $versions);
        if ($form->isSubmitted()) {
            return $this->processEditVersionStepOne($form, $subUrl, $renderer, $queueId);
        }

        // EditVersionStepTwo / DeleteVersion need a versionId from POST
        $body = $request->getParsedBody() ?? [];
        $versionId = (int) ($body['version'] ?? 0);
        if ($versionId) {
            $versionInfo = $this->driver->getVersion($versionId);

            $form = new EditVersionStepTwoForm($request, $versionInfo);
            if ($form->isSubmitted()) {
                return $this->processEditVersionStepTwo($form, $subUrl, $renderer, $queueId);
            }

            $form = new DeleteVersionForm(
                $request,
                $versionInfo['name'],
                $versionInfo['description'],
            );
            if ($form->isSubmitted()) {
                return $this->processDeleteVersion($form, $subUrl, $renderer, $queueId);
            }
        }

        return $this->renderVersionForms($subUrl, $renderer, $queueId);
    }

    private function processAddVersion(
        AddVersionForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->addVersion(
                $queueId,
                $info['name'],
                $info['description'],
                !empty($info['active']),
            );
            $this->notification->push(
                sprintf(_("The version \"%s\" has been created."), $info['name']),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
        }

        return $this->renderVersionForms($subUrl, $renderer, $queueId);
    }

    private function processEditVersionStepOne(
        EditVersionStepOneForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $versionId = (int) $info['version'];
        $versionInfo = $this->driver->getVersion($versionId);
        $button = $form->getClickedButton();

        if ($button === _("Delete Version")) {
            $deleteForm = new DeleteVersionForm(
                ['queue' => $queueId, 'version' => $versionId],
                $versionInfo['name'],
                $versionInfo['description'],
            );
            return $renderer->render($deleteForm, $subUrl, 'post');
        }

        $editForm = new EditVersionStepTwoForm(
            ['queue' => $queueId, 'version' => $versionId],
            $versionInfo,
        );
        return $renderer->render($editForm, $subUrl, 'post');
    }

    private function processEditVersionStepTwo(
        EditVersionStepTwoForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updateVersion(
                (int) $info['version'],
                $info['name'],
                $info['description'],
                !empty($info['active']),
            );
            $this->notification->push(
                sprintf(_("The version \"%s\" has been modified."), $info['name']),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
        }

        return $this->renderVersionForms($subUrl, $renderer, $queueId);
    }

    private function processDeleteVersion(
        DeleteVersionForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        if (!empty($info['yesno'])) {
            try {
                $this->driver->deleteVersion((int) $info['version']);
                $this->notification->push(
                    _("The version has been deleted."),
                    'horde.success',
                );
            } catch (Whups_Exception $e) {
                $this->notification->push($e->getMessage(), 'horde.error');
            }
        } else {
            $this->notification->push(_("The version was not deleted."), 'horde.message');
        }

        return $this->renderVersionForms($subUrl, $renderer, $queueId);
    }

    private function renderVersionForms(
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        $output = [];

        $versions = $this->driver->getVersions($queueId, true);
        if ($versions) {
            $editForm = new EditVersionStepOneForm(['queue' => $queueId], $versions);
            $output[] = $renderer->render($editForm, $subUrl, 'post');
        }

        $addForm = new AddVersionForm(['queue' => $queueId]);
        $output[] = '<br />';
        $output[] = $renderer->render($addForm, $subUrl, 'post');

        return implode("\n", $output);
    }

    // ── User management (sub-entity of queue) ────────────────────────

    private function handleUserSubmission(
        ServerRequestInterface $request,
        string $subUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        // AddUser
        $availableUsers = $this->buildAvailableUserList($queueId);
        $canList = $this->auth->hasCapability('list');
        $form = new AddUserForm($request, $availableUsers, $canList);
        if ($form->isSubmitted()) {
            return $this->processAddUser($form, $subUrl, $renderer, $queueId);
        }

        // EditUser (remove)
        $queueUsers = $this->buildFormattedQueueUsers($queueId);
        $form = new EditUserForm($request, $queueUsers);
        if ($form->isSubmitted()) {
            return $this->processRemoveUser($form, $subUrl, $renderer, $queueId);
        }

        return $this->renderUserForms($subUrl, $renderer, $queueId);
    }

    private function processAddUser(
        AddUserForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $users = (array) ($info['user'] ?? []);

        foreach ($users as $userId) {
            try {
                $this->driver->addQueueUser($queueId, $userId);
                $this->notification->push(
                    sprintf(_("The user \"%s\" has been added to the queue."), Whups::formatUser($userId)),
                    'horde.success',
                );
            } catch (Whups_Exception $e) {
                $this->notification->push($e->getMessage(), 'horde.error');
            }
        }

        return $this->renderUserForms($subUrl, $renderer, $queueId);
    }

    private function processRemoveUser(
        EditUserForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $userId = (string) ($info['user'] ?? '');

        if ($userId !== '') {
            try {
                $this->driver->removeQueueUser($queueId, $userId);
                $this->notification->push(
                    sprintf(_("The user \"%s\" has been removed from the queue."), Whups::formatUser($userId)),
                    'horde.success',
                );
            } catch (Whups_Exception $e) {
                $this->notification->push($e->getMessage(), 'horde.error');
            }
        }

        return $this->renderUserForms($subUrl, $renderer, $queueId);
    }

    private function renderUserForms(
        string $subUrl,
        HtmlRenderer $renderer,
        int $queueId,
    ): string {
        $output = [];

        $queueUsers = $this->buildFormattedQueueUsers($queueId);
        $editForm = new EditUserForm(['queue' => $queueId], $queueUsers);
        $output[] = $renderer->render($editForm, $subUrl, 'post');

        $availableUsers = $this->buildAvailableUserList($queueId);
        $canList = $this->auth->hasCapability('list');
        $addForm = new AddUserForm(['queue' => $queueId], $availableUsers, $canList);
        $output[] = '<br />';
        $output[] = $renderer->render($addForm, $subUrl, 'post');

        return implode("\n", $output);
    }

    /**
     * Build a formatted user list for the queue (user id => display name).
     *
     * @return array<string,string>
     */
    private function buildFormattedQueueUsers(int $queueId): array
    {
        $users = $this->driver->getQueueUsers($queueId);
        $formatted = [];
        foreach ($users as $user) {
            $formatted[$user] = Whups::formatUser($user);
        }
        if ($formatted) {
            asort($formatted);
        }
        return $formatted;
    }

    /**
     * Build a list of users not yet assigned to this queue.
     *
     * @return array<string,string> User id => display name
     */
    private function buildAvailableUserList(int $queueId): array
    {
        if (!$this->auth->hasCapability('list')) {
            return [];
        }

        $current = $this->driver->getQueueUsers($queueId);

        try {
            $allUsers = $this->auth->listNames();
        } catch (Horde_Auth_Exception $e) {
            return [];
        }

        $available = [];
        foreach ($allUsers as $user => $name) {
            if (!in_array($user, $current)) {
                $available[$user] = $name;
            }
        }
        return $available;
    }

    private function renderDefaultTab(
        string $action,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        return match ($action) {
            'queue' => $this->renderQueueTab($adminUrl, $renderer),
            'type' => $this->renderTypeTab($adminUrl, $renderer),
            'mtmatrix' => $this->renderMatrixTab($adminUrl),
            'reminders' => $this->renderRemindersTab($adminUrl, $renderer),
            default => $this->renderQueueTab($adminUrl, $renderer),
        };
    }

    private function renderQueueTab(string $adminUrl, HtmlRenderer $renderer): string
    {
        $output = [];

        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::EDIT,
        );
        if ($queues) {
            $canDelete = ($this->registry->hasMethod('tickets/listQueues') == $this->registry->getApp());
            $editForm = new EditQueueStepOneForm([], $queues, $canDelete);
            $output[] = $renderer->render($editForm, $adminUrl, 'post');
        }

        if ($this->registry->hasMethod('tickets/listQueues') == $this->registry->getApp()) {
            $addForm = new AddQueueForm([], $this->urlGenerator->getWebroot());
            if (!empty($output)) {
                $output[] = '<br />';
            }
            $output[] = $renderer->render($addForm, $adminUrl, 'post');
        }

        return implode("\n", $output);
    }

    // ── Tab 2: Edit Types ──

    private function handleTypeSubmission(
        ServerRequestInterface $request,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): ?string {
        $query = $request->getQueryParams();
        $subaction = $query['subaction'] ?? '';
        $typeId = (int) ($query['type'] ?? 0);

        // Sub-entity handlers: states, priorities, attributes, replies
        if ($subaction && $typeId) {
            $subUrl = $adminUrl . '?action=type&subaction=' . $subaction . '&type=' . $typeId;
            return match ($subaction) {
                'editstates' => $this->handleStateSubmission($request, $subUrl, $adminUrl, $renderer, $typeId),
                'editpriorities' => $this->handlePrioritySubmission($request, $subUrl, $adminUrl, $renderer, $typeId),
                'editattributes' => $this->handleAttributeSubmission($request, $subUrl, $adminUrl, $renderer, $typeId),
                'editreplies' => $this->handleReplySubmission($request, $subUrl, $adminUrl, $renderer, $typeId),
                'createdefaultstates' => $this->processCreateDefaultStates($typeId, $adminUrl, $renderer),
                'createdefaultpriorities' => $this->processCreateDefaultPriorities($typeId, $adminUrl, $renderer),
                default => null,
            };
        }

        $typeUrl = $adminUrl . '?action=type';

        // AddType
        $form = new AddTypeForm($request);
        if ($form->isSubmitted()) {
            return $this->processAddType($form, $typeUrl, $adminUrl, $renderer);
        }

        // EditTypeStepOne
        $types = $this->driver->getAllTypes();
        $form = new EditTypeStepOneForm($request, $types);
        if ($form->isSubmitted()) {
            return $this->processEditTypeStepOne($form, $typeUrl, $adminUrl, $renderer);
        }

        // EditTypeStepTwo
        $body = $request->getParsedBody() ?? [];
        $typeId = (int) ($body['type'] ?? 0);
        if ($typeId) {
            $form = $this->buildEditTypeStepTwoForm($request, $typeId);
            if ($form->isSubmitted()) {
                return $this->processEditTypeStepTwo($form, $typeUrl, $renderer);
            }

            // CloneType
            $typeInfo = $this->driver->getType($typeId);
            $form = new CloneTypeForm(
                $request,
                $typeInfo['name'],
                $typeInfo['description'],
            );
            if ($form->isSubmitted()) {
                return $this->processCloneType($form, $typeUrl, $renderer);
            }

            // DeleteType
            $states = $this->driver->getStates($typeId);
            $priorities = $this->driver->getPriorities($typeId);
            $form = new DeleteTypeForm(
                $request,
                $typeInfo['name'],
                $typeInfo['description'],
                $states,
                $priorities,
            );
            if ($form->isSubmitted()) {
                return $this->processDeleteType($form, $typeUrl, $adminUrl, $renderer);
            }
        }

        return null;
    }

    private function processAddType(
        AddTypeForm $form,
        string $typeUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $typeUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $typeId = (int) $this->driver->addType(
                $info['name'],
                $info['description'],
            );

            $this->notification->push(
                sprintf(_("The type \"%s\" has been created."), $info['name']),
                'horde.success',
            );

            $editForm = $this->buildEditTypeStepTwoForm(['type' => $typeId], $typeId);
            return $renderer->render($editForm, $typeUrl, 'post');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error creating the type:") . ' ' . $e->getMessage(),
                'horde.error',
            );
            return $renderer->render($form, $typeUrl, 'post');
        }
    }

    private function processEditTypeStepOne(
        EditTypeStepOneForm $form,
        string $typeUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $typeUrl, 'post');
        }

        $info = $form->getInfo();
        $typeId = (int) $info['type'];
        $button = $form->getClickedButton();

        if ($button === _("Delete Type")) {
            $typeInfo = $this->driver->getType($typeId);
            $states = $this->driver->getStates($typeId);
            $priorities = $this->driver->getPriorities($typeId);
            $deleteForm = new DeleteTypeForm(
                ['type' => $typeId],
                $typeInfo['name'],
                $typeInfo['description'],
                $states,
                $priorities,
            );
            return $renderer->render($deleteForm, $typeUrl, 'post');
        }

        if ($button === _("Clone Type")) {
            $typeInfo = $this->driver->getType($typeId);
            $cloneForm = new CloneTypeForm(
                ['type' => $typeId],
                $typeInfo['name'],
                $typeInfo['description'],
            );
            return $renderer->render($cloneForm, $typeUrl, 'post');
        }

        // Default: Edit Type
        $editForm = $this->buildEditTypeStepTwoForm(['type' => $typeId], $typeId);
        return $renderer->render($editForm, $typeUrl, 'post');
    }

    private function processEditTypeStepTwo(
        EditTypeStepTwoForm $form,
        string $typeUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $typeUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updateType(
                $info['type'],
                $info['name'],
                $info['description'],
            );

            $this->notification->push(
                sprintf(_("The type \"%s\" has been modified."), $info['name']),
                'horde.success',
            );

            $renderer->setMode('inactive');
            return $renderer->render($form, $typeUrl, 'post');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error modifying the type:") . ' ' . $e->getMessage(),
                'horde.error',
            );
            return $renderer->render($form, $typeUrl, 'post');
        }
    }

    private function processCloneType(
        CloneTypeForm $form,
        string $typeUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $typeUrl, 'post');
        }

        $info = $form->getInfo();
        $sourceId = (int) $info['type'];

        try {
            $sourceInfo = $this->driver->getType($sourceId);
            $states = $this->driver->getAllStateInfo($sourceId);
            $priorities = $this->driver->getAllPriorityInfo($sourceId);
            $attributes = $this->driver->getAttributeInfoForType($sourceId);

            $newId = $this->driver->addType($info['name'], $info['description']);

            foreach ($states as $s) {
                $this->driver->addState(
                    $newId,
                    $s['state_name'],
                    $s['state_description'],
                    $s['state_category'],
                );
            }

            foreach ($priorities as $p) {
                $this->driver->addPriority(
                    $newId,
                    $p['priority_name'],
                    $p['priority_description'],
                );
            }

            foreach ($attributes as $attribute) {
                $a = $this->driver->getAttributeDesc($attribute['attribute_id']);
                $this->driver->addAttributeDesc(
                    $newId,
                    $a['name'],
                    $a['description'],
                    $a['type'],
                    $a['params'],
                    $a['required'],
                );
            }

            $this->notification->push(
                sprintf(
                    _("Successfully Cloned %s to %s."),
                    $sourceInfo['name'],
                    $info['name'],
                ),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error cloning the type:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderTypeTab($typeUrl, $renderer);
    }

    private function processDeleteType(
        DeleteTypeForm $form,
        string $typeUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $typeUrl, 'post');
        }

        $info = $form->getInfo();

        if (($info['yesno'] ?? 0) == 1) {
            try {
                $this->driver->deleteType($info['type']);
                $this->notification->push(
                    _("The type has been deleted."),
                    'horde.success',
                );
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    _("There was an error deleting the type:") . ' ' . $e->getMessage(),
                    'horde.error',
                );
            }
        } else {
            $this->notification->push(_("The type was not deleted."), 'horde.message');
        }

        return $this->renderTypeTab($typeUrl, $renderer);
    }

    private function buildEditTypeStepTwoForm(
        ServerRequestInterface|array $vars,
        int $typeId,
    ): EditTypeStepTwoForm {
        $webroot = $this->urlGenerator->getWebroot();
        $typeInfo = $this->driver->getType($typeId);
        $states = $this->driver->getStates($typeId);
        $priorities = $this->driver->getPriorities($typeId);
        $attributes = $this->driver->getAttributesForType($typeId);
        $replies = $this->driver->getReplies($typeId);

        return new EditTypeStepTwoForm(
            $vars,
            $typeInfo,
            $states,
            $priorities,
            $attributes,
            $replies,
            $webroot,
            $typeId,
        );
    }

    private function renderTypeTab(string $adminUrl, HtmlRenderer $renderer): string
    {
        $output = [];
        $typeUrl = $adminUrl . '?action=type';

        $types = $this->driver->getAllTypes();
        if ($types) {
            $editForm = new EditTypeStepOneForm([], $types);
            $output[] = $renderer->render($editForm, $typeUrl, 'post');
        }

        $addForm = new AddTypeForm([]);
        if (!empty($output)) {
            $output[] = '<br />';
        }
        $output[] = $renderer->render($addForm, $typeUrl, 'post');

        return implode("\n", $output);
    }

    // ── Tab 2 sub-entities: States ──

    private function handleStateSubmission(
        ServerRequestInterface $request,
        string $subUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        $categories = $this->driver->getCategories();

        // AddState
        $form = new AddStateForm($request, $categories);
        if ($form->isSubmitted()) {
            return $this->processAddState($form, $subUrl, $renderer, $typeId, $categories);
        }

        // EditStateStepOne
        $states = $this->driver->getStates($typeId);
        $form = new EditStateStepOneForm($request, $states);
        if ($form->isSubmitted()) {
            return $this->processEditStateStepOne($form, $subUrl, $renderer, $typeId, $categories);
        }

        // DefaultState
        $defaultStates = $this->driver->getStates($typeId, ['unconfirmed', 'new', 'assigned']);
        $currentDefault = $this->driver->getDefaultState($typeId) ?: null;
        $form = new DefaultStateForm($request, $defaultStates, $currentDefault);
        if ($form->isSubmitted()) {
            return $this->processDefaultState($form, $subUrl, $renderer, $typeId, $categories);
        }

        // EditStateStepTwo / DeleteState need a stateId from POST
        $body = $request->getParsedBody() ?? [];
        $stateId = (int) ($body['state'] ?? 0);
        if ($stateId) {
            $stateInfo = $this->driver->getState($stateId);

            $form = new EditStateStepTwoForm($request, $stateInfo, $categories);
            if ($form->isSubmitted()) {
                return $this->processEditStateStepTwo($form, $subUrl, $renderer, $typeId, $categories);
            }

            $form = new DeleteStateForm(
                $request,
                $stateInfo['name'],
                $stateInfo['description'],
            );
            if ($form->isSubmitted()) {
                return $this->processDeleteState($form, $subUrl, $renderer, $typeId, $categories);
            }
        }

        return $this->renderStateForms($subUrl, $renderer, $typeId, $categories);
    }

    private function processAddState(
        AddStateForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $categories,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->addState(
                $typeId,
                $info['name'],
                $info['description'],
                $info['category'] ?? '',
            );

            $typeName = $this->driver->getType($typeId)['name'];
            $this->notification->push(
                sprintf(_("The state \"%s\" has been added to %s."), $info['name'], $typeName),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error creating the state:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderStateForms($subUrl, $renderer, $typeId, $categories);
    }

    private function processEditStateStepOne(
        EditStateStepOneForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $categories,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $stateId = (int) $info['state'];
        $button = $form->getClickedButton();

        if ($button === _("Delete State")) {
            $stateInfo = $this->driver->getState($stateId);
            $deleteForm = new DeleteStateForm(
                ['type' => $typeId, 'state' => $stateId],
                $stateInfo['name'],
                $stateInfo['description'],
            );
            return $renderer->render($deleteForm, $subUrl, 'post');
        }

        // Default: Edit State
        $stateInfo = $this->driver->getState($stateId);
        $editForm = new EditStateStepTwoForm(
            ['type' => $typeId, 'state' => $stateId],
            $stateInfo,
            $categories,
        );
        return $renderer->render($editForm, $subUrl, 'post');
    }

    private function processEditStateStepTwo(
        EditStateStepTwoForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $categories,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updateState(
                $info['state'],
                $info['name'],
                $info['description'],
                $info['category'] ?? '',
            );
            $this->notification->push(_("The state has been modified."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error editing the state:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderStateForms($subUrl, $renderer, $typeId, $categories);
    }

    private function processDefaultState(
        DefaultStateForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $categories,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->setDefaultState($typeId, $info['state']);
            $this->notification->push(_("The default state has been set."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error setting the default state:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderStateForms($subUrl, $renderer, $typeId, $categories);
    }

    private function processDeleteState(
        DeleteStateForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $categories,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        if (($info['yesno'] ?? 0) == 1) {
            try {
                $this->driver->deleteState($info['state']);
                $this->notification->push(_("The state has been deleted."), 'horde.success');
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    _("There was an error deleting the state:") . ' ' . $e->getMessage(),
                    'horde.error',
                );
            }
        } else {
            $this->notification->push(_("The state was not deleted."), 'horde.message');
        }

        return $this->renderStateForms($subUrl, $renderer, $typeId, $categories);
    }

    private function renderStateForms(
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $categories,
    ): string {
        $output = [];

        $states = $this->driver->getStates($typeId);
        if ($states) {
            $editForm = new EditStateStepOneForm(['type' => $typeId], $states);
            $output[] = $renderer->render($editForm, $subUrl, 'post');
        }

        $defaultStates = $this->driver->getStates($typeId, ['unconfirmed', 'new', 'assigned']);
        $currentDefault = $this->driver->getDefaultState($typeId) ?: null;
        $defaultForm = new DefaultStateForm(['type' => $typeId], $defaultStates, $currentDefault);
        $output[] = '<br />';
        $output[] = $renderer->render($defaultForm, $subUrl, 'post');

        $addForm = new AddStateForm(['type' => $typeId], $categories);
        $output[] = '<br />';
        $output[] = $renderer->render($addForm, $subUrl, 'post');

        return implode("\n", $output);
    }

    private function processCreateDefaultStates(
        int $typeId,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        $conf = $GLOBALS['conf'] ?? [];
        $states = $conf['states'] ?? [];
        foreach ($states as $state) {
            if (($state['active'] ?? '') === 'active') {
                $this->driver->addState(
                    $typeId,
                    $state['name'],
                    $state['desc'] ?? '',
                    $state['category'] ?? '',
                );
            }
        }

        $this->notification->push(_("Default states have been created."), 'horde.success');

        $typeUrl = $adminUrl . '?action=type';
        $editForm = $this->buildEditTypeStepTwoForm(['type' => $typeId], $typeId);
        return $renderer->render($editForm, $typeUrl, 'post');
    }

    // ── Tab 2 sub-entities: Priorities ──

    private function handlePrioritySubmission(
        ServerRequestInterface $request,
        string $subUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        // AddPriority
        $form = new AddPriorityForm($request);
        if ($form->isSubmitted()) {
            return $this->processAddPriority($form, $subUrl, $renderer, $typeId);
        }

        // EditPriorityStepOne
        $priorities = $this->driver->getPriorities($typeId);
        $form = new EditPriorityStepOneForm($request, $priorities);
        if ($form->isSubmitted()) {
            return $this->processEditPriorityStepOne($form, $subUrl, $renderer, $typeId);
        }

        // DefaultPriority
        $currentDefault = $this->driver->getDefaultPriority($typeId) ?: null;
        $form = new DefaultPriorityForm($request, $priorities, $currentDefault);
        if ($form->isSubmitted()) {
            return $this->processDefaultPriority($form, $subUrl, $renderer, $typeId);
        }

        // EditPriorityStepTwo / DeletePriority need a priorityId from POST
        $body = $request->getParsedBody() ?? [];
        $priorityId = (int) ($body['priority'] ?? 0);
        if ($priorityId) {
            $priorityInfo = $this->driver->getPriority($priorityId);

            $form = new EditPriorityStepTwoForm($request, $priorityInfo);
            if ($form->isSubmitted()) {
                return $this->processEditPriorityStepTwo($form, $subUrl, $renderer, $typeId);
            }

            $form = new DeletePriorityForm(
                $request,
                $priorityInfo['name'],
                $priorityInfo['description'],
            );
            if ($form->isSubmitted()) {
                return $this->processDeletePriority($form, $subUrl, $renderer, $typeId);
            }
        }

        return $this->renderPriorityForms($subUrl, $renderer, $typeId);
    }

    private function processAddPriority(
        AddPriorityForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->addPriority(
                $typeId,
                $info['name'],
                $info['description'],
            );

            $typeName = $this->driver->getType($typeId)['name'];
            $this->notification->push(
                sprintf(_("The priority \"%s\" has been added to %s."), $info['name'], $typeName),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error creating the priority:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderPriorityForms($subUrl, $renderer, $typeId);
    }

    private function processEditPriorityStepOne(
        EditPriorityStepOneForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $priorityId = (int) $info['priority'];
        $button = $form->getClickedButton();

        if ($button === _("Delete Priority")) {
            $priorityInfo = $this->driver->getPriority($priorityId);
            $deleteForm = new DeletePriorityForm(
                ['type' => $typeId, 'priority' => $priorityId],
                $priorityInfo['name'],
                $priorityInfo['description'],
            );
            return $renderer->render($deleteForm, $subUrl, 'post');
        }

        // Default: Edit Priority
        $priorityInfo = $this->driver->getPriority($priorityId);
        $editForm = new EditPriorityStepTwoForm(
            ['type' => $typeId, 'priority' => $priorityId],
            $priorityInfo,
        );
        return $renderer->render($editForm, $subUrl, 'post');
    }

    private function processEditPriorityStepTwo(
        EditPriorityStepTwoForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updatePriority(
                $info['priority'],
                $info['name'],
                $info['description'],
            );
            $this->notification->push(_("The priority has been modified."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error editing the priority:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderPriorityForms($subUrl, $renderer, $typeId);
    }

    private function processDefaultPriority(
        DefaultPriorityForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->setDefaultPriority($typeId, $info['priority']);
            $this->notification->push(_("The default priority has been set."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error setting the default priority:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderPriorityForms($subUrl, $renderer, $typeId);
    }

    private function processDeletePriority(
        DeletePriorityForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        if (($info['yesno'] ?? 0) == 1) {
            try {
                $this->driver->deletePriority($info['priority']);
                $this->notification->push(_("The priority has been deleted."), 'horde.success');
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    _("There was an error deleting the priority:") . ' ' . $e->getMessage(),
                    'horde.error',
                );
            }
        } else {
            $this->notification->push(_("The priority was not deleted."), 'horde.message');
        }

        return $this->renderPriorityForms($subUrl, $renderer, $typeId);
    }

    private function renderPriorityForms(
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        $output = [];

        $priorities = $this->driver->getPriorities($typeId);
        if ($priorities) {
            $editForm = new EditPriorityStepOneForm(['type' => $typeId], $priorities);
            $output[] = $renderer->render($editForm, $subUrl, 'post');
        }

        $currentDefault = $this->driver->getDefaultPriority($typeId) ?: null;
        $defaultForm = new DefaultPriorityForm(['type' => $typeId], $priorities, $currentDefault);
        $output[] = '<br />';
        $output[] = $renderer->render($defaultForm, $subUrl, 'post');

        $addForm = new AddPriorityForm(['type' => $typeId]);
        $output[] = '<br />';
        $output[] = $renderer->render($addForm, $subUrl, 'post');

        return implode("\n", $output);
    }

    private function processCreateDefaultPriorities(
        int $typeId,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        $conf = $GLOBALS['conf'] ?? [];
        $priorities = $conf['priorities'] ?? [];
        foreach ($priorities as $priority) {
            if (($priority['active'] ?? '') === 'active') {
                $this->driver->addPriority(
                    $typeId,
                    $priority['name'],
                    $priority['desc'] ?? '',
                );
            }
        }

        $this->notification->push(_("Default priorities have been created."), 'horde.success');

        $typeUrl = $adminUrl . '?action=type';
        $editForm = $this->buildEditTypeStepTwoForm(['type' => $typeId], $typeId);
        return $renderer->render($editForm, $typeUrl, 'post');
    }

    // ── Tab 2 sub-entities: Attributes ──

    private function handleAttributeSubmission(
        ServerRequestInterface $request,
        string $subUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        $fieldTypeNames = Whups::fieldTypeNames();

        // AddAttribute
        $body = $request->getParsedBody() ?? [];
        $selectedType = $body['attribute_type'] ?? 'text';
        $fieldTypeParams = Whups::fieldTypeParams($selectedType);
        $form = new AddAttributeForm($request, $fieldTypeNames, $fieldTypeParams, $selectedType);
        if ($form->isSubmitted()) {
            return $this->processAddAttribute($form, $subUrl, $renderer, $typeId, $fieldTypeNames);
        }

        // EditAttributeStepOne
        $attributes = $this->driver->getAttributesForType($typeId);
        $attrNames = [];
        foreach ($attributes as $key => $attribute) {
            $attrNames[$key] = $attribute['human_name'];
        }
        $form = new EditAttributeStepOneForm($request, $attrNames);
        if ($form->isSubmitted()) {
            return $this->processEditAttributeStepOne($form, $subUrl, $renderer, $typeId, $fieldTypeNames);
        }

        // EditAttributeStepTwo / DeleteAttribute need an attributeId from POST
        $attributeId = (int) ($body['attribute'] ?? 0);
        if ($attributeId) {
            $attrInfo = $this->driver->getAttributeDesc($attributeId);
            $attrType = $attrInfo['type'] ?? 'text';
            $attrParams = Whups::fieldTypeParams($attrType);

            $form = new EditAttributeStepTwoForm(
                $request,
                $attrInfo,
                $fieldTypeNames,
                $attrParams,
                $attrType,
            );
            if ($form->isSubmitted()) {
                return $this->processEditAttributeStepTwo($form, $subUrl, $renderer, $typeId, $fieldTypeNames);
            }

            $form = new DeleteAttributeForm(
                $request,
                $attrInfo['name'],
                $attrInfo['description'],
            );
            if ($form->isSubmitted()) {
                return $this->processDeleteAttribute($form, $subUrl, $renderer, $typeId, $fieldTypeNames);
            }
        }

        return $this->renderAttributeForms($subUrl, $renderer, $typeId, $fieldTypeNames);
    }

    private function processAddAttribute(
        AddAttributeForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $fieldTypeNames,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->addAttributeDesc(
                $typeId,
                $info['attribute_name'],
                $info['attribute_description'] ?? '',
                $info['attribute_type'] ?? 'text',
                $info['attribute_params'] ?? [],
                (bool) ($info['attribute_required'] ?? false),
            );

            $typeName = $this->driver->getType($typeId)['name'];
            $this->notification->push(
                sprintf(_("The attribute \"%s\" has been added to %s."), $info['attribute_name'], $typeName),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error creating the attribute:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderAttributeForms($subUrl, $renderer, $typeId, $fieldTypeNames);
    }

    private function processEditAttributeStepOne(
        EditAttributeStepOneForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $fieldTypeNames,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $attributeId = (int) $info['attribute'];
        $button = $form->getClickedButton();

        if ($button === _("Delete Attribute")) {
            $attrInfo = $this->driver->getAttributeDesc($attributeId);
            $deleteForm = new DeleteAttributeForm(
                ['type' => $typeId, 'attribute' => $attributeId],
                $attrInfo['name'],
                $attrInfo['description'],
            );
            return $renderer->render($deleteForm, $subUrl, 'post');
        }

        // Default: Edit Attribute
        $attrInfo = $this->driver->getAttributeDesc($attributeId);
        $attrType = $attrInfo['type'] ?? 'text';
        $attrParams = Whups::fieldTypeParams($attrType);
        $editForm = new EditAttributeStepTwoForm(
            ['type' => $typeId, 'attribute' => $attributeId],
            $attrInfo,
            $fieldTypeNames,
            $attrParams,
            $attrType,
        );
        return $renderer->render($editForm, $subUrl, 'post');
    }

    private function processEditAttributeStepTwo(
        EditAttributeStepTwoForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $fieldTypeNames,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updateAttributeDesc(
                $info['attribute'],
                $info['attribute_name'],
                $info['attribute_description'] ?? '',
                $info['attribute_type'] ?? 'text',
                $info['attribute_params'] ?? [],
                (bool) ($info['attribute_required'] ?? false),
            );
            $this->notification->push(_("The attribute has been modified."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error editing the attribute:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderAttributeForms($subUrl, $renderer, $typeId, $fieldTypeNames);
    }

    private function processDeleteAttribute(
        DeleteAttributeForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $fieldTypeNames,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        if (($info['yesno'] ?? 0) == 1) {
            try {
                $this->driver->deleteAttributeDesc($info['attribute']);
                $this->notification->push(_("The attribute has been deleted."), 'horde.success');
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    _("There was an error deleting the attribute:") . ' ' . $e->getMessage(),
                    'horde.error',
                );
            }
        } else {
            $this->notification->push(_("The attribute was not deleted."), 'horde.message');
        }

        return $this->renderAttributeForms($subUrl, $renderer, $typeId, $fieldTypeNames);
    }

    private function renderAttributeForms(
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
        array $fieldTypeNames,
    ): string {
        $output = [];

        $attributes = $this->driver->getAttributesForType($typeId);
        $attrNames = [];
        foreach ($attributes as $key => $attribute) {
            $attrNames[$key] = $attribute['human_name'];
        }
        if ($attrNames) {
            $editForm = new EditAttributeStepOneForm(['type' => $typeId], $attrNames);
            $output[] = $renderer->render($editForm, $subUrl, 'post');
        }

        $fieldTypeParams = Whups::fieldTypeParams('text');
        $addForm = new AddAttributeForm(['type' => $typeId], $fieldTypeNames, $fieldTypeParams);
        if (!empty($output)) {
            $output[] = '<br />';
        }
        $output[] = $renderer->render($addForm, $subUrl, 'post');

        return implode("\n", $output);
    }

    // ── Tab 2 sub-entities: Replies ──

    private function handleReplySubmission(
        ServerRequestInterface $request,
        string $subUrl,
        string $adminUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        // AddReply
        $form = new AddReplyForm($request);
        if ($form->isSubmitted()) {
            return $this->processAddReply($form, $subUrl, $renderer, $typeId);
        }

        // EditReplyStepOne
        $replies = $this->driver->getReplies($typeId);
        $replyNames = [];
        foreach ($replies as $key => $reply) {
            $replyNames[$key] = $reply['reply_name'];
        }
        $form = new EditReplyStepOneForm($request, $replyNames);
        if ($form->isSubmitted()) {
            return $this->processEditReplyStepOne($form, $subUrl, $renderer, $typeId);
        }

        // EditReplyStepTwo / DeleteReply need a replyId from POST
        $body = $request->getParsedBody() ?? [];
        $replyId = (int) ($body['reply'] ?? 0);
        if ($replyId) {
            $replyInfo = $this->driver->getReply($replyId);
            $permsEditUrl = $this->buildReplyPermsEditUrl($replyId);

            $form = new EditReplyStepTwoForm($request, $replyInfo, $permsEditUrl);
            if ($form->isSubmitted()) {
                return $this->processEditReplyStepTwo($form, $subUrl, $renderer, $typeId);
            }

            $form = new DeleteReplyForm(
                $request,
                $replyInfo['reply_name'],
                $replyInfo['reply_text'],
            );
            if ($form->isSubmitted()) {
                return $this->processDeleteReply($form, $subUrl, $renderer, $typeId);
            }
        }

        return $this->renderReplyForms($subUrl, $renderer, $typeId);
    }

    private function processAddReply(
        AddReplyForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $replyId = $this->driver->addReply(
                $typeId,
                $info['reply_name'],
                $info['reply_text'],
            );

            $this->notification->push(
                sprintf(_("The form reply \"%s\" has been added."), $info['reply_name']),
                'horde.success',
            );

            // Show the newly created reply for editing
            $replyInfo = $this->driver->getReply($replyId);
            $permsEditUrl = $this->buildReplyPermsEditUrl($replyId);
            $editForm = new EditReplyStepTwoForm(
                ['type' => $typeId, 'reply' => $replyId],
                $replyInfo,
                $permsEditUrl,
            );
            $renderer->setMode('inactive');
            return $renderer->render($editForm, $subUrl, 'post');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error creating the form reply:") . ' ' . $e->getMessage(),
                'horde.error',
            );
            return $renderer->render($form, $subUrl, 'post');
        }
    }

    private function processEditReplyStepOne(
        EditReplyStepOneForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();
        $replyId = (int) $info['reply'];
        $button = $form->getClickedButton();

        if ($button === _("Delete Form Reply")) {
            $replyInfo = $this->driver->getReply($replyId);
            $deleteForm = new DeleteReplyForm(
                ['type' => $typeId, 'reply' => $replyId],
                $replyInfo['reply_name'],
                $replyInfo['reply_text'],
            );
            return $renderer->render($deleteForm, $subUrl, 'post');
        }

        // Default: Edit Reply
        $replyInfo = $this->driver->getReply($replyId);
        $permsEditUrl = $this->buildReplyPermsEditUrl($replyId);
        $editForm = new EditReplyStepTwoForm(
            ['type' => $typeId, 'reply' => $replyId],
            $replyInfo,
            $permsEditUrl,
        );
        return $renderer->render($editForm, $subUrl, 'post');
    }

    private function processEditReplyStepTwo(
        EditReplyStepTwoForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        try {
            $this->driver->updateReply(
                $info['reply'],
                $info['reply_name'],
                $info['reply_text'],
            );
            $this->notification->push(_("The form reply has been modified."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error editing the form reply:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderReplyForms($subUrl, $renderer, $typeId);
    }

    private function processDeleteReply(
        DeleteReplyForm $form,
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $subUrl, 'post');
        }

        $info = $form->getInfo();

        if (($info['yesno'] ?? 0) == 1) {
            try {
                $this->driver->deleteReply($info['reply']);
                $this->notification->push(_("The form reply has been deleted."), 'horde.success');
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    _("There was an error deleting the form reply:") . ' ' . $e->getMessage(),
                    'horde.error',
                );
            }
        } else {
            $this->notification->push(_("The form reply was not deleted."), 'horde.message');
        }

        return $this->renderReplyForms($subUrl, $renderer, $typeId);
    }

    private function renderReplyForms(
        string $subUrl,
        HtmlRenderer $renderer,
        int $typeId,
    ): string {
        $output = [];

        $replies = $this->driver->getReplies($typeId);
        $replyNames = [];
        foreach ($replies as $key => $reply) {
            $replyNames[$key] = $reply['reply_name'];
        }
        if ($replyNames) {
            $editForm = new EditReplyStepOneForm(['type' => $typeId], $replyNames);
            $output[] = $renderer->render($editForm, $subUrl, 'post');
        }

        $addForm = new AddReplyForm(['type' => $typeId]);
        if (!empty($output)) {
            $output[] = '<br />';
        }
        $output[] = $renderer->render($addForm, $subUrl, 'post');

        return implode("\n", $output);
    }

    private function buildReplyPermsEditUrl(int $replyId): ?string
    {
        if (!$this->registry->isAdmin(['permission' => 'whups:admin', 'permlevel' => Horde_Perms::EDIT])) {
            return null;
        }

        return $this->registry->get('webroot', 'horde')
            . '/admin/perms/edit.php?category=' . urlencode('whups:replies:' . $replyId)
            . '&autocreate=1';
    }

    private function handleRemindersSubmission(
        ServerRequestInterface $request,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): ?string {
        $form = $this->buildSendReminderForm($request);
        if (!$form->isSubmitted()) {
            return null;
        }

        return $this->processSendReminder($form, $adminUrl, $renderer);
    }

    private function processSendReminder(
        SendReminderForm $form,
        string $adminUrl,
        HtmlRenderer $renderer,
    ): string {
        if (!$form->validate()) {
            return $renderer->render($form, $adminUrl . '?action=reminders', 'post');
        }

        $info = $form->getInfo();

        try {
            $this->reminderSender->send($info);
            $this->notification->push(_("Reminders were sent."), 'horde.success');
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
        }

        return $this->renderRemindersTab($adminUrl, $renderer);
    }

    private function renderRemindersTab(string $adminUrl, HtmlRenderer $renderer): string
    {
        $form = $this->buildSendReminderForm([]);
        return $renderer->render($form, $adminUrl . '?action=reminders', 'post');
    }

    private function buildSendReminderForm(
        ServerRequestInterface|array $vars,
    ): SendReminderForm {
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::EDIT,
        );

        $categories = $this->driver->getCategories();
        unset($categories['resolved']);

        return new SendReminderForm($vars, $queues, $categories);
    }

    // ── Tab 3: Queue/Type Matrix ──

    private function handleMatrixSubmission(
        ServerRequestInterface $request,
        string $adminUrl,
    ): ?string {
        $body = $request->getParsedBody() ?? [];
        if (($body['formname'] ?? '') !== 'mtmatrix') {
            return null;
        }

        $matrix = $body['matrix'] ?? [];
        $pairs = [];
        foreach ($matrix as $queueId => $types) {
            foreach (array_keys($types) as $typeId) {
                $pairs[] = [(int) $queueId, (int) $typeId];
            }
        }

        try {
            $this->driver->updateTypesQueues($pairs);
            $this->notification->push(
                _("The queue/type associations have been updated."),
                'horde.success',
            );
        } catch (Whups_Exception $e) {
            $this->notification->push(
                _("There was an error updating associations:") . ' ' . $e->getMessage(),
                'horde.error',
            );
        }

        return $this->renderMatrixTab($adminUrl);
    }

    private function renderMatrixTab(string $adminUrl): string
    {
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::EDIT,
        );
        $types = $this->driver->getAllTypes();

        if (empty($queues) || empty($types)) {
            return '<p class="horde-content">'
                . htmlspecialchars(_("There are no queues or types to display."))
                . '</p>';
        }

        $actionUrl = htmlspecialchars($adminUrl . '?action=mtmatrix');

        $html = '<form action="' . $actionUrl . '" method="post" name="matrix">' . "\n"
            . '<input type="hidden" name="formname" value="mtmatrix">' . "\n"
            . '<h1 class="header">' . htmlspecialchars(_("Queue/Type Matrix")) . '</h1>' . "\n"
            . '<br><table class="horde-table">' . "\n"
            . '<thead><tr><th class="rightAlign">&nbsp;</th>' . "\n";

        foreach ($types as $tid => $type) {
            $html .= '<th style="text-align:center"><strong>'
                . htmlspecialchars($type) . '</strong></th>' . "\n";
        }
        $html .= '</tr></thead>' . "\n";

        foreach ($queues as $qid => $queue) {
            $selected = $this->driver->getTypes($qid);
            $html .= '<tr><td class="rightAlign"><strong>'
                . htmlspecialchars($queue) . '</strong></td>' . "\n";
            foreach ($types as $tid => $type) {
                $checked = !empty($selected[$tid]) ? ' checked="checked"' : '';
                $html .= '<td style="text-align:center">'
                    . '<input type="checkbox" class="checkbox"'
                    . ' name="matrix[' . (int) $qid . '][' . (int) $tid . ']"'
                    . $checked . '></td>' . "\n";
            }
            $html .= '</tr>' . "\n";
        }

        $html .= '</table>' . "\n"
            . '<div class="horde-form-buttons">'
            . '<input type="submit" class="horde-default" value="'
            . htmlspecialchars(_("Update Associations")) . '">'
            . '</div>' . "\n"
            . '</form>';

        return $html;
    }
}
