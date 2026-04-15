<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Generate RSS feed for a single ticket's history.
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

use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde_Perms;
use Horde_Themes;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;

class RssController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly PermissionChecker $permissions,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $ticketId = preg_replace('|\D|', '', (string) ($route['id'] ?? ''));
        if (!$ticketId) {
            return $this->xmlResponse('', 404);
        }

        $details = $this->driver->getTicketDetails($ticketId);

        if (!$this->permissions->hasQueuePermission($details['queue'], Horde_Perms::READ)) {
            return $this->xmlResponse('', 403);
        }

        $history = $this->permissions->filterComments(
            $this->driver->getHistory($ticketId),
            Horde_Perms::READ,
        );

        $selfUrl = $this->urlGenerator->absoluteUrlFor('TicketView', ['id' => (int) $ticketId]);
        $items = [];
        foreach (array_keys($history) as $i) {
            if (!isset($history[$i]['comment_text'])) {
                continue;
            }
            $items[$i] = [
                'title' => htmlspecialchars(substr($history[$i]['comment_text'], 0, 60)),
                'description' => htmlspecialchars($history[$i]['comment_text']),
                'pubDate' => htmlspecialchars(date('r', $history[$i]['timestamp'])),
                'url' => $selfUrl . '#t' . $i,
            ];
        }

        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/rss']);
        $view->xsl = Horde_Themes::getFeedXsl();
        $view->pubDate = htmlspecialchars(date('r'));
        $view->title = htmlspecialchars($details['summary']);
        $view->items = $items;
        $view->url = $this->urlGenerator->absoluteUrlFor('TicketView', ['id' => (int) $ticketId]);
        $view->rss_url = $this->urlGenerator->absoluteUrlFor('TicketRss', ['id' => (int) $ticketId]);
        $view->description = htmlspecialchars($details['summary']);

        return $this->xmlResponse($view->render('items.rss'));
    }
}
