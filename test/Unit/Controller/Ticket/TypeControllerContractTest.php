<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/type.php → TypeController
 *
 * Entrypoint: ticket/type.php
 * Purpose: Display form and handle changing the ticket type
 * Route: /whups/ticket/{id}/type
 */
#[CoversNothing]
class TypeControllerContractTest extends TestCase
{
    public function testDisplaysTypeChangeForm(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/type.php');
    }

    public function testChangesTicketType(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/type.php');
    }

    public function testRequiresEditPermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/type.php');
    }

    public function testRedirectsAfterTypeChange(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/type.php');
    }
}
