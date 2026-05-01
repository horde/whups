<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for reports.php → ReportsController
 *
 * Entrypoint: reports.php
 * Purpose: Generate and display statistical reports about tickets
 * Route: /whups/reports
 */
#[CoversNothing]
class ReportsControllerContractTest extends TestCase
{
    public function testDisplaysReportPage(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from reports.php');
    }

    public function testSupportsMultipleReportTypes(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from reports.php');
    }

    public function testReturnsHtmlResponse(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from reports.php');
    }
}
