<?php
declare(strict_types=1);
namespace Horde\Whups\Ui;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Horde\Http\Server\DefaultHandlerTrait;
use Horde_Registry;
/**
 * Redirect to the actual whups page configured in prefs.
 */
class WhupsUi implements RequestHandlerInterface
{
    use DefaultHandlerTrait;
    public function bodyContent(): string
    {
        // Preliminary implementation. Just setup whups and forward to the classic page.
        Horde_Registry::appInit('whups');
        ## Remove once the client pages are all converted to proper PSR-7 responses.
        global $browser, $injector, $notification, $page_output, $prefs, $registry;
        $default = $GLOBALS['prefs']->getValue('whups_default_view');
        require basename($default . '.php');
        return ''; // This is a placeholder. The actual output is currently handled by the classic client pages.
    }
}
