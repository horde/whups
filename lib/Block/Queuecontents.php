<?php

/**
 * Show the open tickets in a queue.
 */
class Whups_Block_Queuecontents extends Whups_Block_Tickets
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
        $this->_name = _("Queue Contents");
    }

    /**
     */
    protected function _params()
    {
        $qParams = [];
        $qDefault = null;
        $permissionChecker = $GLOBALS['injector']->getInstance(
            Horde\Whups\Service\PermissionChecker::class,
        );
        $queueRepo = $GLOBALS['injector']->getInstance(
            Horde\Whups\Domain\QueueRepositoryInterface::class,
        );
        $qParams = $permissionChecker->filterQueues(
            $queueRepo->listQueues(),
        );
        if (!$qParams) {
            $qDefault = _("No queues available.");
            $qType = 'error';
        } else {
            $qType = 'enum';
        }

        return array_merge(
            [
                'queue' => [
                    'type' => $qType,
                    'name' => _("Queue"),
                    'default' => $qDefault,
                    'values' => $qParams,
                ]],
            parent::_params()
        );
    }

    /**
     */
    protected function _title()
    {
        if ($queue = $this->_getQueue()) {
            return sprintf(_("Open Tickets in %s"), htmlspecialchars($queue->name ?? ''));
        }

        return $this->getName();
    }

    /**
     */
    protected function _content()
    {
        if (!($queue = $this->_getQueue())) {
            return '<p class="horde-content"><em>' . _("No tickets in queue.") . '</em></p>';
        }

        $queryService = $GLOBALS['injector']->getInstance(
            Horde\Whups\Service\TicketQueryService::class,
        );
        $tickets = $queryService->getQueueTickets((int) $this->_params['queue']);
        if (!$tickets) {
            return '<p class="horde-content"><em>' . _("No tickets in queue.") . '</em></p>';
        }

        return $this->_table($tickets, 'whups_block_queue_' . $this->_params['queue']);
    }

    /**
     */
    private function _getQueue()
    {
        if (empty($this->_params['queue'])) {
            return false;
        }

        $permissionChecker = $GLOBALS['injector']->getInstance(
            Horde\Whups\Service\PermissionChecker::class,
        );
        if (!$permissionChecker->filterQueueIds([$this->_params['queue']])) {
            return false;
        }

        try {
            $queueRepo = $GLOBALS['injector']->getInstance(
                Horde\Whups\Domain\QueueRepositoryInterface::class,
            );
            return $queueRepo->getQueue((int) $this->_params['queue']);
        } catch (Whups_Exception $e) {
            return false;
        }
    }

}
