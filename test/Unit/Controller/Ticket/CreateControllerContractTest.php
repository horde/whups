<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/create.php → CreateController
 *
 * Entrypoint: ticket/create.php
 * Purpose: Multi-step form for creating new tickets (select queue/type, fill details)
 * Route: /whups/ticket/create
 */
class CreateControllerContractTest extends TestCase
{
    public function testDisplaysQueueTypeSelectionForm(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/create.php');
    }

    public function testDisplaysDetailFormAfterQueueSelection(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/create.php');
    }

    public function testCreatesTicketSuccessfully(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/create.php');
    }

    public function testRedirectsToNewTicketAfterCreation(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/create.php');
    }

    public function testRequiresCreatePermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/create.php');
    }
}
