<?php

declare(strict_types=1);

/**
 * Injectable topbar search configurator for Whups.
 *
 * Sets up the Horde topbar search widget to submit ticket-ID lookups
 * to the Whups ticket controller.  Replaces the static
 * Whups::addTopbarSearch() and the identical 3-line block that was
 * duplicated across every PSR-15 controller.
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

namespace Horde\Whups\Service;

use Horde\Core\Session\HordeSession;
use Horde_Registry;
use Horde_Url;
use Horde_View_Topbar;

class TopbarSearch
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly HordeSession $session,
        private readonly Horde_View_Topbar $topbar,
    ) {}

    /**
     * Configure the topbar search widget for Whups ticket lookup.
     */
    public function apply(): void
    {
        $webroot = $this->registry->get('webroot', 'whups');
        $this->topbar->search = true;
        $this->topbar->searchAction = new Horde_Url($webroot . '/ticket');
        $this->topbar->searchLabel = $this->session->getScoped('whups', 'search')
            ?: _("Ticket #Id");
    }
}
