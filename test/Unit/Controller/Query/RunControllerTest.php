<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Query;

use Horde\Core\Service\PrefsService;
use Horde\Core\Session\SessionAccess;
use Horde\Http\ServerRequest;
use Horde\Routes\Mapper;
use Horde\Routes\Utils;
use Horde\Whups\Controller\Query\RunController;
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\TopbarSearch;
use Horde\Whups\Service\UrlGenerator;
use Horde_Injector;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Horde_Perms_Base;
use Horde_Prefs;
use Horde_Registry;
use Horde_Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Whups_Driver_Sql;
use Whups_Query;
use Whups_Query_Manager;
use Horde_Browser;

#[CoversClass(RunController::class)]
class RunControllerTest extends TestCase
{
    private Whups_Driver_Sql $driver;
    private Horde_Notification_Handler $notification;
    private Horde_PageOutput $pageOutput;
    private Horde_Registry $registry;
    private SessionAccess $session;
    private TicketSorter $sorter;
    private TopbarSearch $topbarSearch;
    private UrlGenerator $urlGenerator;
    private Whups_Query_Manager $queryManager;
    private PrefsService $prefs;
    private array $savedGlobals = [];

    protected function setUp(): void
    {
        // Save globals.
        foreach (['registry', 'injector', 'session', 'page_output', 'notification', 'prefs', 'conf', 'whups_driver', 'browser'] as $key) {
            $this->savedGlobals[$key] = $GLOBALS[$key] ?? null;
        }

        // Registry mock.
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('getAuth')->willReturn('test_user');
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('get')->willReturnMap([
            ['name', null, 'Whups'],
            ['webroot', 'whups', '/whups'],
            ['webroot', 'horde', '/horde'],
        ]);
        $GLOBALS['registry'] = $registry;

        // Injector mock — includes Routes\Mapper for Whups::urlFor().
        $perms = $this->createMock(Horde_Perms_Base::class);
        $perms->method('exists')->willReturn(true);
        $perms->method('hasPermission')->willReturn(false);

        $topbar = new stdClass();
        $topbar->search = false;
        $topbar->searchAction = null;
        $topbar->searchLabel = '';

        $utils = $this->createMock(Utils::class);
        $utils->method('urlFor')->willReturn('/whups/query/test');
        $mapper = $this->createMock(Mapper::class);
        $mapper->utils = $utils;

        $injector = $this->createMock(Horde_Injector::class);
        $injector->method('getInstance')->willReturnMap([
            ['Horde_Perms', $perms],
            ['Horde_View_Topbar', $topbar],
            ['Horde\Routes\Mapper', $mapper],
        ]);
        $GLOBALS['injector'] = $injector;

        $legacySession = $this->createMock(Horde_Session::class);
        $legacySession->method('get')->willReturn('');
        $GLOBALS['session'] = $legacySession;

        $GLOBALS['page_output'] = $this->createMock(Horde_PageOutput::class);
        $GLOBALS['notification'] = $this->createMock(Horde_Notification_Handler::class);

        $prefs = $this->createMock(Horde_Prefs::class);
        $prefs->method('getValue')->willReturn('');
        $GLOBALS['prefs'] = $prefs;
        $GLOBALS['conf'] = ['tickets' => ['search_results' => []], 'share' => []];

        // Browser mock needed by Horde_Core_Ui_Tabs::render().
        $browser = $this->createMock(Horde_Browser::class);
        $browser->method('hasFeature')->willReturn(false);
        $GLOBALS['browser'] = $browser;

        // Controller dependencies.
        $this->driver = $this->createMock(Whups_Driver_Sql::class);
        $GLOBALS['whups_driver'] = $this->driver;

        $this->notification = $this->createMock(Horde_Notification_Handler::class);
        $this->pageOutput = $this->createMock(Horde_PageOutput::class);
        $this->registry = $registry;
        $this->session = $this->createMock(SessionAccess::class);
        $this->sorter = $this->createMock(TicketSorter::class);
        $this->topbarSearch = $this->createMock(TopbarSearch::class);
        $this->urlGenerator = $this->createMock(UrlGenerator::class);
        $this->urlGenerator->method('urlFor')->willReturn('/whups/query/test');
        $this->urlGenerator->method('absoluteUrlFor')->willReturn('http://localhost/whups/query/test/rss');
        $this->urlGenerator->method('getWebroot')->willReturn('/whups');
        $this->urlGenerator->method('defaultViewUrl')->willReturnCallback(
            fn(string $view) => '/whups/' . $view,
        );
        $this->queryManager = $this->createMock(Whups_Query_Manager::class);
        $this->prefs = $this->createMock(PrefsService::class);

        if (!defined('WHUPS_TEMPLATES')) {
            define('WHUPS_TEMPLATES', dirname(__DIR__, 4) . '/templates');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $key => $value) {
            if ($value !== null) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
        $this->savedGlobals = [];
    }

    private function createController(
        string $defaultView = 'mybugs',
    ): RunController {
        $this->prefs->method('getValue')->willReturn($defaultView);
        return new RunController(
            $this->driver,
            $this->notification,
            $this->pageOutput,
            $this->registry,
            $this->session,
            $this->sorter,
            $this->topbarSearch,
            $this->urlGenerator,
            $this->queryManager,
            $this->prefs,
        );
    }

    private function createTestQuery(
        ?int $id = null,
        string $name = 'Test Query',
        string $slug = 'test-query',
        array $parameters = [],
        bool $hasReadPermission = true,
    ): Whups_Query {
        $manager = $this->createMock(Whups_Query_Manager::class);
        $manager->method('hasPermission')->willReturn($hasReadPermission);

        return new Whups_Query(
            $manager,
            [
                'query_id' => $id,
                'query_name' => $name,
                'query_slug' => $slug,
                'query_parameters' => serialize($parameters),
                'query_object' => serialize([]),
            ],
        );
    }

    // --- Redirect / error paths ---

    public function testRedirectsWhenNoQueryFound(): void
    {
        $this->session->method('getScoped')->willReturn(null);

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/whups/mybugs', $response->getHeaderLine('Location'));
    }

    public function testRedirectsWhenPermissionDenied(): void
    {
        $query = $this->createTestQuery(id: 5, hasReadPermission: false);
        $this->session->method('getScoped')->willReturn($query);

        $this->notification->expects($this->once())
            ->method('push')
            ->with('Permission denied.', 'horde.error');

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testUsesDefaultViewInRedirectUrl(): void
    {
        $this->session->method('getScoped')->willReturn(null);

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $response = $this->createController(defaultView: 'search')->handle($request);

        $this->assertEquals('/whups/search', $response->getHeaderLine('Location'));
    }

    public function testUsesWebrootInRedirectUrl(): void
    {
        $this->session->method('getScoped')->willReturn(null);

        $urlGenerator = $this->createMock(UrlGenerator::class);
        $urlGenerator->method('urlFor')->willReturn('/custom-whups/query/test');
        $urlGenerator->method('absoluteUrlFor')->willReturn('http://localhost/custom-whups/opensearch');
        $urlGenerator->method('getWebroot')->willReturn('/custom-whups');
        $urlGenerator->method('defaultViewUrl')->willReturnCallback(
            fn(string $view) => '/custom-whups/' . $view,
        );

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn('mybugs');

        $controller = new RunController(
            $this->driver,
            $this->notification,
            $this->pageOutput,
            $this->registry,
            $this->session,
            $this->sorter,
            $this->topbarSearch,
            $urlGenerator,
            $this->queryManager,
            $prefs,
        );

        $request = (new ServerRequest('GET', '/custom-whups/query/'))
            ->withAttribute('route', []);

        $response = $controller->handle($request);

        $this->assertStringStartsWith('/custom-whups/', $response->getHeaderLine('Location'));
    }

    // --- Render paths ---

    public function testRendersResultsForParameterlessQuery(): void
    {
        $query = $this->createTestQuery(id: 10, parameters: []);
        $this->session->method('getScoped')->willReturn($query);
        $this->driver->method('executeQuery')->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowsParameterFormWhenNotSubmitted(): void
    {
        $query = $this->createTestQuery(id: 10, parameters: ['username', 'project']);
        $this->session->method('getScoped')->willReturn($query);

        $this->driver->expects($this->never())->method('executeQuery');

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('username', $body);
        $this->assertStringContainsString('project', $body);
    }

    public function testExecutesQueryWhenParameterFormSubmitted(): void
    {
        $query = $this->createTestQuery(id: 10, parameters: ['username']);
        $this->session->method('getScoped')->willReturn($query);

        $this->driver->expects($this->once())
            ->method('executeQuery')
            ->willReturn([]);

        $request = (new ServerRequest('POST', '/whups/query/'))
            ->withAttribute('route', [])
            ->withParsedBody([
                'formname' => 'horde_whups_form_query_queryparameterform',
                'username' => 'alice',
            ]);

        $response = $this->createController()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSessionFallbackLoadsQuery(): void
    {
        $query = $this->createTestQuery(id: 42, name: 'My Saved Query');
        $this->session->method('getScoped')
            ->willReturnMap([['whups', 'query', $query]]);
        $this->driver->method('executeQuery')->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStoresQueryInSession(): void
    {
        $query = $this->createTestQuery(id: 10);
        $this->session->method('getScoped')->willReturn($query);
        $this->driver->method('executeQuery')->willReturn([]);

        $this->session->expects($this->atLeastOnce())
            ->method('setScoped')
            ->with('whups', $this->logicalOr('query', 'last_search'), $this->anything());

        $request = (new ServerRequest('GET', '/whups/query/'))
            ->withAttribute('route', []);

        $this->createController()->handle($request);
    }

    // --- Query resolution paths ---

    public function testResolvesQueryBySlug(): void
    {
        $query = $this->createTestQuery(id: 5, slug: 'open-bugs');
        $this->queryManager->expects($this->once())
            ->method('getQueryBySlug')
            ->with('open-bugs')
            ->willReturn($query);

        $this->driver->method('executeQuery')->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/query/open-bugs'))
            ->withAttribute('route', ['slug' => 'open-bugs']);

        $response = $this->createController()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResolvesQueryByNumericSlug(): void
    {
        $query = $this->createTestQuery(id: 42);
        $this->queryManager->expects($this->once())
            ->method('getQuery')
            ->with(42)
            ->willReturn($query);

        $this->driver->method('executeQuery')->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/query/42'))
            ->withAttribute('route', ['slug' => '42']);

        $response = $this->createController()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
