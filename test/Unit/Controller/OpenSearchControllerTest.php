<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller;

use Horde\Http\ServerRequest;
use Horde\Whups\Controller\OpenSearchController;
use Horde\Whups\Service\UrlGenerator;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(OpenSearchController::class)]
class OpenSearchControllerTest extends TestCase
{
    private Horde_Registry $registry;
    private UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Horde_Registry::class);
        $this->urlGenerator = $this->createMock(UrlGenerator::class);
    }

    private function createController(): OpenSearchController
    {
        return new OpenSearchController($this->registry, $this->urlGenerator);
    }

    public function testReturnsXmlContentType(): void
    {
        $this->urlGenerator->method('getWebroot')->willReturn('/whups');
        $this->urlGenerator->method('urlFor')->willReturn('/whups/ticket/0');
        $this->registry->method('get')
            ->willReturnMap([
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
        $this->urlGenerator->method('getWebroot')->willReturn('/my-whups');
        $this->urlGenerator->method('urlFor')->willReturn('/my-whups/ticket/0');
        $this->registry->method('get')
            ->willReturnMap([
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
        $this->urlGenerator->method('getWebroot')->willReturn('/whups');
        $this->urlGenerator->method('urlFor')->willReturn('/whups/ticket/0');
        $this->registry->method('get')
            ->willReturnMap([
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
        $this->urlGenerator->method('getWebroot')->willReturn('/whups');
        $this->urlGenerator->method('urlFor')->willReturn('/whups/ticket/0');
        $this->registry->method('get')
            ->willReturnMap([
                ['name', 'whups', 'Whups'],
                ['themesfs', 'whups', '/nonexistent'],
            ]);

        $request = new ServerRequest('GET', '/whups/opensearch');
        $response = $this->createController()->handle($request);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('/whups/ticket/', $body);
    }
}
