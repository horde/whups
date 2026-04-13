<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller;

use Horde\Whups\Controller\ResponseTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversNothing
 */
class ResponseTraitTest extends TestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        $this->traitUser = new class {
            use ResponseTrait;

            public function testXmlResponse(string $xml, int $status = 200): ResponseInterface
            {
                return $this->xmlResponse($xml, $status);
            }

            public function testDownloadResponse(
                string $content,
                string $filename,
                string $contentType = 'application/octet-stream',
                bool $inline = false,
            ): ResponseInterface {
                return $this->downloadResponse($content, $filename, $contentType, $inline);
            }

            public function testHtmlResponse(string $html, int $status = 200): ResponseInterface
            {
                return $this->htmlResponse($html, $status);
            }

            public function testRedirect(string $url, int $status = 302): ResponseInterface
            {
                return $this->redirect($url, $status);
            }
        };
    }

    public function testXmlResponseSetsCorrectContentType(): void
    {
        $xml = '<?xml version="1.0"?><root><item>test</item></root>';
        $response = $this->traitUser->testXmlResponse($xml);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals($xml, (string) $response->getBody());
    }

    public function testXmlResponseWithCustomStatus(): void
    {
        $response = $this->traitUser->testXmlResponse('<empty/>', 404);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDownloadResponseSetsHeaders(): void
    {
        $response = $this->traitUser->testDownloadResponse('file content', 'test.txt', 'text/plain');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('test.txt', $response->getHeaderLine('Content-Disposition'));
        $this->assertEquals('12', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('file content', (string) $response->getBody());
    }

    public function testDownloadResponseInline(): void
    {
        $response = $this->traitUser->testDownloadResponse('data', 'image.png', 'image/png', true);
        $this->assertStringContainsString('inline', $response->getHeaderLine('Content-Disposition'));
    }

    public function testHtmlResponseFromBaseTrait(): void
    {
        $html = '<h1>Hello</h1>';
        $response = $this->traitUser->testHtmlResponse($html);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals($html, (string) $response->getBody());
    }

    public function testRedirectFromBaseTrait(): void
    {
        $response = $this->traitUser->testRedirect('/whups/mybugs');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/whups/mybugs', $response->getHeaderLine('Location'));
    }

    public function testRedirectPermanent(): void
    {
        $response = $this->traitUser->testRedirect('/new-location', 301);
        $this->assertEquals(301, $response->getStatusCode());
    }
}
