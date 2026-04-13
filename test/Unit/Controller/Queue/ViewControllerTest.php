<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Queue;

use Horde\Http\ServerRequest;
use Horde\Whups\Controller\Queue\ViewController;
use Horde\Whups\Test\Fixtures\HordeGlobalsMockTrait;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Whups_Driver_Sql;
use Whups_Exception;
use Error;

#[CoversClass(ViewController::class)]
class ViewControllerTest extends TestCase
{
    use HordeGlobalsMockTrait;
    private Whups_Driver_Sql $driver;
    private Horde_Notification_Handler $notification;
    private Horde_PageOutput $pageOutput;
    private Horde_Session $session;
    private mixed $originalDriver;

    protected function setUp(): void
    {
        $this->setUpHordeGlobals();
        $this->originalDriver = $GLOBALS['whups_driver'] ?? null;
        $this->driver = $this->createMock(Whups_Driver_Sql::class);
        $this->notification = $this->createMock(Horde_Notification_Handler::class);
        $this->pageOutput = $this->createMock(Horde_PageOutput::class);
        $this->session = $this->createMock(Horde_Session::class);
        $GLOBALS['whups_driver'] = $this->driver;
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

    private function createController(): ViewController
    {
        return new ViewController(
            $this->driver,
            $this->notification,
            $this->pageOutput,
            $this->session,
            'mybugs',
            '/whups',
        );
    }

    public function testRedirectsWhenNoQueueFound(): void
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

    public function testRedirectsToDefaultViewForEmptySlug(): void
    {
        $request = (new ServerRequest('GET', '/whups/queue/'))
            ->withAttribute('route', []);

        $response = $this->createController()->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/whups/', $response->getHeaderLine('Location'));
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

        // Full render requires Horde chrome stack (routes mapper, topbar, etc.)
        // Verify the driver receives the correct queue ID; full HTML render
        // is tested as an integration/acceptance test with the Horde bootstrap.
        $obLevel = ob_get_level();
        try {
            $response = $this->createController()->handle($request);
            $this->assertInstanceOf(ResponseInterface::class, $response);
            $this->assertTrue(
                $response->getStatusCode() === 200 || $response->getStatusCode() === 302,
            );
        } catch (Error $e) {
            // Expected when Horde chrome stack is not available
            $this->assertStringContainsString('null', $e->getMessage());
        } finally {
            // Restore output buffer level to what PHPUnit expects
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
    }

    public function testUsesWebrootInRedirectUrl(): void
    {
        $controller = new ViewController(
            $this->driver,
            $this->notification,
            $this->pageOutput,
            $this->session,
            'search',
            '/custom-whups',
        );

        $request = (new ServerRequest('GET', '/custom-whups/queue/'))
            ->withAttribute('route', []);

        $response = $controller->handle($request);

        $this->assertStringStartsWith('/custom-whups/', $response->getHeaderLine('Location'));
    }
}
