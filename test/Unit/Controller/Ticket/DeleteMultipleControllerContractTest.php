<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/delete_multiple.php → DeleteMultipleController
 *
 * Entrypoint: ticket/delete_multiple.php
 * Purpose: Handle bulk deletion of multiple tickets
 * Route: /whups/ticket/delete-multiple
 * @coversNothing
 */
class DeleteMultipleControllerContractTest extends TestCase
{
    public function testDisplaysConfirmationWithTicketList(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete_multiple.php');
    }

    public function testDeletesMultipleTickets(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete_multiple.php');
    }

    public function testRequiresDeletePermissionForEachTicket(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete_multiple.php');
    }

    public function testRedirectsAfterBulkDeletion(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete_multiple.php');
    }
}
