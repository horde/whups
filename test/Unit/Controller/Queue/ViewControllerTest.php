<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Queue;

use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Http\ServerRequest;
use Horde\Whups\Controller\Queue\ViewController;
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\TopbarSearch;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\Test\Fixtures\HordeGlobalsMockTrait;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Whups_Driver_Sql;

#[CoversClass(ViewController::class)]
class ViewControllerTest extends TestCase
{
    use HordeGlobalsMockTrait;

    private Whups_Driver_Sql $driver;
    private Horde_Notification_Handler $notification;
    private Horde_PageOutput $pageOutput;
    private Horde_Registry $registry;
    private HordeSession $session;
    private TicketSorter $sorter;
    private TopbarSearch $topbarSearch;
    private UrlGenerator $urlGenerator;
    private PrefsService $prefs;
    private mixed $originalDriver;

    protected function setUp(): void
    {
        $this->setUpHordeGlobals();
        $this->originalDriver = $GLOBALS['whups_driver'] ?? null;
        $this->driver = $this->createMock(Whups_Driver_Sql::class);
        $this->notification = $this->createMock(Horde_Notification_Handler::class);
        $this->pageOutput = $this->createMock(Horde_PageOutput::class);
        $this->registry = $this->createMock(Horde_Registry::class);
        $this->registry->method('get')->willReturnMap([
            ['name', null, 'Whups'],
            ['webroot', 'whups', '/whups'],
        ]);
        $this->registry->method('getAuth')->willReturn('test_user');
        $this->session = $this->createMock(HordeSession::class);
        $this->sorter = $this->createMock(TicketSorter::class);
        $this->topbarSearch = $this->createMock(TopbarSearch::class);
        $this->urlGenerator = $this->createMock(UrlGenerator::class);
        $this->urlGenerator->method('urlFor')->willReturn('/whups/queue/test');
        $this->urlGenerator->method('absoluteUrlFor')->willReturn('http://localhost/whups/opensearch');
        $this->urlGenerator->method('getWebroot')->willReturn('/whups');
        $this->urlGenerator->method('defaultViewUrl')->willReturnCallback(
            fn(string $view) => '/whups/' . $view,
        );
        $this->prefs = $this->createMock(PrefsService::class);
        $GLOBALS['whups_driver'] = $this->driver;

        if (!defined('WHUPS_TEMPLATES')) {
            define('WHUPS_TEMPLATES', dirname(__DIR__, 4) . '/templates');
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalDriver !== null) {
            $GLOBALS['whups_driver'] = $this->originalDriver;
        } else {
            unset($GLOBALS['whups_driver']);
        }
        $this->tearDownHordeGlobals();
    }

    private function createController(
        string $defaultView = 'mybugs',
    ): ViewController {
        $this->prefs->method('getValue')->willReturn($defaultView);
        return new ViewController(
            $this->driver,
            $this->notification,
            $this->pageOutput,
            $this->registry,
            $this->session,
            $this->sorter,
            $this->topbarSearch,
            $this->urlGenerator,
            $this->prefs,
        );
    }

    public function testRedirectsWhenNoSlugOrId(): void
    {
        $request = (new ServerRequest('GET', '/whups/queue/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/whups/', $response->getHeaderLine('Location'));
    }

    public function testRedirectsWhenSlugIsNull(): void
    {
        $request = (new ServerRequest('GET', '/whups/queue/'))
            ->withAttribute('route', ['slug' => null]);

        $this->notification->expects($this->once())
            ->method('push')
            ->with($this->stringContains('Invalid'), 'horde.error');

        $response = $this->createController()->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/whups/mybugs', $response->getHeaderLine('Location'));
    }

    public function testUsesDefaultViewInRedirectUrl(): void
    {
        $request = (new ServerRequest('GET', '/whups/queue/'))
            ->withAttribute('route', ['slug' => null]);

        $response = $this->createController(defaultView: 'search')->handle($request);

        $this->assertEquals('/whups/search', $response->getHeaderLine('Location'));
    }

    public function testUsesWebrootInRedirectUrl(): void
    {
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $urlGenerator->method('urlFor')->willReturn('/custom-whups/queue/test');
        $urlGenerator->method('absoluteUrlFor')->willReturn('http://localhost/custom-whups/opensearch');
        $urlGenerator->method('getWebroot')->willReturn('/custom-whups');
        $urlGenerator->method('defaultViewUrl')->willReturnCallback(
            fn(string $view) => '/custom-whups/' . $view,
        );

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn('mybugs');

        $controller = new ViewController(
            $this->driver,
            $this->notification,
            $this->pageOutput,
            $this->registry,
            $this->session,
            $this->sorter,
            $this->topbarSearch,
            $urlGenerator,
            $prefs,
        );

        $request = (new ServerRequest('GET', '/custom-whups/queue/'))
            ->withAttribute('route', []);

        $response = $controller->handle($request);

        $this->assertStringStartsWith('/custom-whups/', $response->getHeaderLine('Location'));
    }

    public function testResolvesNumericSlugAsQueueId(): void
    {
        $this->driver->expects($this->once())
            ->method('getQueue')
            ->with(42)
            ->willReturn(['id' => 42, 'name' => 'Support', 'slug' => 'support']);

        $this->driver->method('getTicketsByProperties')
            ->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/queue/42'))
            ->withAttribute('route', ['slug' => '42']);

        $response = $this->createController()->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResolvesSlugViaDriver(): void
    {
        $this->driver->expects($this->once())
            ->method('getQueueBySlugInternal')
            ->with('support')
            ->willReturn(['id' => 7, 'name' => 'Support', 'slug' => 'support']);

        $this->driver->method('getTicketsByProperties')
            ->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/queue/support'))
            ->withAttribute('route', ['slug' => 'support']);

        $response = $this->createController()->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNumericSlugFallsBackToIdQueryParam(): void
    {
        $this->driver->expects($this->once())
            ->method('getQueue')
            ->with(99)
            ->willReturn(['id' => 99, 'name' => 'Bugs', 'slug' => 'bugs']);

        $this->driver->method('getTicketsByProperties')
            ->willReturn([]);

        $request = (new ServerRequest('GET', '/whups/queue/?id=99'))
            ->withQueryParams(['id' => '99'])
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
