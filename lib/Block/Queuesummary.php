<?php

/**
 * Show a summary of all available queues and their number of open tickets.
 */
class Whups_Block_Queuesummary extends Horde_Core_Block
{
    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);

        $this->_name = _("Queue Summary");
    }

    /**
     */
    protected function _content()
    {
        $queryService = $GLOBALS['injector']->getInstance(
            \Horde\Whups\Service\TicketQueryService::class,
        );
        $qsummary = $queryService->getQueueSummary();
        if (!$qsummary) {
            return '<p class="horde-content"><em>' . _("There are no open tickets.") . '</em></p>';
        }

        $summary = $types = [];
        foreach ($qsummary as $queue) {
            $types[$queue['type']] = $queue['type'];
            if (!isset($summary[$queue['id']])) {
                $summary[$queue['id']] = $queue;
            }
            $summary[$queue['id']][$queue['type']] = $queue['open_tickets'];
        }

        $html = '<thead><tr>';
        $sortby = 'queue_name';
        foreach (array_merge(['queue_name' => _("Queue")], $types) as $column => $name) {
            $html .= '<th' . ($sortby == $column ? ' class="sortdown"' : '') . '>' . $name . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($summary as $queue) {
            $html .= '<tr><td>' . Whups::urlFor('queue', $queue, true)->link(['title' => $queue['description']]) . htmlspecialchars($queue['name'] ?? '') . '</a></td>';
            foreach ($types as $type) {
                $html .= '<td>' . ($queue[$type] ?? '&nbsp;') . '</td>';
            }
            $html .= '</tr>';
        }

        $GLOBALS['page_output']->addScriptFile('tables.js', 'horde');

        return '<table id="whups_block_queuesummary" class="horde-table sortable" style="width:100%">' . $html . '</tbody></table>';
    }

}
