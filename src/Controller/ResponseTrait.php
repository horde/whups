<?php

declare(strict_types=1);

/**
 * Shared response helpers for Whups PSR-15 controllers.
 *
 * Composes Core's HtmlResponseTrait and RedirectResponseTrait for standard
 * response building, and adds xmlResponse() for RSS/OpenSearch output and
 * renderChrome() for legacy Horde_PageOutput chrome wrapping.
 *
 * Expects the using class to have properties:
 *   - Horde_Notification_Handler $notification  (for renderChrome)
 *   - Horde_PageOutput           $pageOutput    (for renderChrome)
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller;

use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Psr\Http\Message\ResponseInterface;

trait ResponseTrait
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    /**
     * Create an XML response (RSS, OpenSearch, etc.)
     */
    protected function xmlResponse(string $xml, int $status = 200): ResponseInterface
    {
        $streamFactory = new StreamFactory();
        $response = new Response();

        return $response
            ->withBody($streamFactory->createStream($xml))
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withStatus($status);
    }

    /**
     * Create a binary download response.
     */
    protected function downloadResponse(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
        bool $inline = false,
    ): ResponseInterface {
        $streamFactory = new StreamFactory();
        $response = new Response();
        $disposition = $inline ? 'inline' : 'attachment';

        return $response
            ->withBody($streamFactory->createStream($content))
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', $disposition . '; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($content))
            ->withStatus(200);
    }

    /**
     * Render page content inside the Horde chrome (topbar, header, footer).
     *
     * The callable $renderBody is expected to echo its output.
     */
    private function renderChrome(string $title, callable $renderBody): string
    {
        ob_start();
        $this->pageOutput->header(['title' => $title]);
        $this->notification->notify(['listeners' => 'status']);
        $renderBody();
        $this->pageOutput->footer();

        return ob_get_clean();
    }
}
