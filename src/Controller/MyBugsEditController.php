<?php

declare(strict_types=1);

/**
 * PSR-15 controller: "My Bugs" portal block layout editor.
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

use Horde;
use Horde\Core\Service\PrefsService;
use Horde_Core_Factory_BlockCollection;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde\Core\Session\SessionAccess;
use Horde_Url;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\Service\TopbarSearch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MyBugsEditController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Core_Factory_BlockCollection $blockFactory,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly SessionAccess $session,
        private readonly TopbarSearch $topbarSearch,
        private readonly PrefsService $prefs,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uid = $this->registry->getAuth() ?: '';

        // Read form data from PSR-7 request.
        $query = $request->getQueryParams();
        $body = (array) ($request->getParsedBody() ?? []);
        $action = $body['action'] ?? $query['action'] ?? null;
        $row = (int) ($body['row'] ?? $query['row'] ?? 0);
        $col = (int) ($body['col'] ?? $query['col'] ?? 0);
        $returnUrl = $body['url'] ?? $query['url'] ?? null;

        // Build the block layout manager.
        $blocks = $this->blockFactory->create(['whups'], 'mybugs_layout');
        $layout = $blocks->getLayoutManager();

        // Process layout action.
        $layout->handle($action, $row, $col);

        if ($layout->updated()) {
            $this->prefs->setValue($uid, 'whups', 'mybugs_layout', $layout->serialize());

            if ($returnUrl && ($verified = Horde::verifySignedUrl($returnUrl))) {
                return $this->redirect((new Horde_Url($verified))->unique()->toString());
            }
        }

        // Render the editor page.
        $title = sprintf(_("My %s :: Add Content"), $this->registry->get('name'));

        $html = $this->renderChrome($title, function () use ($layout, $blocks) {
            // Topbar search.
            $this->topbarSearch->apply();

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Horde-provided portal editor template.
            // The template reads $layout and $GLOBALS['injector'] for max_blocks permission.
            require $this->registry->get('templates', 'horde') . '/portal/edit.inc';
        });

        return $this->htmlResponse($html);
    }
}
