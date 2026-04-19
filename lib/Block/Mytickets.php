<?php

/**
 * Display a summary of the current user's assigned tickets.
 */
class Whups_Block_Mytickets extends Whups_Block_Tickets
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
        $this->_name = _("My Tickets");
    }

    /**
     */
    protected function _content()
    {
        $queryService = $GLOBALS['injector']->getInstance(
            \Horde\Whups\Service\TicketQueryService::class,
        );
        $assigned = $queryService->getMyTickets();
        if (!$assigned) {
            return '<p class="horde-content"><em>' . _("No tickets are assigned to you.") . '</em></p>';
        }

        return $this->_table($assigned);
    }

}
