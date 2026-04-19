<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Ticket search form and results.
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

namespace Horde\Whups\Controller;

use Horde\Form\V3\HtmlRenderer;
use Horde\Util\Util;
use Horde\Whups\Form\SearchForm;
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\TopbarSearch;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde\Core\Session\HordeSession;
use Horde_Url;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Query;
use Whups_Query_Manager;
use Whups_View_Results;
use Whups_View_SavedQueries;

class SearchController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly HordeSession $session,
        private readonly TicketSorter $sorter,
        private readonly TopbarSearch $topbarSearch,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $webroot = $this->registry->get('webroot', 'whups');
        $searchUrl = $webroot . '/search';

        // Pre-load domain data for the form.
        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::READ,
        );
        $typeStates = $this->buildTypeStates($queues);

        $form = new SearchForm($request, $queues, $typeStates);

        $renderer = new HtmlRenderer();
        $results = null;
        $beendone = false;

        $params = $request->getQueryParams();
        $hasSearch = !empty($params['formname'])
            || !empty($params['summary'])
            || !empty($params['states'])
            || Util::getFormData('haveSearch', false);

        if ($hasSearch && $form->validate()) {
            $info = $this->processSearchInfo($form->getInfo(), $queues);

            // "Save as Query" button.
            if ($form->getClickedButton() === _("Save as Query")) {
                return $this->saveAsQuery($info, $params, $webroot);
            }

            // Execute search.
            try {
                $tickets = $this->driver->getTicketsByProperties($info);
                $this->sorter->sort($tickets);
                $resultUrl = new Horde_Url($searchUrl . '?' . $this->buildSearchUrl($params));
                $this->session->setScoped('whups', 'last_search', $resultUrl);
                $results = new Whups_View_Results([
                    'title' => _("Search Results"),
                    'results' => $tickets,
                    'values' => TicketSorter::getSearchResultColumns(),
                    'url' => $resultUrl,
                ]);
                $beendone = true;
            } catch (Whups_Exception $e) {
                $this->notification->push(
                    sprintf(_("There was an error performing your search: %s"), $e->getMessage()),
                    'horde.error',
                );
            }
        }

        $this->pageOutput->ajax = true;

        $html = $this->renderChrome(_("Search"), function () use (
            $form,
            $renderer,
            $results,
            $beendone,
            $searchUrl,
        ) {
            // Topbar search.
            $this->topbarSearch->apply();

            if ($results) {
                $results->html();
                $form->setTitle(_("Refine Search"));
                echo $renderer->render($form, $searchUrl, 'get');
            }

            if (!$beendone) {
                $form->setTitle(_("Ticket Search"));
                echo $renderer->render($form, $searchUrl, 'get');
            }

            // Saved queries list.
            $qManager = new Whups_Query_Manager();
            $auth = $this->registry->getAuth();
            $myqueries = new Whups_View_SavedQueries([
                'title' => $auth ? _("My Queries") : _("Public Queries"),
                'results' => $qManager->listQueries($auth, true),
            ]);
            $myqueries->html();
        });

        return $this->htmlResponse($html);
    }

    /**
     * Build per-type state data for the search form.
     *
     * @param array<int,string> $queues Queue id => name
     * @return array<int,array{typeName:string,states:array<int,string>,defaults:list<int>}>
     *
     * TODO: Duplicated in SearchRssController — extract to a shared service.
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
     * Normalizes queue to an array, flattens per-type states to a flat
     * state_id array, and filters queues to only those with selected states.
     *
     * @param array $info   Raw getInfo() output
     * @param array<int,string> $queues  All readable queues
     * @return array Processed info for getTicketsByProperties()
     *
     * TODO: Duplicated in SearchRssController — extract to a shared service.
     */
    private function processSearchInfo(array $info, array $queues): array
    {
        // Normalize queue to array.
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

        // Flatten per-type states into a single state_id array.
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

        // Filter queues to only those with selected states.
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
     * Build the "Save as Query" query object and redirect to the query builder.
     */
    private function saveAsQuery(array $info, array $params, string $webroot): ResponseInterface
    {
        $qManager = new Whups_Query_Manager();
        $whups_query = $qManager->newQuery();

        if (strlen($info['summary'] ?? '')) {
            $whups_query->insertCriterion(
                '',
                Whups_Query::CRITERION_SUMMARY,
                null,
                Whups_Query::OPERATOR_CI_SUBSTRING,
                $info['summary'],
            );
        }

        if (!empty($params['queue'])) {
            $whups_query->insertCriterion(
                '',
                Whups_Query::CRITERION_QUEUE,
                null,
                Whups_Query::OPERATOR_EQUAL,
                $info['queue'][0],
            );
        }

        // Date range criteria.
        $dateFields = [
            'ticket_timestamp' => Whups_Query::CRITERION_TIMESTAMP,
            'date_updated' => Whups_Query::CRITERION_UPDATED,
            'date_resolved' => Whups_Query::CRITERION_RESOLVED,
            'date_assigned' => Whups_Query::CRITERION_ASSIGNED,
            'date_due' => Whups_Query::CRITERION_DUE,
        ];

        $path = '';
        foreach ($dateFields as $field => $criterion) {
            if (!empty($info[$field]['from']) || !empty($info[$field]['to'])) {
                $path = $whups_query->insertBranch('', Whups_Query::TYPE_AND);
                break;
            }
        }

        foreach ($dateFields as $field => $criterion) {
            if (!empty($info[$field]['from'])) {
                $whups_query->insertCriterion(
                    $path,
                    $criterion,
                    null,
                    Whups_Query::OPERATOR_GREATER,
                    $info[$field]['from'],
                );
            }
            if (!empty($info[$field]['to'])) {
                $whups_query->insertCriterion(
                    $path,
                    $criterion,
                    null,
                    Whups_Query::OPERATOR_LESS,
                    $info[$field]['to'],
                );
            }
        }

        // State criteria.
        if (!empty($info['state_id'])) {
            $statePath = $whups_query->insertBranch('', Whups_Query::TYPE_OR);
            foreach ($info['state_id'] as $state) {
                $whups_query->insertCriterion(
                    $statePath,
                    Whups_Query::CRITERION_STATE,
                    null,
                    Whups_Query::OPERATOR_EQUAL,
                    $state,
                );
            }
        }

        $this->session->setScoped('whups', 'query', $whups_query);

        return $this->redirect(
            (new Horde_Url($webroot . '/query/builder', true))
                ->add('action', 'save')
                ->toString(),
        );
    }

    /**
     * Reconstruct a URL query string representing the current search parameters.
     *
     * @param array<string,mixed> $params Query parameters
     */
    private function buildSearchUrl(array $params): string
    {
        $qUrl = new Horde_Url();

        $queue = (int) ($params['queue'] ?? 0);
        $qUrl->add(['queue' => $queue]);

        $summary = $params['summary'] ?? '';
        if ($summary) {
            $qUrl->add('summary', $summary);
        }

        $states = $params['states'] ?? null;
        if (is_array($states)) {
            foreach ($states as $type => $state) {
                if (is_array($state)) {
                    foreach ($state as $s) {
                        $qUrl->add("states[$type][]", $s);
                    }
                } else {
                    $qUrl->add("states[$type]", $state);
                }
            }
        }

        return substr((string) $qUrl, 1);
    }
}
