<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Display ticket attachments.
 *
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
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
use Horde\Date\Format as DateFormat;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\Service\UserFormatter;
use Horde_Core_Factory_MimeViewer;
use Horde_Core_Factory_Vfs;
use Horde_Mime_Magic;
use Horde_Mime_Part;
use Horde_Mime_Viewer_Default;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde\Core\Session\SessionAccess;
use Horde_Themes_Image;
use Horde_Url;
use Horde_Variables;
use Horde_View;
use Horde\Whups\Service\TopbarSearch;
use Horde_Vfs_Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Ticket;

class AttachmentsController implements RequestHandlerInterface
{
    use ResponseTrait;
    use TicketTabsTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Core_Factory_MimeViewer $mimeViewerFactory,
        private readonly Horde_Core_Factory_Vfs $vfsFactory,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly SessionAccess $session,
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

        $uid = $this->registry->getAuth() ?: '';

        if (!$id) {
            $this->notification->push(_("Invalid Ticket Id"), 'horde.error');
            $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
            return $this->redirect($this->urlGenerator->defaultViewUrl($defaultView));
        }

        try {
            $details = $this->driver->getTicketDetails($id);
            $ticket = new Whups_Ticket($id, $details);
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage(), 'horde.error');
            $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
            return $this->redirect($this->urlGenerator->defaultViewUrl($defaultView));
        }

        $vars = Horde_Variables::getDefaultVariables();
        $vars->set('id', $id);

        // Build attachment list.
        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES]);
        try {
            $files = $ticket->listAllAttachments();
        } catch (Whups_Exception $e) {
            $this->notification->push($e);
            $files = null;
        }

        if ($files) {
            $dateFormat = $this->prefs->getValue($uid, 'horde', 'date_format') ?: '%x';
            $timeFormat = $this->prefs->getValue($uid, 'horde', 'time_format') ?: '%X';
            $vfsAttachments = $this->getAttachments((int) $id);
            $queue = $ticket->get('queue');
            $canDelete = $this->permissions->hasQueuePermission($queue, Horde_Perms::DELETE);

            $view->attachments = [];
            foreach ($files as $file) {
                $attachmentInfo = isset($vfsAttachments[$file['value']])
                    ? $this->buildAttachmentLinks(
                        (int) $id,
                        $vfsAttachments[$file['value']],
                        $queue,
                        $canDelete,
                    )
                    : ['view' => '', 'download' => '', 'delete' => ''];

                $view->attachments[] = array_merge(
                    [
                        'timestamp' => $file['timestamp'],
                        'date' => DateFormat::formatDate(
                            $file['timestamp'],
                            $dateFormat . ' ' . $timeFormat,
                        ),
                        'user' => $this->userFormatter->format(
                            $this->userFormatter->getAttributes($file['user_id']),
                            true,
                            true,
                            true,
                        ),
                    ],
                    $attachmentInfo,
                );
            }
        }

        // Tabs.
        $tabs = $this->buildTicketTabs($vars, $ticket);
        $title = sprintf(
            _("Attachments for %s"),
            '[#' . $id . '] ' . $ticket->get('summary'),
        );

        $html = $this->renderChrome($title, function () use (
            $ticket,
            $vars,
            $view,
            $tabs,
            $id,
        ) {
            // Topbar search.
            $this->topbarSearch->apply();

            // Feed links.
            $rssUrl = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $id]);
            $this->pageOutput->addLinkTag(['href' => $rssUrl, 'title' => '[#' . $id . '] ' . $ticket->get('summary')]);

            $this->pageOutput->addLinkTag([
                'href' => $this->urlGenerator->absoluteUrlFor('OpenSearch'),
                'rel' => 'search',
                'type' => 'application/opensearchdescription+xml',
                'title' => $this->registry->get('name') . ' (' . $this->urlGenerator->getWebroot() . ')',
            ]);

            $this->pageOutput->addScriptFile('tables.js', 'horde');

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Tabs.
            echo $tabs->render('attachments');

            // Attachment list.
            echo $view->render('ticket/attachments');
        });

        return $this->htmlResponse($html);
    }

    /**
     * List VFS attachment files for a ticket.
     *
     * @return array<string, array> Keyed by filename.
     */
    private function getAttachments(int $ticketId): array
    {
        try {
            $vfs = $this->vfsFactory->create();
        } catch (Horde_Vfs_Exception $e) {
            return [];
        }

        $path = Whups::VFS_ATTACH_PATH;
        if (!$vfs->isFolder($path, (string) $ticketId)) {
            return [];
        }

        try {
            return $vfs->listFolder($path . '/' . $ticketId);
        } catch (Horde_Vfs_Exception $e) {
            return [];
        }
    }

    /**
     * Build view/download/delete links for an attachment.
     *
     * @return array{view: string, download: string, delete?: string}
     */
    private function buildAttachmentLinks(
        int $ticketId,
        array $file,
        int $queue,
        bool $canDelete,
    ): array {
        $links = [];

        // View link: check if a MIME viewer exists for this file type.
        $mimePart = new Horde_Mime_Part();
        $mimePart->setType(Horde_Mime_Magic::extToMime($file['type']));
        $viewer = $this->mimeViewerFactory->create($mimePart);

        if ($viewer && !($viewer instanceof Horde_Mime_Viewer_Default)) {
            $links['view'] = (new Horde_Url($this->urlGenerator->urlFor('ViewAttachment')))
                ->add([
                    'actionID' => 'view_file',
                    'type' => $file['type'],
                    'file' => $file['name'],
                    'ticket' => $ticketId,
                ])
                ->link(['title' => $file['name'], 'target' => '_blank'])
                . htmlspecialchars($file['name']) . '</a>';
        } else {
            $links['view'] = htmlspecialchars($file['name']);
        }

        // Download link.
        $links['download'] = $this->registry->downloadUrl(
            $file['name'],
            ['actionID' => 'download_file', 'file' => $file['name'], 'ticket' => $ticketId],
        )->link(['title' => $file['name']])
            . Horde_Themes_Image::tag('download.png', ['alt' => _("Download")])
            . '</a>';

        // Delete link (permission required).
        if ($canDelete) {
            $links['delete'] = (new Horde_Url($this->urlGenerator->urlFor('TicketDeleteAttachment', ['id' => $ticketId])))
                ->add([
                    'file' => $file['name'],
                    'id' => $ticketId,
                    'url' => Horde::signUrl(Horde::selfUrl(true, false, true)),
                ])
                ->link([
                    'title' => sprintf(_("Delete %s"), $file['name']),
                    'onclick' => 'return window.confirm(\''
                        . addslashes(sprintf(_("Permanently delete %s?"), $file['name']))
                        . '\');',
                ])
                . Horde_Themes_Image::tag('delete.png', ['alt' => sprintf(_("Delete %s"), $file['name'])])
                . '</a>';
        } else {
            $links['delete'] = '';
        }

        return $links;
    }
}
