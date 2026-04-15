<?php

declare(strict_types=1);

/**
 * PSR-15 controller: "My Bugs" portal block layout.
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

use Horde\Core\Service\PrefsService;
use Horde_Browser;
use Horde_Core_Block_Layout_View;
use Horde_Core_Factory_BlockCollection;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_Session;
use Horde_Url;
use Horde_View_Topbar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MyBugsController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Browser $browser,
        private readonly Horde_Core_Factory_BlockCollection $blockFactory,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly Horde_Session $session,
        private readonly Horde_View_Topbar $topbar,
        private readonly PrefsService $prefs,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $webroot = $this->registry->get('webroot', 'whups');
        $uid = $this->registry->getAuth() ?: '';
        $title = sprintf(_("My %s"), $this->registry->get('name'));

        // Meta refresh for non-XHR browsers.
        $refreshTime = (int) $this->prefs->getValue($uid, 'horde', 'summary_refresh_time');
        if ($refreshTime && !$this->browser->hasFeature('xmlhttpreq')) {
            $this->pageOutput->metaRefresh($refreshTime, new Horde_Url($webroot . '/mybugs'));
        }

        // Ensure a default block layout exists.
        $this->ensureDefaultLayout($uid);

        // Build the block layout view.
        $collection = $this->blockFactory->create(['whups'], 'mybugs_layout');
        $layout = new Horde_Core_Block_Layout_View(
            $collection->getLayout(),
            new Horde_Url($webroot . '/mybugs/edit'),
            new Horde_Url($webroot . '/mybugs', true),
        );
        $layoutHtml = $layout->toHtml();

        $html = $this->renderChrome($title, function () use ($webroot, $layoutHtml) {
            // Topbar search.
            $this->topbar->search = true;
            $this->topbar->searchAction = new Horde_Url($webroot . '/ticket');
            $this->topbar->searchLabel = $this->session->get('whups', 'search') ?: _("Ticket #Id");

            // OpenSearch link.
            $this->pageOutput->addLinkTag([
                'href' => (new Horde_Url($webroot . '/opensearch.php', true))->toString(true, false),
                'rel' => 'search',
                'type' => 'application/opensearchdescription+xml',
                'title' => $this->registry->get('name')
                    . ' (' . (new Horde_Url($webroot, true))->toString(true, false) . ')',
            ]);

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Block layout.
            echo $layoutHtml;
        });

        return $this->htmlResponse($html);
    }

    /**
     * Ensure the mybugs_layout pref has a sensible default.
     */
    private function ensureDefaultLayout(string $uid): void
    {
        if (!$this->registry->isAuthenticated()) {
            // Guest: show queries + queue summary.
            $this->prefs->setValue($uid, 'whups', 'mybugs_layout', serialize([
                [['app' => 'whups', 'params' => ['type2' => 'whups_Block_Myqueries', 'params' => false], 'height' => 1, 'width' => 1]],
                [['app' => 'whups', 'params' => ['type2' => 'whups_Block_Queuesummary', 'params' => false], 'height' => 1, 'width' => 1]],
            ]));
            return;
        }

        $current = $this->prefs->getValue($uid, 'whups', 'mybugs_layout');
        if (!$current || !@unserialize($current)) {
            // Authenticated user with no valid layout: set default.
            $this->prefs->setValue($uid, 'whups', 'mybugs_layout', serialize([
                [['app' => 'whups', 'params' => ['type2' => 'whups_Block_Mytickets', 'params' => false], 'height' => 1, 'width' => 1]],
                [['app' => 'whups', 'params' => ['type2' => 'whups_Block_Myrequests', 'params' => false], 'height' => 1, 'width' => 1]],
                [['app' => 'whups', 'params' => ['type2' => 'whups_Block_Myqueries', 'params' => false], 'height' => 1, 'width' => 1]],
            ]));
        }
    }
}
