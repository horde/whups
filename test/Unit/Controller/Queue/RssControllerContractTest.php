<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Controller\Queue;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for queue/rss.php → QueueRssController
 *
 * Entrypoint: queue/rss.php
 * Purpose: Generate RSS feed for a specific queue's open tickets
 * Route: /whups/queue/{slug}/rss
 */
class RssControllerContractTest extends TestCase
{
    public function testReturnsXmlContentType(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from queue/rss.php');
    }

    public function testContainsTicketItems(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from queue/rss.php');
    }

    public function testReturns404ForInvalidQueue(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from queue/rss.php');
    }

    public function testResolvesQueueBySlug(): void
    {
        $this->markTestIncomplete('Awaiting controller conversion from queue/rss.php');
    }
}
