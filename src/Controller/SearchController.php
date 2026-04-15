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

use Horde\Util\Util;
use Horde_Form_Renderer;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_Session;
use Horde_Url;
use Horde_Variables;
use Horde_View_Topbar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Form_Search;
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
        private readonly Horde_Session $session,
        private readonly Horde_View_Topbar $topbar,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $webroot = $this->registry->get('webroot', 'whups');
        $vars = Horde_Variables::getDefaultVariables();

        $form = new Whups_Form_Search($vars);
        $results = null;
        $beendone = false;

        $hasSearch = $vars->get('formname')
            || $vars->get('summary')
            || $vars->get('states')
            || Util::getFormData('haveSearch', false);

        if ($hasSearch && $form->validate($vars, true)) {
            $info = $form->getInfo($vars);

            // "Save as Query" button — build query and redirect to builder.
            if ($vars->get('submitbutton') == _("Save as Query")) {
                return $this->saveAsQuery($info, $vars, $webroot);
            }

            // Execute search.
            try {
                $tickets = $this->driver->getTicketsByProperties($info);
                Whups::sortTickets($tickets);
                $searchUrl = new Horde_Url($webroot . '/search?' . $this->buildSearchUrl($vars));
                $this->session->set('whups', 'last_search', $searchUrl);
                $results = new Whups_View_Results([
                    'title' => _("Search Results"),
                    'results' => $tickets,
                    'values' => Whups::getSearchResultColumns(),
                    'url' => $searchUrl,
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
            $vars,
            $form,
            $results,
            $beendone,
            $webroot,
        ) {
            // Topbar search.
            $this->topbar->search = true;
            $this->topbar->searchAction = new Horde_Url($webroot . '/ticket');
            $this->topbar->searchLabel = $this->session->get('whups', 'search') ?: _("Ticket #Id");

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            $renderer = new Horde_Form_Renderer();
            $searchUrl = new Horde_Url($webroot . '/search');

            if ($results) {
                $results->html();
                $form->setTitle(_("Refine Search"));
                $form->renderActive($renderer, $vars, $searchUrl, 'get');
            }

            if (!$beendone) {
                $form->setTitle(_("Ticket Search"));
                $form->renderActive($renderer, $vars, $searchUrl, 'get');
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
     * Build the "Save as Query" query object and redirect to the query builder.
     */
    private function saveAsQuery(array $info, Horde_Variables $vars, string $webroot): ResponseInterface
    {
        $qManager = new Whups_Query_Manager();
        $whups_query = $qManager->newQuery();

        if (strlen($info['summary'])) {
            $whups_query->insertCriterion(
                '',
                Whups_Query::CRITERION_SUMMARY,
                null,
                Whups_Query::OPERATOR_CI_SUBSTRING,
                $info['summary'],
            );
        }

        if ($vars->get('queue')) {
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
        if ($info['state_id']) {
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

        $this->session->set('whups', 'query', $whups_query);

        return $this->redirect(
            (new Horde_Url($webroot . '/query/builder', true))
                ->add('action', 'save')
                ->toString(),
        );
    }

    /**
     * Reconstruct a URL query string representing the current search parameters.
     */
    private function buildSearchUrl(Horde_Variables $vars): string
    {
        $qUrl = new Horde_Url();

        $queue = (int) $vars->get('queue');
        $qUrl->add(['queue' => $queue]);

        $summary = $vars->get('summary');
        if ($summary) {
            $qUrl->add('summary', $summary);
        }

        $states = $vars->get('states');
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
