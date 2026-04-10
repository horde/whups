<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Ticket;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ticket/comment.php → CommentController
 *
 * Entrypoint: ticket/comment.php
 * Purpose: Display form and handle adding comments/transactions to a ticket
 * Route: /whups/ticket/{id}/comment
 */
class CommentControllerContractTest extends TestCase
{
    public function testDisplaysCommentForm(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/comment.php');
    }

    public function testSubmitsCommentSuccessfully(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/comment.php');
    }

    public function testRequiresUpdatePermission(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/comment.php');
    }

    public function testRedirectsAfterSuccessfulComment(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from ticket/comment.php');
    }
}
