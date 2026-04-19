<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Execute a saved query and display results.
 *
 * Loads a saved query by slug or numeric ID, optionally prompts for
 * parameters, executes the query, and renders results via
 * Whups_View_Results.
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

namespace Horde\Whups\Controller\Query;

use Horde\Core\Session\HordeSession;
use Horde\Form\V3\HtmlRenderer;
use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Form\Query\QueryParameterForm;
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\TopbarSearch;
use Horde\Whups\Service\UrlGenerator;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Registry;
use Horde_Url;
use Horde_Variables;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Exception;
use Whups_Query;
use Whups_Query_Manager;
use Whups_View_Results;

class RunController implements RequestHandlerInterface
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
        private readonly UrlGenerator $urlGenerator,
        private readonly Whups_Query_Manager $queryManager,
        private readonly string $defaultView,
        private readonly string $webroot,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $queryParams = $request->getQueryParams();
        $postParams = (array) ($request->getParsedBody() ?? []);
        $allParams = array_merge($queryParams, $postParams);

        // Resolve query: slug from route, then query id, then session.
        $whups_query = $this->resolveQuery($route, $queryParams);

        if (!$whups_query
            || !$whups_query->hasPermission($this->registry->getAuth(), Horde_Perms::READ)
        ) {
            if ($whups_query) {
                $this->notification->push(_("Permission denied."), 'horde.error');
            }
            return $this->redirect($this->webroot . '/' . $this->defaultView);
        }

        // Store in session for other pages (query builder tabs, etc.)
        $this->session->setScoped('whups', 'query', $whups_query);

        // Determine if we can execute or need parameter input.
        $tickets = null;
        $paramFormHtml = '';

        if (!$whups_query->parameters) {
            // No parameters needed — execute immediately.
            $vars = new Horde_Variables($allParams);
            $tickets = $this->driver->executeQuery($whups_query, $vars);
        } else {
            // Query has parameters — show form or validate + execute.
            $form = new QueryParameterForm($allParams, $whups_query->parameters);

            if ($form->isSubmitted() && $form->validate()) {
                $info = $form->getInfo();
                $vars = new Horde_Variables($info);
                $tickets = $this->driver->executeQuery($whups_query, $vars);
            } else {
                $renderer = new HtmlRenderer();
                $runUrl = $this->buildRunUrl($whups_query);
                $paramFormHtml = $renderer->render($form, $runUrl, 'post');
            }
        }

        $title = $whups_query->name ?: _("Query Results");

        $html = $this->renderChrome($title, function () use (
            $whups_query,
            $tickets,
            $paramFormHtml,
            $title,
            $allParams,
        ) {
            $this->topbarSearch->apply();

            // Add query-specific RSS feed link.
            if ($whups_query->id) {
                $feed = $whups_query->feedLink();
                $this->pageOutput->addLinkTag([
                    'href' => $feed['href'],
                    'rel' => 'alternate',
                    'type' => 'application/rss+xml',
                    'title' => $feed['title'],
                ]);
            }

            // Add OpenSearch link.
            $this->pageOutput->addLinkTag([
                'href' => (new Horde_Url($this->webroot . '/opensearch.php', true))->toString(true, false),
                'rel' => 'search',
                'type' => 'application/opensearchdescription+xml',
                'title' => $this->registry->get('name')
                    . ' (' . (new Horde_Url($this->webroot, true))->toString(true, false) . ')',
            ]);

            // Render query tabs.
            $tabVars = new Horde_Variables($allParams);
            $tabs = $whups_query->getTabs($tabVars);
            echo $tabs->render($tabVars->get('action') ?: 'run');

            if ($tickets !== null) {
                // We have results — render them.
                $this->sorter->sort($tickets);

                $runUrl = $this->buildRunUrl($whups_query);
                $subscription = null;
                if ($whups_query->id) {
                    $rssParams = empty($whups_query->slug)
                        ? ['slug' => (string) $whups_query->id]
                        : ['slug' => $whups_query->slug];
                    $rssUrl = $this->urlGenerator->absoluteUrlFor('QueryRss', $rssParams);
                    $subscription = '<a href="' . htmlspecialchars($rssUrl) . '" title="'
                        . htmlspecialchars(_("Subscribe to this query")) . '">'
                        . \Horde_Themes_Image::tag('feed.png', ['alt' => _("Subscribe to this query")])
                        . '</a>';
                }

                $this->session->setScoped('whups', 'last_search', $runUrl);

                $results = new Whups_View_Results([
                    'title' => $title,
                    'results' => $tickets,
                    'extra' => $subscription,
                    'values' => TicketSorter::getSearchResultColumns(),
                    'url' => new Horde_Url($runUrl),
                ]);
                $results->html();
            } else {
                // Show parameter form.
                echo $paramFormHtml;
            }
        });

        return $this->htmlResponse($html);
    }

    private function resolveQuery(array $route, array $queryParams): ?Whups_Query
    {
        $slug = $route['slug'] ?? $queryParams['slug'] ?? null;
        $queryId = $queryParams['query'] ?? null;

        try {
            if ($slug && !is_numeric($slug)) {
                return $this->queryManager->getQueryBySlug($slug);
            }
            if ($slug) {
                return $this->queryManager->getQuery((int) $slug);
            }
            if ($queryId) {
                return $this->queryManager->getQuery((int) $queryId);
            }

            // Fall back to session.
            return $this->session->getScoped('whups', 'query');
        } catch (Whups_Exception $e) {
            $this->notification->push($e->getMessage());
            return null;
        }
    }

    private function buildRunUrl(Whups_Query $query): string
    {
        if (!empty($query->slug)) {
            return $this->urlGenerator->urlFor('QueryRun', ['slug' => $query->slug]);
        }
        if ($query->id) {
            return $this->urlGenerator->urlFor('QueryRun', ['slug' => (string) $query->id]);
        }
        return $this->webroot . '/query/run';
    }
}
