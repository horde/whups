<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Query results RSS feed.
 *
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller\Rss;

use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\UrlGenerator;
use Horde_Perms;
use Horde_Registry;
use Horde_Themes;
use Horde_Variables;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Query_Manager;

class QueryRssController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $query = $request->getQueryParams();

        // Resolve query by slug (route param) or id (query param).
        $slug = $route['slug'] ?? $query['slug'] ?? null;
        $queryId = $query['query'] ?? null;

        $qManager = new Whups_Query_Manager();
        $whups_query = null;

        if ($slug) {
            $whups_query = $qManager->getQueryBySlug($slug);
        } elseif ($queryId) {
            $whups_query = $qManager->getQuery($queryId);
        }

        // Validate: query must exist, have no parameters, and be readable.
        $auth = $this->registry->getAuth();
        if (!isset($whups_query)
            || $whups_query->parameters
            || !$whups_query->hasPermission($auth, Horde_Perms::READ)
        ) {
            return $this->xmlResponse('', 404);
        }

        $vars = new Horde_Variables();
        $tickets = $this->driver->executeQuery($whups_query, $vars);
        if (!count($tickets)) {
            return $this->xmlResponse('', 404);
        }

        Whups::sortTickets($tickets, 'date_updated', 'desc');
        $items = $this->buildItems($tickets);

        // URL params: prefer slug over id.
        $urlParams = $slug
            ? ['slug' => $slug]
            : ['slug' => (string) $queryId];

        $queryName = $whups_query->name ?: _("Query Results");

        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/rss']);
        $view->xsl = Horde_Themes::getFeedXsl();
        $view->pubDate = htmlspecialchars(date('r'));
        $view->title = htmlspecialchars($queryName);
        $view->items = $items;
        $view->url = $this->urlGenerator->absoluteUrlFor('QueryRun', $urlParams);
        $view->rss_url = $this->urlGenerator->absoluteUrlFor('QueryRss', $urlParams);
        $view->description = htmlspecialchars(sprintf(
            _("Tickets matching the query \"%s\"."),
            $whups_query->name,
        ));

        return $this->xmlResponse($view->render('items.rss'));
    }

    /**
     * @param array<int, array<string, mixed>> $tickets
     * @return array<int, array<string, string>>
     */
    private function buildItems(array $tickets): array
    {
        $items = [];

        foreach (array_keys($tickets) as $i) {
            $items[$i] = [
                'title' => htmlspecialchars(sprintf(
                    '[%s] %s',
                    $tickets[$i]['id'],
                    $tickets[$i]['summary'],
                )),
                'description' => htmlspecialchars(sprintf(
                    'Type: %s; State: %s',
                    $tickets[$i]['type_name'],
                    $tickets[$i]['state_name'],
                )),
                'url' => $this->urlGenerator->absoluteUrlFor('TicketView', ['id' => (int) $tickets[$i]['id']]),
                'pubDate' => htmlspecialchars(date('r', $tickets[$i]['timestamp'])),
            ];
        }

        return $items;
    }
}
