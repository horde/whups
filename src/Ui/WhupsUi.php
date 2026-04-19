<?php

declare(strict_types=1);

namespace Horde\Whups\Ui;

use Horde\Routes\MatchResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Horde_Registry;

/**
 * Dispatch to the classic whups page matching the current route.
 *
 * Uses the typed MatchResult to resolve which legacy PHP file to
 * require based on the matched route name.
 */
class WhupsUi implements RequestHandlerInterface
{
    /**
     * Map route names to classic file paths (relative to fileroot).
     *
     * Keyed by the route name defined in config/routes.php.
     * A null value means the file is resolved from the user's pref.
     */
    private const ROUTE_FILE_MAP = [
        'TicketView'             => 'ticket/index.php',
        'TicketRss'              => 'ticket/rss.php',
        'TicketDeleteAttachment' => 'ticket/delete_attachment.php',
        'TicketDeleteHistory'    => 'ticket/delete_history.php',
        'TicketDeleteMultiple'   => 'ticket/delete_multiple.php',
        'QueueRss'               => 'queue/rss.php',
        'QueryBuilder'           => 'query/index.php',
        'QueryRss'               => 'query/rss.php',
        'Search'                 => 'search.php',
        'SearchRss'              => 'search/rss.php',
        'MyBugs'                 => 'mybugs.php',
        'MyBugsEdit'             => 'mybugs_edit.php',
        'Reports'                => 'reports.php',
        'Admin'                  => 'admin/index.php',
    ];

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $bodyContent = $this->bodyContent($request);
        $body = $this->streamFactory->createStream($bodyContent);
        return $this->responseFactory->createResponse(200)->withBody($body);
    }

    private function bodyContent(ServerRequestInterface $request): string
    {
        // Preliminary implementation. Just setup whups and forward to the classic page.
        Horde_Registry::appInit('whups');
        ## Remove once the client pages are all converted to proper PSR-7 responses.
        global $browser, $conf, $injector, $notification, $page_output, $prefs, $registry, $session, $whups_driver;

        /** @var MatchResult|null $matchResult */
        $matchResult = $request->getAttribute('matchResult');
        $routeName = $matchResult?->getRouteName() ?? '';
        $action = $matchResult?->getAction() ?? 'index';
        $fileroot = $registry->get('fileroot', 'whups');

        if (array_key_exists($routeName, self::ROUTE_FILE_MAP)) {
            $file = self::ROUTE_FILE_MAP[$routeName];
        } elseif ($routeName === 'TicketAction') {
            // /ticket/:id/:action → ticket/{action}.php
            $file = 'ticket/' . basename($action) . '.php';
        } else {
            $file = 'mybugs.php';
        }

        require $fileroot . '/' . $file;
        return ''; // Placeholder. Output is currently handled by the classic client pages.
    }
}
