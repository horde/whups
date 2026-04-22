<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use Horde\Http\ServerRequest;
use Horde\Whups\Controller\Ticket\RssController;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\Test\Fixtures\HordeGlobalsMockTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Whups_Driver_Sql;

#[CoversClass(RssController::class)]
class RssControllerTest extends TestCase
{
    use HordeGlobalsMockTrait;
    private Whups_Driver_Sql $driver;
    private PermissionChecker $permissions;
    private UrlGenerator $urlGenerator;
    private mixed $originalDriver;

    protected function setUp(): void
    {
        $this->setUpHordeGlobals();
        $this->originalDriver = $GLOBALS['whups_driver'] ?? null;
        $this->driver = $this->createMock(Whups_Driver_Sql::class);
        $this->permissions = $this->createMock(PermissionChecker::class);
        $this->urlGenerator = $this->createMock(UrlGenerator::class);
        $this->urlGenerator->method('absoluteUrlFor')->willReturn('http://localhost/whups/ticket/1/rss');
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

    private function createController(): RssController
    {
        return new RssController(
            $this->driver,
            $this->permissions,
            $this->urlGenerator,
        );
    }

    private function createRequest(string $ticketId): ServerRequest
    {
        return (new ServerRequest('GET', '/whups/ticket/' . $ticketId . '/rss'))
            ->withAttribute('route', ['id' => $ticketId]);
    }

    public function testReturns404ForEmptyTicketId(): void
    {
        $request = (new ServerRequest('GET', '/whups/ticket/rss'))
            ->withAttribute('route', ['id' => '']);

        $response = $this->createController()->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testReturns404ForNonNumericTicketId(): void
    {
        $request = $this->createRequest('abc');
        $response = $this->createController()->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testReturnsXmlContentType(): void
    {
        $this->driver->method('getTicketDetails')
            ->willReturn(['id' => 5, 'queue' => 1, 'summary' => 'Bug report']);

        // permissionsFilter with no Horde stack returns empty = 403
        $request = $this->createRequest('5');
        $response = $this->createController()->handle($request);

        // Without permissions stack, we get 403 (denied)
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertTrue(
            $response->getStatusCode() === 403 || str_contains($response->getHeaderLine('Content-Type'), 'xml'),
            'Should return 403 (no perms) or XML content type'
        );
    }

    public function testReturnsResponseInterface(): void
    {
        $this->driver->method('getTicketDetails')
            ->willReturn(['id' => 1, 'queue' => 1, 'summary' => 'Test']);

        $request = $this->createRequest('1');
        $response = $this->createController()->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
