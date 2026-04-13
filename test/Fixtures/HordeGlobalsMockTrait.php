<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Fixtures;

use Horde_Injector;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms_Base;
use Horde_Prefs;
use Horde_Registry;
use Horde_Session;
use stdClass;

/**
 * Sets up minimal mock globals ($registry, $injector, $session) so that
 * static methods in Whups:: and Whups_Ticket:: can execute without
 * crashing on null globals.
 *
 * Without a full Horde bootstrap, Whups::hasPermission() will return false
 * and Whups::permissionsFilter() will return empty arrays — which lets us
 * test the "permission denied" paths of controllers.
 */
trait HordeGlobalsMockTrait
{
    private array $savedGlobals = [];

    protected function setUpHordeGlobals(): void
    {
        $keys = ['registry', 'injector', 'session', 'page_output', 'notification', 'prefs', 'conf'];
        foreach ($keys as $key) {
            $this->savedGlobals[$key] = $GLOBALS[$key] ?? null;
        }

        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('getAuth')->willReturn('test_user');
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('get')->willReturnMap([
            ['webroot', 'whups', '/whups'],
        ]);
        $GLOBALS['registry'] = $registry;

        $perms = $this->createMock(Horde_Perms_Base::class);
        $perms->method('exists')->willReturn(true);
        $perms->method('hasPermission')->willReturn(false);

        $topbar = new stdClass();
        $topbar->search = false;
        $topbar->searchAction = null;
        $topbar->searchLabel = '';

        $injector = $this->createMock(Horde_Injector::class);
        $injector->method('getInstance')->willReturnMap([
            ['Horde_Perms', $perms],
            ['Horde_View_Topbar', $topbar],
        ]);
        $GLOBALS['injector'] = $injector;

        $session = $this->createMock(Horde_Session::class);
        $session->method('get')->willReturn('');
        $GLOBALS['session'] = $session;

        $pageOutput = $this->createMock(Horde_PageOutput::class);
        $GLOBALS['page_output'] = $pageOutput;

        $notification = $this->createMock(Horde_Notification_Handler::class);
        $GLOBALS['notification'] = $notification;

        $prefs = $this->createMock(Horde_Prefs::class);
        $prefs->method('getValue')->willReturn('');
        $GLOBALS['prefs'] = $prefs;

        $GLOBALS['conf'] = ['tickets' => ['search_results' => []]];
    }

    protected function tearDownHordeGlobals(): void
    {
        foreach ($this->savedGlobals as $key => $value) {
            if ($value !== null) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
        $this->savedGlobals = [];
    }
}
