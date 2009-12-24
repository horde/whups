<?php

$block_name = _("Queue Summary");

/**
 * Horde_Block_Whups_queuesummary:: Show a summary of all available queues
 * and their number of open tickets.
 *
 * @package Horde_Block
 */
class Horde_Block_Whups_queuesummary extends Horde_Block {

    var $_app = 'whups';

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        return _("Queue Summary");
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        require_once dirname(__FILE__) . '/../base.php';
        global $whups_driver;

        $queues = Whups::permissionsFilter($whups_driver->getQueues(), 'queue', Horde_Perms::READ);
        $qsummary = $whups_driver->getQueueSummary(array_keys($queues));
        if (is_a($qsummary, 'PEAR_Error')) {
            return $qsummary;
        }

        if (!$qsummary) {
            return '<p><em>' . _("There are no open tickets.") . '</em></p>';
        }

        $html = '<thead><tr>';
        $sortby = 'queue_name';
        foreach (array('queue_name' => _("Queue"), 'open_tickets' => _("Open Tickets")) as $column => $name) {
            $html .= '<th' . ($sortby == $column ? ' class="sortdown"' : '') . '>' . $name . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($qsummary as $queue) {
            $html .= '<tr><td>' . Horde::link(Whups::urlFor('queue', $queue, true), $queue['description']) . htmlspecialchars($queue['name']) . '</a></td>' .
                '<td>' . htmlspecialchars($queue['open_tickets']) . '</td></tr>';
        }

        Horde::addScriptFile('tables.js', 'horde', true);
        return '<table id="whups_block_queuesummary" cellspacing="0" class="tickets striped sortable">' . $html . '</tbody></table>';
    }

}
