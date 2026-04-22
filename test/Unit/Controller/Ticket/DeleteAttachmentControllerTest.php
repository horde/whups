<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use Horde\Core\Service\PrefsService;
use Horde\Http\ServerRequest;
use Horde\Whups\Controller\Ticket\DeleteAttachmentController;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\Test\Fixtures\HordeGlobalsMockTrait;
use Horde_Notification_Handler;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Whups_Driver_Sql;

#[CoversClass(DeleteAttachmentController::class)]
class DeleteAttachmentControllerTest extends TestCase
{
    use HordeGlobalsMockTrait;
    private Whups_Driver_Sql $driver;
    private Horde_Notification_Handler $notification;
    private Horde_Registry $registry;
    private PrefsService $prefs;
    private PermissionChecker $permissions;
    private UrlGenerator $urlGenerator;
    private mixed $originalDriver;

    protected function setUp(): void
    {
        $this->setUpHordeGlobals();
        $this->originalDriver = $GLOBALS['whups_driver'] ?? null;
        $this->driver = $this->createMock(Whups_Driver_Sql::class);
        $this->notification = $this->createMock(Horde_Notification_Handler::class);
        $this->registry = $this->createMock(Horde_Registry::class);
        $this->registry->method('getAuth')->willReturn('test_user');
        $this->prefs = $this->createMock(PrefsService::class);
        $this->prefs->method('getValue')->willReturn('mybugs');
        $this->permissions = $this->createMock(PermissionChecker::class);
        $this->urlGenerator = $this->createMock(UrlGenerator::class);
        $this->urlGenerator->method('urlFor')->willReturn('/whups/ticket/5');
        $this->urlGenerator->method('getWebroot')->willReturn('/whups');
        $this->urlGenerator->method('defaultViewUrl')->willReturnCallback(
            fn(string $view) => '/whups/' . $view,
        );
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

    private function createController(): DeleteAttachmentController
    {
        return new DeleteAttachmentController(
            $this->driver,
            $this->notification,
            $this->registry,
            $this->prefs,
            $this->permissions,
            $this->urlGenerator,
        );
    }

    private function createRequest(string $ticketId, array $queryParams = []): ServerRequest
    {
        return (new ServerRequest('POST', '/whups/ticket/' . $ticketId . '/delete-attachment'))
            ->withAttribute('route', ['id' => $ticketId])
            ->withQueryParams($queryParams);
    }

    public function testReturnsRedirectResponse(): void
    {
        $this->driver->method('getTicketDetails')
            ->willReturn(['id' => 5, 'queue' => 1, 'summary' => 'Test']);

        $request = $this->createRequest('5', ['file' => 'test.txt']);
        $response = $this->createController()->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testRedirectsToDefaultViewOnPermissionDenied(): void
    {
        $this->driver->method('getTicketDetails')
            ->willReturn(['id' => 5, 'queue' => 1, 'summary' => 'Test']);

        $request = $this->createRequest('5', ['file' => 'exploit.sh']);
        $response = $this->createController()->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/whups/mybugs', $response->getHeaderLine('Location'));
    }

    public function testHandlesFileAndMessageParams(): void
    {
        $this->driver->method('getTicketDetails')
            ->willReturn(['id' => 3, 'queue' => 1, 'summary' => 'Bug']);

        // With file param
        $request = $this->createRequest('3', ['file' => 'doc.pdf']);
        $response = $this->createController()->handle($request);
        $this->assertEquals(302, $response->getStatusCode());

        // With message param (no file)
        $request = $this->createRequest('3', ['message' => '42']);
        $response = $this->createController()->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testUsesWebrootInRedirectUrl(): void
    {
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $urlGenerator->method('urlFor')->willReturn('/custom-whups/ticket/1');
        $urlGenerator->method('getWebroot')->willReturn('/custom-whups');
        $urlGenerator->method('defaultViewUrl')->willReturnCallback(
            fn(string $view) => '/custom-whups/' . $view,
        );

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn('search');

        $controller = new DeleteAttachmentController(
            $this->driver,
            $this->notification,
            $this->registry,
            $prefs,
            $this->permissions,
            $urlGenerator,
        );

        $this->driver->method('getTicketDetails')
            ->willReturn(['id' => 1, 'queue' => 1, 'summary' => 'T']);

        $request = $this->createRequest('1', ['file' => 'a.txt']);
        $response = $controller->handle($request);

        $this->assertStringStartsWith('/custom-whups/', $response->getHeaderLine('Location'));
    }
}
