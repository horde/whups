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
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde\Core\Session\HordeSession;
use Horde_Url;
use Horde\Whups\Service\TopbarSearch;
use Horde\Whups\Service\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MyBugsController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Browser $browser,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly HordeSession $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly PrefsService $prefs,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uid = $this->registry->getAuth() ?: '';
        $title = sprintf(_("My %s"), $this->registry->get('name'));

        if (!$this->registry->hasPermission('whups', Horde_Perms::READ)) {
            $this->notification->push(_("You are not authorized for this page."), 'horde.error');
            $html = $this->renderChrome($title, function () {});

            return $this->htmlResponse($html);
        }

        // Meta refresh for non-XHR browsers.
        $refreshTime = (int) $this->prefs->getValue($uid, 'horde', 'summary_refresh_time');
        if ($refreshTime && !$this->browser->hasFeature('xmlhttpreq')) {
            $this->pageOutput->metaRefresh($refreshTime, new Horde_Url($this->urlGenerator->urlFor('MyBugs')));
        }

        // Ensure a default block layout exists.
        $this->ensureDefaultLayout($uid);

        // Read layout directly via PrefsService (bypasses $GLOBALS['prefs'] scope).
        $layoutData = @unserialize(
            $this->prefs->getValue($uid, 'whups', 'mybugs_layout') ?: ''
        );
        if (!is_array($layoutData) || !$layoutData) {
            $layoutData = [];
        }

        // Build the block layout view.
        $layout = new Horde_Core_Block_Layout_View(
            $layoutData,
            new Horde_Url($this->urlGenerator->urlFor('MyBugsEdit')),
            new Horde_Url($this->urlGenerator->urlFor('MyBugs'), true),
        );
        $layoutHtml = $layout->toHtml();

        $html = $this->renderChrome($title, function () use ($layoutHtml) {
            // Topbar search.
            $this->topbarSearch->apply();

            // OpenSearch link.
            $this->pageOutput->addLinkTag([
                'href' => $this->urlGenerator->absoluteUrlFor('OpenSearch'),
                'rel' => 'search',
                'type' => 'application/opensearchdescription+xml',
                'title' => $this->registry->get('name')
                    . ' (' . $this->urlGenerator->getWebroot() . ')',
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
