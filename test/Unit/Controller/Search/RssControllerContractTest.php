<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Search;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for search/rss.php → SearchRssController
 *
 * Entrypoint: search/rss.php
 * Purpose: Generate RSS feed for search results
 * Route: /whups/search/rss
 * @coversNothing
 */
class RssControllerContractTest extends TestCase
{
    public function testReturnsXmlContentType(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from search/rss.php');
    }

    public function testContainsSearchResults(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from search/rss.php');
    }

    public function testHandlesEmptyResults(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from search/rss.php');
    }
}
