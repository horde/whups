<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Query;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for query/run.php → RunController
 *
 * Entrypoint: query/run.php
 * Purpose: Execute and display results of a saved query (by slug or ID)
 * Route: /whups/query/{slug}
 */
class RunControllerContractTest extends TestCase
{
    public function testExecutesQueryBySlug(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/run.php');
    }

    public function testExecutesQueryById(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/run.php');
    }

    public function testReturnsHtmlWithResults(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/run.php');
    }

    public function testRedirectsForInvalidQuery(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/run.php');
    }
}
