<?php

/**
 * OpenSearch XML descriptor.
 *
 * Legacy entry point — delegates to the PSR-15 OpenSearchController.
 *
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Jan Schneider <jan@horde.org>
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('whups');

use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Whups\Controller\OpenSearchController;

$requestBuilder = new RequestBuilder(
    new RequestFactory(),
    new StreamFactory(),
    new UriFactory(),
);
$request = $requestBuilder->withGlobalVariables()->build();

$controller = new OpenSearchController($registry);

$response = $controller->handle($request);
(new ResponseWriterWeb())->writeResponse($response);
