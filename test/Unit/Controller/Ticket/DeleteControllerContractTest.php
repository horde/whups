<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/delete.php → DeleteController
 *
 * Entrypoint: ticket/delete.php
 * Purpose: Display confirmation form and handle permanent deletion of a ticket
 * Route: /whups/ticket/{id}/delete
 */
#[CoversNothing]
class DeleteControllerContractTest extends TestCase
{
    public function testDisplaysConfirmationForm(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete.php');
    }

    public function testDeletesTicketOnConfirmation(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete.php');
    }

    public function testRequiresDeletePermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete.php');
    }

    public function testRedirectsAfterDeletion(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/delete.php');
    }
}
