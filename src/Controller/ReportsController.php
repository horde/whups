<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Display ticket statistics reports.
 *
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller;

use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\TopbarSearch;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Perms;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups;
use Whups_Driver;
use Whups_Reports;

class ReportsController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly TopbarSearch $topbarSearch,
        private readonly PermissionChecker $permissions,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $title = _("Reports");

        $stats = [
            'avg|open' => _("Average time a ticket is unresolved"),
            'max|open' => _("Maximum time a ticket is unresolved"),
            'min|open' => _("Minimum time a ticket is unresolved"),
        ];

        $queues = Whups::permissionsFilter(
            $this->driver->getQueues(),
            'queue',
            Horde_Perms::READ,
        );

        $reporter = new Whups_Reports($this->driver);
        $reportCache = $this->driver->getReportCache();
        if ($reportCache !== null) {
            $reporter->setCache($reportCache);
        }

        $html = $this->renderChrome($title, function () use ($queues, $stats, $reporter) {
            $this->topbarSearch->apply();

            require WHUPS_TEMPLATES . '/reports/stats.inc';
        });

        return $this->htmlResponse($html);
    }
}
