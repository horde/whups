<?php

/**
 * RSS feed for a single ticket's history.
 *
 * Legacy entry point — delegates to the PSR-15 RssController.
 *
 * Copyright 2001-2026 Robert E. Coyle <robertecoyle@hotmail.com>
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('whups');

use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Whups\Controller\Ticket\RssController;
use Horde\Util\Util;

$requestBuilder = new RequestBuilder(
    new RequestFactory(),
    new StreamFactory(),
    new UriFactory(),
);
$request = $requestBuilder->withGlobalVariables()->build();
$request = $request->withAttribute('route', [
    'id' => Util::getFormData('id'),
]);

$controller = $injector->getInstance(RssController::class);

$response = $controller->handle($request);
(new ResponseWriterWeb())->writeResponse($response);
