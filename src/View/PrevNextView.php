<?php

declare(strict_types=1);

/**
 * Prev/next navigation view for ticket browsing.
 *
 * Replaces the templates/prevnext.inc global-heavy template with a
 * proper Horde_View subclass that receives all data via constructor.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\View;

use Horde;
use Horde\Whups\Service\UrlGenerator;
use Horde_Url;

class PrevNextView
{
    /** @var int|false Index of current ticket in list, or false if not found. */
    private int|false $currentIndex;

    /**
     * @param int      $ticketId      The current ticket ID.
     * @param int[]    $ticketList    Ordered list of ticket IDs from session.
     * @param string   $lastSearchUrl The URL of the last search result page.
     * @param UrlGenerator $urlGenerator  For generating ticket URLs.
     */
    public function __construct(
        private readonly int $ticketId,
        private readonly array $ticketList,
        private readonly string $lastSearchUrl,
        private readonly UrlGenerator $urlGenerator,
    ) {
        $this->currentIndex = array_search($ticketId, $ticketList);
    }

    /**
     * Render the prev/next navigation bar.
     *
     * Returns empty string if the ticket list has one or fewer entries
     * or the current ticket is not in the list.
     */
    public function render(): string
    {
        $count = count($this->ticketList);

        if ($count <= 1 || $this->currentIndex === false) {
            return '';
        }

        $idx = $this->currentIndex;
        $links = [];

        if ($idx > 0) {
            $links[] = $this->ticketLink($this->ticketList[0])
                . htmlspecialchars(_("<<First")) . '</a>';
            $links[] = $this->ticketLink($this->ticketList[$idx - 1])
                . htmlspecialchars(_("<Prev")) . '</a>';
        }

        if ($idx + 1 < $count) {
            $links[] = $this->ticketLink($this->ticketList[$idx + 1])
                . htmlspecialchars(_("Next>")) . '</a>';
            $links[] = $this->ticketLink($this->ticketList[$count - 1])
                . htmlspecialchars(_("Last>>")) . '</a>';
        }

        $label = _("Re_turn to Search Results");
        $ak = Horde::getAccessKey($label);
        $label = Horde::highlightAccessKey($label, $ak);

        $position = sprintf(
            _("Search Results: %s of %s"),
            $idx + 1,
            $count,
        );

        $html = '<div id="searchnav"><p>'
            . '<strong>' . $position . '</strong> '
            . '<small>[ ' . implode(' ', $links) . ' ]';

        if ($this->lastSearchUrl !== '') {
            $searchLink = (new Horde_Url($this->lastSearchUrl))
                ->add('haveSearch', true)
                ->link(['accesskey' => $ak]);
            $html .= ' [ ' . $searchLink . $label . '</a> ]';
        }

        $html .= '</small></p></div>';

        return $html;
    }

    /**
     * Generate an opening <a> tag linking to a ticket.
     */
    private function ticketLink(int $ticketId): string
    {
        $url = $this->urlGenerator->urlFor('TicketView', ['id' => $ticketId]);

        return '<a href="' . htmlspecialchars($url) . '">';
    }
}
