<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/watch.php → WatchController
 *
 * Entrypoint: ticket/watch.php
 * Purpose: Subscribe/unsubscribe to ticket update notifications
 * Route: /whups/ticket/{id}/watch
 * @coversNothing
 */
class WatchControllerContractTest extends TestCase
{
    public function testAddWatchRedirects(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/watch.php');
    }

    public function testRemoveWatchRedirects(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/watch.php');
    }

    public function testRequiresAuthentication(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/watch.php');
    }
}
