<?php
/**
 * Whups application API.
 *
 * This file defines Horde's core API interface. Other core Horde libraries
 * can interact with Whups through this API.
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @package Whups
 */

/* Determine the base directories. */
if (!defined('WHUPS_BASE')) {
    define('WHUPS_BASE', realpath(__DIR__ . '/..'));
}

if (!defined('HORDE_BASE')) {
    /* If Horde does not live directly under the app directory, the HORDE_BASE
     * constant should be defined in config/horde.local.php. */
    if (file_exists(WHUPS_BASE . '/config/horde.local.php')) {
        include WHUPS_BASE . '/config/horde.local.php';
    } else {
        define('HORDE_BASE', realpath(WHUPS_BASE . '/..'));
    }
}

/* Load the Horde Framework core (needed to autoload
 * Horde_Registry_Application::). */
require_once HORDE_BASE . '/lib/core.php';

class Whups_Application extends Horde_Registry_Application
{
    /**
     */
    public $version = 'H6 (4.0.0alpha6)';

    /**
     * Global variables defined:
     * - $whups_driver: The global Whups driver object.
     */
    protected function _init()
    {
        $GLOBALS['whups_driver'] = $GLOBALS['injector']
            ->getInstance('Whups_Factory_Driver')
            ->create();

        /* Set the timezone variable, if available. */
        $GLOBALS['registry']->setTimeZone();
    }

    /**
     */
    public function perms()
    {
        /* Available Whups permissions. */
        $perms = array(
            'admin' => array(
                'title' => _("Administration")
            ),
            'hiddenComments' => array(
                'title' => _("Hidden Comments")
            ),
            'queues' => array(
                'title' => _("Queues")
            ),
            'replies' => array(
                'title' => _("Form Replies")
            )
        );

        /* Loop through queues and add their titles. */
        $queues = $GLOBALS['whups_driver']->getQueues();
        foreach ($queues as $id => $name) {
            $perms['queues:' . $id] = array(
                'title' => $name
            );

            $entries = array(
                'assign' => _("Assign"),
                'requester' => _("Set Requester"),
                'update' => _("Update")
            );

            foreach ($entries as $key => $val) {
                $perms['queues:' . $id . ':' . $key] = array(
                    'title' => $val,
                    'type' => 'boolean'
                );
            }
        }

        /* Loop through type and replies and add their titles. */
        foreach ($GLOBALS['whups_driver']->getAllTypes() as $type_id => $type_name) {
            foreach ($GLOBALS['whups_driver']->getReplies($type_id) as $reply_id => $reply) {
                $perms['replies:' . $reply_id] = array(
                    'title' => $type_name . ': ' . $reply['reply_name']
                );
            }
        }

        return $perms;
    }

    public function sidebar($sidebar)
    {
        global $registry, $session;

        $sidebar->addNewButton(
            _("_New Ticket"),
            Horde::url('ticket/create.php'));
        $sidebar->containers['queries'] = array(
            'header' => array(
                'id' => 'whups-toggle-queries',
                'label' => _("Saved Queries"),
            ),
        );
        $manager = new Whups_Query_Manager();
        $queries = $manager->listQueries($registry->getAuth(), true);
        foreach ($queries as $id => $query) {
            $row = array(
                'selected' => strpos(strval(Horde::selfUrl()), $registry->get('webroot') . '/query') === 0 &&
                    $id == $session->get('whups', 'query'),
                'cssClass' => 'whups-sidebar-query',
                'url' => Whups::urlFor('query', empty($query['slug']) ? array('id' => $id) : array('slug' => $query['slug'])),
                'label' => $query['name'],
            );
            $sidebar->addRow($row, 'queries');
        }
    }

    /**
     */
    public function menu($menu)
    {
        $menu->add(Horde::url('mybugs.php'), sprintf(_("_My %s"), $GLOBALS['registry']->get('name')), 'whups-mywhups', null, null, null, $GLOBALS['prefs']->getValue('whups_default_view') == 'mybugs' && strpos($_SERVER['PHP_SELF'], $GLOBALS['registry']->get('webroot') . '/index.php') !== false ? 'current' : null);
        $menu->add(Horde::url('search.php'), _("_Search"), 'horde-search', null, null, null, $GLOBALS['prefs']->getValue('whups_default_view') == 'search' && strpos($_SERVER['PHP_SELF'], $GLOBALS['registry']->get('webroot') . '/index.php') !== false ? 'current' : null);
        $menu->add(Horde::url('query/index.php'), _("_Query Builder"), 'whups-query');
        $menu->add(Horde::url('reports.php'), _("_Reports"), 'whups-reports');

        /* Administration. */
        if ($GLOBALS['registry']->isAdmin(array('permission' => 'whups:admin'))) {
            $menu->add(Horde::url('admin/'), _("_Admin"), 'whups-admin');
        }
    }

    /* Topbar method. */

    /**
     */
    public function topbarCreate(Horde_Tree_Renderer_Base $tree, $parent = null,
                                 array $params = array())
    {
        $tree->addNode(array(
            'id' => $parent . '__new',
            'parent' => $parent,
            'label' => _("New Ticket"),
            'expanded' => false,
            'params' => array(
                'url' => Horde::url('ticket/create.php')
            )
        ));

        $tree->addNode(array(
            'id' => $parent . '__search',
            'parent' => $parent,
            'label' => _("Search"),
            'expanded' => false,
            'params' => array(
                'url' => Horde::url('search.php')
            )
        ));
    }

    /* Download data. */

    /**
     * @throws Whups_Exception
     */
    public function download(Horde_Variables $vars)
    {
        switch ($vars->actionID) {
        case 'download_file':
        case 'download_message':
            return $this->_downloadAttachment($vars);
        case 'ticket':
            return $this->_downloadTicket($vars);
        case 'report':
            return $this->_downloadReport($vars);
        }
    }

    /**
     * Provides download data for an attachment or original message.
     *
     * @param Horde_Variables $vars  Submitted form/URL data.
     *
     * @throws Whups_Exception
     */
    protected function _downloadAttachment(Horde_Variables $vars)
    {
        global $injector, $whups_driver;

        // Get the ticket details first.
        if (empty($vars->ticket)) {
            throw new Whups_Exception(_("No ticket ID"));
        }

        // Check permissions on this ticket.
        $tickets = Whups::permissionsFilter(
            $whups_driver->getHistory($vars->ticket),
            'comment',
            Horde_Perms::READ
        );
        if (!count($tickets)) {
            throw new Whups_Exception(
                sprintf(
                    _("You are not allowed to view ticket %d."),
                    $vars->ticket
                )
            );
        }

        try {
            $vfs = $injector->getInstance('Horde_Core_Factory_Vfs')
                ->create();
        } catch (Horde_Exception $e) {
            throw new Whups_Exception(_("The VFS backend needs to be configured to enable attachment uploads."));
        }

        try {
            if ($vars->actionID == 'download_message') {
                return array(
                    'data' => $vfs->read(
                        Whups::VFS_MESSAGE_PATH . '/' . $vars->ticket,
                        $vars->message
                    ),
                    'name' => _("Original message") . '.eml'
                );
            }
            return array(
                'data' => $vfs->read(
                    Whups::VFS_ATTACH_PATH . '/' . $vars->ticket,
                    $vars->file
                ),
                'name' => $vars->file
            );
        } catch (Horde_Vfs_Exception $e) {
            throw new Whups_Exception(
                sprintf(_("Access denied to %s"), $vars->file)
            );
        }
    }

    /**
     * Provides download data for an HTML version of an ticket.
     *
     * @param Horde_Variables $vars  Submitted form/URL data.
     *
     * @throws Whups_Exception
     */
    protected function _downloadTicket(Horde_Variables $vars)
    {
        global $conf, $injector, $page_output, $prefs, $whups_driver;

        $ticket = Whups::getCurrentTicket();
        $ticket->setDetails($vars);
        $title = '[#' . $ticket->getId() . '] ' . $ticket->get('summary');
        $filter = $injector->getInstance('Horde_Core_Factory_TextFilter');
        $obfuscate = $conf['prefs']['obfuscate_email'];
        $conf['prefs']['obfuscate_email'] = false;

        Horde::startBuffer();

        $page_output->addMetaTag('Content-Type', 'text/html; charset=UTF-8');
        $page_output->header(array(
            'title' => $title,
            'view' => Horde_Registry::VIEW_MINIMAL
        ));

        $html = Horde::endBuffer();

        $html = substr($html, 0, strrpos($html, '</head>')) . <<<STYLE
   <style type="text/css">
table, th, td {
    border: 1px solid black;
}
table {
    border-collapse: collapse;
}
td, th {
    padding: 2px;
}
div.header {
    font-weight: bold;
    font-size: 140%;
    padding: 3px 0;
}
.nowrap {
    white-space: nowrap;
}
.comment {
    font-family: Menlo,Consolas,"Lucida Console","DejaVu Sans Mono",monospace;
    padding: 5px;
}
    </style>
 </head>
<body>

STYLE;

        Horde::startBuffer();

        $form = new Whups_Form_TicketDetails($vars, $ticket);
        $renderer = $form->getRenderer();
        $renderer->_name = $form->getName();
        $renderer->beginInactive($title);
        $renderer->renderFormInactive($form, $vars);
        $renderer->end();
        echo "<br />\n";

        echo '<div class="header">' . _("Comments") . "</div>\n";
        $history = Whups::permissionsFilter(
            $whups_driver->getHistory($ticket->getId(), $form),
            'comment',
            Horde_Perms::READ);
        foreach ($history as $transaction) {
            if (empty($transaction['changes'])) {
                continue;
            }
            foreach ($transaction['changes'] as $change) {
                if ($change['type'] != 'comment' ||
                    !empty($change['private'])) {
                    continue;
                }
                $comment = $change['comment'];
                $flowed = new Horde_Text_Flowed($comment, 'UTF-8');
                $flowed->setDelSp(true);
                $comment = $flowed->toFlowed(false);
                $comment = $filter->filter(
                    $comment,
                    array('text2html', 'simplemarkup'),
                    array(
                        array('parselevel' => Horde_Text_Filter_Text2html::MICRO),
                        array('html' => true),
                    )
                );
                $user = empty($transaction['user_id'])
                    ? '&nbsp;'
                    : htmlspecialchars(
                        Whups::formatUser($transaction['user_id'])
                    );
                $time = strftime(
                    $prefs->getValue('date_format') . ' '
                        . $prefs->getValue('time_format'),
                    $transaction['timestamp']
                );
                echo <<<COMMENT
<table width="100%">
 <tr>
  <td class="nowrap" valign="top"><em>$user</em></td>
  <td class="nowrap" valign="top" align="right"><em>$time</em></td>
 </tr>
 <tr><td colspan="2">
  <div class="comment">
   $comment
  </div>
 </td></tr>
</table>
<br />

COMMENT;
            }
        }

        $page_output->footer();

        $html .= Horde::endBuffer();

        $conf['prefs']['obfuscate_email'] = $obfuscate;

        return array(
            'data' => $html,
            'name' => _("ticket") . $vars->id . '.html'
        );
    }

    /**
     * Provides download data for a report.
     *
     * @param Horde_Variables $vars  Submitted form/URL data.
     *
     * @throws Whups_Exception
     */
    protected function _downloadReport(Horde_Variables $vars)
    {
        global $injector, $whups_driver;

        $_templates = Horde::loadConfiguration(
            'templates.php', '_templates', 'whups'
        );
        $tpl = $vars->template;
        if (empty($_templates[$tpl])) {
            throw new Whups_Exception(
                _("The requested template does not exist.")
            );
        }
        if ($_templates[$tpl]['type'] != 'searchresults') {
            throw new Whups_Exception(
                _("This is not a search results template.")
            );
        }

        // Fetch all unresolved tickets assigned to the current user.
        $info = array('id' => explode(',', $vars->ids));
        $tickets = array();
        foreach ($whups_driver->getTicketsByProperties($info) as $id => $info) {
            if (!Whups::hasPermission($info['queue'], 'queue', Horde_Perms::READ)) {
                continue;
            }
            $tickets[$id] = $info;
            $tickets[$id]['#'] = $id + 1;
            $tickets[$id]['link'] = Whups::urlFor(
                'ticket', $info['id'], true, -1
            );
            $tickets[$id]['date_created'] = strftime('%x', $info['timestamp']);
            $tickets[$id]['owners'] = Whups::getOwners($info['id']);
            $tickets[$id]['owner_name'] = Whups::getOwners(
                $info['id'], false, true
            );
            $tickets[$id]['owner_email'] = Whups::getOwners(
                $info['id'], true, false
            );
            if (!empty($info['date_assigned'])) {
                $tickets[$id]['date_assigned'] = strftime(
                    '%x', $info['date_assigned']
                );
            }
            if (!empty($info['date_resolved'])) {
                $tickets[$id]['date_resolved'] = strftime(
                    '%x', $info['date_resolved']
                );
            }

            // If the template has a callback function defined for data
            // filtering, call it now.
            if (!empty($_templates[$tpl]['callback'])) {
                array_walk($tickets[$id], $_templates[$tpl]['callback']);
            }
        }

        Whups::sortTickets(
            $tickets,
            isset($_templates[$tpl]['sortby'])
                ? $_templates[$tpl]['sortby']
                : null,
            isset($_templates[$tpl]['sortdir'])
                ? $_templates[$tpl]['sortdir']
                : null
        );

        $template = $injector->createInstance('Horde_Template');
        $template->set('tickets', $tickets);
        $template->set('now', strftime('%x'));
        $template->set('values', Whups::getSearchResultColumns(null, true));

        return array(
            'data' => $template->parse($_templates[$tpl]['template']),
            'name' => isset($_templates[$tpl]['filename'])
                ? $_templates[$tpl]['filename']
                : 'report.html'
        );
    }
}
