<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller;

use Horde\Http\ServerRequest;
use Horde\Whups\Controller\OpenSearchController;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(OpenSearchController::class)]
class OpenSearchControllerTest extends TestCase
{
    private Horde_Registry $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Horde_Registry::class);
    }

    private function createController(): OpenSearchController
    {
        return new OpenSearchController($this->registry);
    }

    public function testReturnsXmlContentType(): void
    {
        $this->registry->method('get')
            ->willReturnMap([
                ['webroot', 'whups', '/whups'],
                ['name', 'whups', 'Whups'],
                ['themesfs', 'whups', '/nonexistent'],
            ]);

        $request = new ServerRequest('GET', '/whups/opensearch');
        $response = $this->createController()->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('xml', $response->getHeaderLine('Content-Type'));
    }

    public function testXmlContainsWebroot(): void
    {
        $this->registry->method('get')
            ->willReturnMap([
                ['webroot', 'whups', '/my-whups'],
                ['name', 'whups', 'Bug Tracker'],
                ['themesfs', 'whups', '/nonexistent'],
            ]);

        $request = new ServerRequest('GET', '/my-whups/opensearch');
        $response = $this->createController()->handle($request);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('/my-whups', $body);
        $this->assertStringContainsString('Bug Tracker', $body);
    }

    public function testXmlContainsOpenSearchStructure(): void
    {
        $this->registry->method('get')
            ->willReturnMap([
                ['webroot', 'whups', '/whups'],
                ['name', 'whups', 'Whups'],
                ['themesfs', 'whups', '/nonexistent'],
            ]);

        $request = new ServerRequest('GET', '/whups/opensearch');
        $response = $this->createController()->handle($request);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('OpenSearchDescription', $body);
        $this->assertStringContainsString('ShortName', $body);
        $this->assertStringContainsString('SearchForm', $body);
        $this->assertStringContainsString('{searchTerms}', $body);
    }

    public function testXmlContainsTicketSearchUrl(): void
    {
        $this->registry->method('get')
            ->willReturnMap([
                ['webroot', 'whups', '/whups'],
                ['name', 'whups', 'Whups'],
                ['themesfs', 'whups', '/nonexistent'],
            ]);

        $request = new ServerRequest('GET', '/whups/opensearch');
        $response = $this->createController()->handle($request);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('/whups/ticket/', $body);
    }
}
