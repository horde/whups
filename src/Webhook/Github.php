<?php

declare(strict_types=1);

namespace Horde\Whups\Webhook;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * A Webhook receiver.
 */
class Github implements MiddlewareInterface
{
    public function __construct(
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ResponseFactoryInterface $responseFactory
    ) {}
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $returnCode = 200;
        $stream = $this->streamFactory->createStream('1');
        return $this->responseFactory->createResponse($returnCode)->withBody($stream);        // Implement readiness check logic here
    }
}
