<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/queue.php → QueueChangeController
 *
 * Entrypoint: ticket/queue.php
 * Purpose: Display form and handle moving a ticket to a different queue
 * Route: /whups/ticket/{id}/queue
 */
class QueueChangeControllerContractTest extends TestCase
{
    public function testDisplaysQueueChangeForm(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/queue.php');
    }

    public function testMovesTicketToNewQueue(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/queue.php');
    }

    public function testRequiresEditPermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/queue.php');
    }

    public function testRedirectsAfterQueueChange(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/queue.php');
    }
}
