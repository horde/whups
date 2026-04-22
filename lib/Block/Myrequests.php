<?php

/**
 * Display a summary of the current user's requested tickets.
 */
class Whups_Block_Myrequests extends Whups_Block_Tickets
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
        $this->_name = _("My Requests");
    }

    /**
     */
    protected function _content()
    {
        $queryService = $GLOBALS['injector']->getInstance(
            Horde\Whups\Service\TicketQueryService::class,
        );
        $requests = $queryService->getMyRequests();
        if (!$requests) {
            return '<p class="horde-content"><em>' . _("You have no open requests.") . '</em></p>';
        }

        return $this->_table($requests);
    }

}
