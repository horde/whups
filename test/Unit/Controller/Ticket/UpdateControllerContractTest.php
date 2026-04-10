<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/update.php → UpdateController
 *
 * Entrypoint: ticket/update.php
 * Purpose: Display form and handle updating ticket properties
 * Route: /whups/ticket/{id}/update
 */
class UpdateControllerContractTest extends TestCase
{
    public function testDisplaysUpdateForm(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/update.php');
    }

    public function testSubmitsUpdateSuccessfully(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/update.php');
    }

    public function testRequiresEditPermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/update.php');
    }

    public function testRedirectsAfterSuccessfulUpdate(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/update.php');
    }

    public function testPreservesFormDataOnValidationError(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/update.php');
    }
}
