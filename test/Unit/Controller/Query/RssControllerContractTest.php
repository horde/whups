<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Query;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for query/rss.php → QueryRssController
 *
 * Entrypoint: query/rss.php
 * Purpose: Generate RSS feed for a saved query's ticket results
 * Route: /whups/query/{slug}/rss
 * @coversNothing
 */
class RssControllerContractTest extends TestCase
{
    public function testReturnsXmlContentType(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/rss.php');
    }

    public function testContainsQueryResults(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/rss.php');
    }

    public function testReturnsEmptyForInvalidQuery(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/rss.php');
    }

    public function testResolvesQueryBySlug(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from query/rss.php');
    }
}
