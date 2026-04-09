<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Display open tickets in a queue.
 *
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Controller\Queue;

use Horde\Whups\Controller\ResponseTrait;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Session;
use Horde_Url;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_View_Results;

class ViewController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Session $session,
        private readonly string $defaultView,
        private readonly string $webroot,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $queryParams = $request->getQueryParams();

        $slug = $route['slug'] ?? $queryParams['slug'] ?? null;
        $id = $queryParams['id'] ?? null;

        $queue = null;
        if ($slug && !is_numeric($slug)) {
            $queue = $this->driver->getQueueBySlugInternal($slug);
            $id = $queue['id'] ?? null;
        } elseif ($slug) {
            $id = (int) $slug;
            $queue = $this->driver->getQueue($id);
        } elseif ($id) {
            $id = (int) $id;
            $queue = $this->driver->getQueue($id);
        }

        if (!$id) {
            $this->notification->push(_("Invalid queue"), 'horde.error');
            return $this->redirect($this->webroot . '/' . $this->defaultView);
        }

        $title = sprintf(_("Open tickets in %s"), $queue['name']);

        $criteria = [
            'queue' => $id,
            'category' => ['unconfirmed', 'new', 'assigned'],
        ];

        $html = $this->renderChrome($title, function () use ($criteria, $title, $queue, $id) {
            Whups::addFeedLink();

            try {
                $tickets = $this->driver->getTicketsByProperties($criteria);
                Whups::sortTickets($tickets);
                $self = Whups::urlFor('queue', $queue);
                $results = new Whups_View_Results([
                    'title' => $title,
                    'results' => $tickets,
                    'values' => Whups::getSearchResultColumns(),
                    'url' => $self,
                ]);
                $this->session->set('whups', 'last_search', $self);
                $results->html();
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    sprintf(
                        _("There was an error locating tickets in this queue: %s"),
                        $e->getMessage()
                    ),
                    'horde.error'
                );
            }
        });

        return $this->htmlResponse($html);
    }
}
