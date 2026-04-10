<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/index.php → ViewController
 *
 * Entrypoint: ticket/index.php
 * Purpose: Display a single ticket with details, comments, history, attachments
 * Route: /whups/ticket/{id}
 */
class ViewControllerContractTest extends TestCase
{
    public function testDisplaysTicketDetails(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/index.php');
    }

    public function testRequiresReadPermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/index.php');
    }

    public function testReturnsHtmlResponse(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/index.php');
    }

    public function testRedirectsForInvalidTicketId(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/index.php');
    }

    public function testIncludesCommentHistory(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/index.php');
    }
}
