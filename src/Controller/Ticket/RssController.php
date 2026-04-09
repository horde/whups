<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Generate RSS feed for a single ticket's history.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Controller\Ticket;

use Horde\Whups\Controller\ResponseTrait;
use Horde_Perms;
use Horde_Themes;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;

class RssController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly string $templatePath,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $ticketId = preg_replace('|\D|', '', (string) ($route['id'] ?? ''));
        if (!$ticketId) {
            return $this->xmlResponse('', 404);
        }

        $details = $this->driver->getTicketDetails($ticketId);

        if (!count(Whups::permissionsFilter([$details['queue'] => ''], 'queue', Horde_Perms::READ))) {
            return $this->xmlResponse('', 403);
        }

        $history = Whups::permissionsFilter(
            $this->driver->getHistory($ticketId),
            'comment',
            Horde_Perms::READ
        );

        $self = Whups::urlFor('ticket', $ticketId, true, -1);
        $items = [];
        foreach (array_keys($history) as $i) {
            if (!isset($history[$i]['comment_text'])) {
                continue;
            }
            $items[$i]['title'] = htmlspecialchars(substr($history[$i]['comment_text'], 0, 60));
            $items[$i]['description'] = htmlspecialchars($history[$i]['comment_text']);
            $items[$i]['pubDate'] = htmlspecialchars(date('r', $history[$i]['timestamp']));
            $items[$i]['url'] = $self . '#t' . $i;
        }

        $view = new Horde_View(['templatePath' => $this->templatePath]);
        $view->xsl = Horde_Themes::getFeedXsl();
        $view->pubDate = htmlspecialchars(date('r'));
        $view->title = htmlspecialchars($details['summary']);
        $view->items = $items;
        $view->url = Whups::urlFor('ticket', $ticketId, true);
        $view->rss_url = Whups::urlFor('ticket_rss', $ticketId, true);
        $view->description = htmlspecialchars($details['summary']);

        return $this->downloadResponse(
            $view->render('items.rss'),
            $details['summary'] . '.rss',
            'text/xml',
            true,
        );
    }
}
