<?php

/**
 * Display a summary of unassigned tickets.
 */
class Whups_Block_Unassigned extends Whups_Block_Tickets
{
    /**
     * Is this block enabled?
     *
     * @var boolean
     */
    public $enabled = true;

    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);
        $this->_name = _("Unassigned Tickets");
    }

    /**
     */
    protected function _content()
    {
        $queryService = $GLOBALS['injector']->getInstance(
            Horde\Whups\Service\TicketQueryService::class,
        );
        $unassigned = $queryService->getUnassignedTickets();
        if (!$unassigned) {
            return '<p class="horde-content"><em>' . _("No tickets are unassigned!") . '</em></p>';
        }

        return $this->_table($unassigned);
    }

}
