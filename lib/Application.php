<?php

/**
 * Whups application API.
 *
 * This file defines Horde's core API interface. Other core Horde libraries
 * can interact with Whups through this API.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
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

use Horde\Cache\Cache as HordeCache;
use Horde\Cache\FileStorage;
use Horde\Date\Format as DateFormat;
use Horde\Util\Variables;
use Horde\Whups\Service\PermissionChecker;
use Horde\Whups\Service\UrlGenerator;
use Horde\Whups\Service\UserFormatter;

/* Load the Horde Framework core (needed to autoload
 * Horde_Registry_Application::). */
require_once HORDE_BASE . '/lib/core.php';

class Whups_Application extends Horde_Registry_Application
{
    /**
     */
    public $version = '4.0.0-beta5';

    /**
     * Register PSR-4 services in the injector.
     *
     * Called once during app registration — before _init(), before any
     * request processing.  Closures are lazy: the service is only
     * constructed when first requested from the injector.
     */
    protected function _bootstrap()
    {
        $injector = $GLOBALS['injector'];

        $injector->bindClosure(
            PermissionChecker::class,
            function ($injector) {
                return new PermissionChecker(
                    $injector->getInstance('Horde_Perms'),
                    $injector->getInstance('Horde_Registry'),
                );
            },
        );

        // Alias Horde_Perms_Base to the Horde_Perms injector binding so that
        // controllers type-hinting the abstract base class get the configured
        // Horde_Perms_Sql (or other) driver instance.
        $injector->bindClosure(
            'Horde_Perms_Base',
            function ($injector) {
                return $injector->getInstance('Horde_Perms');
            },
        );

        // Alias Horde_Group_Base to the Horde_Group injector binding so that
        // services type-hinting the abstract base class get the configured
        // Horde_Group_Sql (or other) driver instance.
        $injector->bindClosure(
            'Horde_Group_Base',
            function ($injector) {
                return $injector->getInstance('Horde_Group');
            },
        );

        $injector->bindClosure(
            UrlGenerator::class,
            function ($injector) {
                $mapper = new \Horde\Routes\Mapper();
                require WHUPS_BASE . '/config/routes.php';
                if (file_exists(WHUPS_BASE . '/config/routes.local.php')) {
                    include WHUPS_BASE . '/config/routes.local.php';
                }
                $registry = $injector->getInstance('Horde_Registry');
                $webroot = $registry->get('webroot', 'whups');

                return new UrlGenerator($mapper, $webroot);
            },
        );

        $injector->bindClosure(
            UserFormatter::class,
            function ($injector) {
                $conf = $GLOBALS['conf'] ?? [];

                return new UserFormatter(
                    $injector->getInstance('Horde_Core_Factory_Identity'),
                    $injector->getInstance('Horde_Group'),
                    $injector->getInstance('Horde_Registry'),
                    $injector->getInstance('Whups_Driver'),
                    !empty($conf['prefs']['obfuscate_email']),
                );
            },
        );
    }

    /**
     * Global variables defined:
     * - $whups_driver: The global Whups driver object.
     */
    protected function _init()
    {
        $GLOBALS['whups_driver'] = $GLOBALS['injector']
            ->getInstance('Whups_Factory_Driver')
            ->create();
        $GLOBALS['injector']->setInstance('Whups_Driver', $GLOBALS['whups_driver']);

        /* Inject metadata cache into driver if configured. */
        $metadataLifetime = (int) ($GLOBALS['conf']['cache']['metadata_lifetime'] ?? 0);
        if ($metadataLifetime > 0) {
            $cacheDir = $GLOBALS['conf']['cache']['params']['dir'] ?? '';
            $cache = new HordeCache(
                new FileStorage(dir: $cacheDir),
                ['namespace' => 'whups_meta', 'lifetime' => $metadataLifetime],
            );
            $GLOBALS['whups_driver']->setCache($cache);
        }

        /* Inject report cache into driver if configured. */
        $reportLifetime = (int) ($GLOBALS['conf']['cache']['report_lifetime'] ?? 0);
        if ($reportLifetime > 0) {
            $cacheDir ??= ($GLOBALS['conf']['cache']['params']['dir'] ?? '');
            $reportCache = new HordeCache(
                new FileStorage(dir: $cacheDir),
                ['namespace' => 'whups_reports', 'lifetime' => $reportLifetime],
            );
            $GLOBALS['whups_driver']->setReportCache($reportCache);
        }

        /* Set the timezone variable, if available. */
        $GLOBALS['registry']->setTimeZone();
    }

    /**
     */
    public function perms()
    {
        /* Available Whups permissions. */
        $perms = [
            'admin' => [
                'title' => _("Administration"),
            ],
            'hiddenComments' => [
                'title' => _("Hidden Comments"),
            ],
            'queues' => [
                'title' => _("Queues"),
            ],
            'replies' => [
                'title' => _("Form Replies"),
            ],
        ];

        /* Loop through queues and add their titles. */
        $queues = $GLOBALS['whups_driver']->getQueues();
        foreach ($queues as $id => $name) {
            $perms['queues:' . $id] = [
                'title' => $name,
            ];

            $entries = [
                'assign' => _("Assign"),
                'requester' => _("Set Requester"),
                'update' => _("Update"),
            ];

            foreach ($entries as $key => $val) {
                $perms['queues:' . $id . ':' . $key] = [
                    'title' => $val,
                    'type' => 'boolean',
                ];
            }
        }

        /* Loop through type and replies and add their titles. */
        foreach ($GLOBALS['whups_driver']->getAllTypes() as $type_id => $type_name) {
            foreach ($GLOBALS['whups_driver']->getReplies($type_id) as $reply_id => $reply) {
                $perms['replies:' . $reply_id] = [
                    'title' => $type_name . ': ' . $reply['reply_name'],
                ];
            }
        }

        return $perms;
    }

    public function sidebar($sidebar)
    {
        global $registry, $session;

        $webroot = $registry->get('webroot', 'whups');

        $sidebar->addNewButton(
            _("_New Ticket"),
            new Horde_Url($webroot . '/ticket/create')
        );
        $sidebar->containers['queries'] = [
            'header' => [
                'id' => 'whups-toggle-queries',
                'label' => _("Saved Queries"),
            ],
        ];
        $manager = new Whups_Query_Manager();
        $queries = $manager->listQueries($registry->getAuth(), true);
        $currentQuery = $session->get('whups', 'query');
        // Extract ID if stored as object
        if ($currentQuery instanceof Whups_Query) {
            $currentQuery = $currentQuery->id;
        }
        foreach ($queries as $id => $query) {
            $row = [
                'selected' => strpos($_SERVER['REQUEST_URI'] ?? '', $webroot . '/query') === 0
                    && $id == $currentQuery,
                'cssClass' => 'whups-sidebar-query',
                'url' => Whups::urlFor('query', empty($query['slug']) ? ['id' => $id] : ['slug' => $query['slug']]),
                'label' => $query['name'],
            ];
            $sidebar->addRow($row, 'queries');
        }
    }

    /**
     */
    public function menu($menu)
    {
        $webroot = $GLOBALS['registry']->get('webroot', 'whups');
        $menu->add(new Horde_Url($webroot . '/mybugs'), sprintf(_("_My %s"), $GLOBALS['registry']->get('name')), 'whups-mywhups', null, null, null, $GLOBALS['prefs']->getValue('whups_default_view') == 'mybugs' && strpos($_SERVER['PHP_SELF'], $webroot . '/index.php') !== false ? 'current' : null);
        $menu->add(new Horde_Url($webroot . '/search'), _("_Search"), 'horde-search', null, null, null, $GLOBALS['prefs']->getValue('whups_default_view') == 'search' && strpos($_SERVER['PHP_SELF'], $webroot . '/index.php') !== false ? 'current' : null);
        $menu->add(new Horde_Url($webroot . '/query/builder'), _("_Query Builder"), 'whups-query');
        $menu->add(new Horde_Url($webroot . '/reports'), _("_Reports"), 'whups-reports');

        /* Administration. */
        if ($GLOBALS['registry']->isAdmin(['permission' => 'whups:admin'])) {
            $menu->add(new Horde_Url($webroot . '/admin'), _("_Admin"), 'whups-admin');
        }
    }

    /* Topbar method. */

    /**
     */
    public function topbarCreate(
        Horde_Tree_Renderer_Base $tree,
        $parent = null,
        array $params = []
    ) {
        $webroot = $GLOBALS['registry']->get('webroot', 'whups');
        $tree->addNode([
            'id' => $parent . '__new',
            'parent' => $parent,
            'label' => _("New Ticket"),
            'expanded' => false,
            'params' => [
                'url' => new Horde_Url($webroot . '/ticket/create'),
            ],
        ]);

        $tree->addNode([
            'id' => $parent . '__search',
            'parent' => $parent,
            'label' => _("Search"),
            'expanded' => false,
            'params' => [
                'url' => new Horde_Url($webroot . '/search'),
            ],
        ]);
    }

    /* Download data. */

    /**
     * @throws Whups_Exception
     */
    public function download(Variables|Horde_Variables $vars)
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
    protected function _downloadAttachment(Variables|Horde_Variables $vars)
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
                return [
                    'data' => $vfs->read(
                        Whups::VFS_MESSAGE_PATH . '/' . $vars->ticket,
                        $vars->message
                    ),
                    'name' => _("Original message") . '.eml',
                ];
            }
            return [
                'data' => $vfs->read(
                    Whups::VFS_ATTACH_PATH . '/' . $vars->ticket,
                    $vars->file
                ),
                'name' => $vars->file,
            ];
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
    protected function _downloadTicket(Variables|Horde_Variables $vars)
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
        $page_output->header([
            'title' => $title,
            'view' => Horde_Registry::VIEW_MINIMAL,
        ]);

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
            Horde_Perms::READ
        );
        foreach ($history as $transaction) {
            if (empty($transaction['changes'])) {
                continue;
            }
            foreach ($transaction['changes'] as $change) {
                if ($change['type'] != 'comment'
                    || !empty($change['private'])) {
                    continue;
                }
                $comment = $change['comment'];
                $flowed = new Horde_Text_Flowed($comment, 'UTF-8');
                $flowed->setDelSp(true);
                $comment = $flowed->toFlowed(false);
                $comment = $filter->filter(
                    $comment,
                    ['text2html', 'simplemarkup'],
                    [
                        ['parselevel' => Horde_Text_Filter_Text2html::MICRO],
                        ['html' => true],
                    ]
                );
                $user = empty($transaction['user_id'])
                    ? '&nbsp;'
                    : htmlspecialchars(
                        Whups::formatUser($transaction['user_id'])
                    );
                $time = DateFormat::formatDate(
                    $transaction['timestamp'],
                    $prefs->getValue('date_format') . ' '
                        . $prefs->getValue('time_format')
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

        return [
            'data' => $html,
            'name' => _("ticket") . $vars->id . '.html',
        ];
    }

    /**
     * Provides download data for a report.
     *
     * @param Horde_Variables $vars  Submitted form/URL data.
     *
     * @throws Whups_Exception
     */
    protected function _downloadReport(Variables|Horde_Variables $vars)
    {
        global $registry, $whups_driver;

        $_templates = $registry->loadConfigFile(
            'templates.php',
            '_templates',
            'whups'
        )->config['_templates'];
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
        $info = ['id' => explode(',', $vars->ids)];
        $tickets = [];
        foreach ($whups_driver->getTicketsByProperties($info) as $id => $info) {
            if (!Whups::hasPermission($info['queue'], 'queue', Horde_Perms::READ)) {
                continue;
            }
            $tickets[$id] = $info;
            $tickets[$id]['#'] = $id + 1;
            $tickets[$id]['link'] = Whups::urlFor(
                'ticket',
                $info['id'],
                true,
                -1
            );
            $tickets[$id]['date_created'] = DateFormat::formatDate($info['timestamp'], '%x');
            $tickets[$id]['owners'] = Whups::getOwners($info['id']);
            $tickets[$id]['owner_name'] = Whups::getOwners(
                $info['id'],
                false,
                true
            );
            $tickets[$id]['owner_email'] = Whups::getOwners(
                $info['id'],
                true,
                false
            );
            if (!empty($info['date_assigned'])) {
                $tickets[$id]['date_assigned'] = DateFormat::formatDate(
                    $info['date_assigned'],
                    '%x'
                );
            }
            if (!empty($info['date_resolved'])) {
                $tickets[$id]['date_resolved'] = DateFormat::formatDate(
                    $info['date_resolved'],
                    '%x'
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
            $_templates[$tpl]['sortby']
                ?? null,
            $_templates[$tpl]['sortdir']
                ?? null
        );

        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/reports']);
        $view->tickets = $tickets;
        $view->now = DateFormat::formatDate(time(), '%x');
        $view->values = Whups::getSearchResultColumns(null, true);

        return [
            'data' => $view->render($_templates[$tpl]['view_template']),
            'name' => $_templates[$tpl]['filename']
                ?? 'report.html',
        ];
    }
}
