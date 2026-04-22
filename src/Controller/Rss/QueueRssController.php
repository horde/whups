<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Queue RSS feed.
 *
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
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
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\UrlGenerator;
use Horde_String;
use Horde_Themes;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;

class QueueRssController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly TicketSorter $sorter,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $query = $request->getQueryParams();

        // Resolve queue by slug (route param) or id (query param).
        $slug = $route['slug'] ?? $query['slug'] ?? null;
        $id = null;
        $queue = null;

        if ($slug) {
            $queue = $this->driver->getQueueBySlugInternal($slug);
            if (!count($queue)) {
                return $this->xmlResponse('', 404);
            }
            $id = $queue['id'];
        } else {
            $id = $query['id'] ?? null;
            if ($id) {
                $queue = $this->driver->getQueue($id);
            }
        }

        // State category filtering.
        $stateParam = $query['state'] ?? null;
        if ($stateParam) {
            $stateDisplay = Horde_String::ucFirst($stateParam);
            $limit = 10;
            $stateCategory = [$stateParam];
        } else {
            $stateCategory = ['unconfirmed', 'new', 'assigned'];
            $stateDisplay = _("Open");
            $limit = 0;
        }

        // Optional type filtering.
        $typeId = $query['type_id'] ?? null;
        $type = null;
        $criteria = [];

        if (is_numeric($typeId)) {
            try {
                $type = $this->driver->getType($typeId);
                $criteria['type'] = [$typeId];
            } catch (Whups_Exception) {
                // Ignore invalid type.
            }
        }

        if (!$id && !$stateParam && !$typeId) {
            return $this->xmlResponse('', 404);
        }

        $criteria['category'] = $stateCategory;
        if ($id) {
            $criteria['queue'] = $id;
        }

        $tickets = $this->driver->getTicketsByProperties($criteria);
        if (!count($tickets)) {
            return $this->xmlResponse('', 404);
        }

        $this->sorter->sort($tickets, 'date_updated', 'desc');
        $items = $this->buildItems($tickets, $limit);

        // Build title.
        $queueName = $queue['name'] ?? null;
        $typeName = $type['name'] ?? null;
        $title = $this->buildTitle($stateDisplay, $queueName, $typeName);

        // Build description.
        $description = $queueName
            ? sprintf(_("Open tickets in %s"), $queueName)
            : _("Open tickets in all queues.");

        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/rss']);
        $view->xsl = Horde_Themes::getFeedXsl();
        $view->pubDate = htmlspecialchars(date('r'));
        $view->title = htmlspecialchars($title);
        $view->items = $items;
        $view->url = $this->urlGenerator->absoluteUrlFor('QueueView', ['slug' => (string) $id]);
        $view->rss_url = $this->urlGenerator->absoluteUrlFor('QueueRss', ['slug' => (string) $id]);
        $view->description = htmlspecialchars($description);

        return $this->xmlResponse($view->render('items.rss'));
    }

    private function buildTitle(string $stateDisplay, ?string $queueName, ?string $typeName): string
    {
        if ($typeName && $queueName) {
            return sprintf(_("%s %s tickets in %s"), $stateDisplay, $typeName, $queueName);
        }
        if ($typeName) {
            return sprintf(_("%s %s tickets in all queues"), $stateDisplay, $typeName);
        }
        if ($queueName) {
            return sprintf(_("%s tickets in %s"), $stateDisplay, $queueName);
        }

        return sprintf(_("%s tickets in all queues"), $stateDisplay);
    }

    /**
     * @param array<int, array<string, mixed>> $tickets
     * @return array<int, array<string, string>>
     */
    private function buildItems(array $tickets, int $limit): array
    {
        $items = [];
        $count = 0;

        foreach (array_keys($tickets) as $i) {
            if ($limit > 0 && $count++ === $limit) {
                break;
            }

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
