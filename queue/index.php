<?php

/**
 * Display open tickets in a queue.
 *
 * Legacy entry point — delegates to the PSR-15 ViewController.
 *
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Michael J. Rubinsk <mrubinsk@horde.org>
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('whups');

use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Whups\Controller\Queue\ViewController;
use Horde\Util\Util;

$requestBuilder = new RequestBuilder(
    new RequestFactory(),
    new StreamFactory(),
    new UriFactory(),
);
$request = $requestBuilder->withGlobalVariables()->build();

$slug = Util::getFormData('slug');
$id = Util::getFormData('id');
$request = $request->withAttribute('route', [
    'slug' => $slug ?: $id,
]);

$controller = new ViewController(
    $whups_driver,
    $notification,
    $page_output,
    $session,
    $prefs->getValue('whups_default_view'),
    $registry->get('webroot', 'whups'),
);

$response = $controller->handle($request);
(new ResponseWriterWeb())->writeResponse($response);
