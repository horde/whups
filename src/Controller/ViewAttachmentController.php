<?php

declare(strict_types=1);

/**
 * PSR-15 controller: View ticket attachments and original messages.
 *
 * Serves file downloads and MIME-rendered content for ticket attachments
 * and original email messages stored in VFS.
 *
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller;

use Horde_Core_Factory_MimeViewer;
use Horde_Core_Factory_Vfs;
use Horde_Exception;
use Horde_Exception_PermissionDenied;
use Horde_Mime_Magic;
use Horde_Mime_Part;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde_Url;
use Horde_Vfs_Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;

class ViewAttachmentController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Core_Factory_Vfs $vfsFactory,
        private readonly Horde_Core_Factory_MimeViewer $mimeViewerFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $ticketId = (int) ($params['ticket'] ?? 0);
        $filename = $params['file'] ?? '';
        $messageId = (int) ($params['message'] ?? 0);
        $type = $params['type'] ?? '';

        if (empty($ticketId)) {
            return $this->htmlResponse('', 404);
        }

        // Permission check.
        try {
            $this->driver->getTicketDetails($ticketId);
        } catch (Horde_Exception_PermissionDenied $e) {
            $loginUrl = (new Horde_Url(
                $this->registry->get('webroot', 'horde') . '/login.php',
                true,
            ))->add('url', \Horde::signUrl(\Horde::selfUrl(true)));

            return $this->redirect($loginUrl->toString());
        }

        $history = $this->driver->getHistory($ticketId);
        if (!count(Whups::permissionsFilter($history, 'comment', Horde_Perms::READ))) {
            throw new Horde_Exception(sprintf(_("You are not allowed to view ticket %d."), $ticketId));
        }

        try {
            $vfs = $this->vfsFactory->create();
        } catch (Horde_Exception $e) {
            throw new Horde_Exception(
                _("The VFS backend needs to be configured to enable attachment uploads."),
            );
        }

        if ($messageId) {
            return $this->serveMessage($vfs, $ticketId, $messageId);
        }

        return $this->serveAttachment($vfs, $ticketId, $filename, $type);
    }

    /**
     * Serve an original email message as .eml download.
     */
    private function serveMessage(object $vfs, int $ticketId, int $messageId): ResponseInterface
    {
        try {
            $data = $vfs->read(Whups::VFS_MESSAGE_PATH . '/' . $ticketId, (string) $messageId);
        } catch (Horde_Vfs_Exception $e) {
            throw new Horde_Exception(sprintf(_("Access denied to message %d"), $messageId));
        }

        $mimePart = new Horde_Mime_Part();
        $mimePart->setType('message/rfc822');
        $mimePart->setContents($data);

        $filename = _("Original message") . '.eml';

        return $this->renderMimePart($mimePart, $filename, $data);
    }

    /**
     * Serve a file attachment via MIME viewer or direct download.
     */
    private function serveAttachment(
        object $vfs,
        int $ticketId,
        string $filename,
        string $type,
    ): ResponseInterface {
        try {
            $data = $vfs->read(Whups::VFS_ATTACH_PATH . '/' . $ticketId, $filename);
        } catch (Horde_Vfs_Exception $e) {
            throw new Horde_Exception(sprintf(_("Access denied to %s"), $filename));
        }

        $mimePart = new Horde_Mime_Part();
        $mimePart->setType(Horde_Mime_Magic::extToMime($type));
        $mimePart->setContents($data);
        $mimePart->setName($filename);
        $mimePart->setCharset('US-ASCII');

        return $this->renderMimePart($mimePart, $filename, $data);
    }

    /**
     * Render a MIME part via the viewer, or fall back to direct download.
     */
    private function renderMimePart(
        Horde_Mime_Part $mimePart,
        string $filename,
        string $rawData,
    ): ResponseInterface {
        $ret = $this->mimeViewerFactory->create($mimePart)->render('full');
        reset($ret);
        $key = key($ret);

        if (empty($ret)) {
            // No viewer — serve raw download.
            return $this->downloadResponse($rawData, $filename);
        }

        if (str_contains($ret[$key]['type'], 'text/html')) {
            // HTML output — wrap in page chrome.
            $this->pageOutput->topbar = $this->pageOutput->sidebar = false;
            $html = $this->renderChrome($filename, function () use ($ret, $key) {
                echo $ret[$key]['data'];
            });
            return $this->htmlResponse($html);
        }

        // Other rendered content — serve as download.
        return $this->downloadResponse(
            $ret[$key]['data'],
            $filename,
            $ret[$key]['type'],
            true,
        );
    }
}
