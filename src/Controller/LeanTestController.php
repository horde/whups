<?php

declare(strict_types=1);

namespace Horde\Whups\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;

class LeanTestController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->htmlResponse('<html><body><h1>Lean bootstrap works</h1></body></html>');
    }
}
