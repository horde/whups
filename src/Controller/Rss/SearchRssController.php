<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Search results RSS feed.
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
use Horde\Whups\Form\SearchForm;
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\UrlGenerator;
use Horde_Perms;
use Horde_Registry;
use Horde_Themes;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;

class SearchRssController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
        private readonly TicketSorter $sorter,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit = (int) ($params['limit'] ?? 0);

        // Pre-load domain data for the form.
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::READ,
        );
        $typeStates = $this->buildTypeStates($queues);

        $form = new SearchForm($request, $queues, $typeStates);

        if (!$form->validate()) {
            return $this->xmlResponse('<error>' . _("Invalid search") . '</error>', 400);
        }

        $info = $this->processSearchInfo($form->getInfo(), $queues);
        $tickets = $this->driver->getTicketsByProperties($info);
        $this->sorter->sort($tickets, 'date_updated', 'desc');

        $items = $this->buildItems($tickets, $limit);

        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/rss']);
        $view->xsl = Horde_Themes::getFeedXsl();
        $view->pubDate = htmlspecialchars(date('r'));
        $view->title = _("Search Results");
        $view->items = $items;
        $view->url = $this->urlGenerator->absoluteUrlFor('Search');
        $view->rss_url = $this->urlGenerator->absoluteUrlFor('SearchRss');
        $view->description = _("Search Results");

        return $this->xmlResponse($view->render('items.rss'));
    }

    /**
     * Build per-type state data for the search form.
     *
     * @param array<int,string> $queues Queue id => name
     * @return array<int,array{typeName:string,states:array<int,string>,defaults:list<int>}>
     *
     * TODO: Duplicated in SearchController — extract to a shared service.
     */
    private function buildTypeStates(array $queues): array
    {
        $types = [];
        if (count($queues) === 1) {
            $types = $this->driver->getTypes(key($queues));
        } else {
            foreach ($queues as $queueId => $name) {
                $types = $types + $this->driver->getTypes($queueId);
            }
        }

        $typeStates = [];
        foreach ($types as $typeId => $typeName) {
            $states = $this->driver->getAllStateInfo($typeId);
            $list = [];
            $defaults = [];
            foreach ($states as $state) {
                $list[$state['state_id']] = $state['state_name'];
                if ($state['state_category'] !== 'resolved') {
                    $defaults[] = $state['state_id'];
                }
            }
            $typeStates[$typeId] = [
                'typeName' => $typeName,
                'states' => $list,
                'defaults' => $defaults,
            ];
        }

        return $typeStates;
    }

    /**
     * Post-process raw form info for search execution.
     *
     * @param array $info   Raw getInfo() output
     * @param array<int,string> $queues  All readable queues
     * @return array Processed info for getTicketsByProperties()
     *
     * TODO: Duplicated in SearchController — extract to a shared service.
     */
    private function processSearchInfo(array $info, array $queues): array
    {
        if (empty($info['queue'])) {
            $info['queue'] = array_keys(
                Whups::permissionsFilter(
                    $this->driver->getQueues(),
                    'queue',
                    Horde_Perms::READ,
                    $this->registry->getAuth(),
                    $this->registry->getAuth(),
                ),
            );
        } else {
            $info['queue'] = [$info['queue']];
        }

        if (empty($info['states'])) {
            unset($info['states']);
        }

        if (isset($info['states'])) {
            $info['state_id'] = [];
            foreach ($info['states'] as $states) {
                if (isset($states)) {
                    $info['state_id'] = array_merge($info['state_id'], (array) $states);
                }
            }
            unset($info['states']);
        }

        if (!empty($info['state_id'])) {
            $types = [];
            foreach ($info['queue'] as $queue) {
                foreach ($this->driver->getTypeIds($queue) as $type) {
                    $types[$type][$queue] = true;
                }
            }
            $filteredQueues = [];
            foreach ($info['state_id'] as $stateId) {
                $state = $this->driver->getState($stateId);
                if (isset($types[$state['type']])) {
                    $filteredQueues = array_merge($filteredQueues, array_keys($types[$state['type']]));
                }
            }
            $info['queue'] = array_intersect($info['queue'], $filteredQueues);
        }

        return $info;
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
                    _("Type: %s; State: %s"),
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
