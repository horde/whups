<?php

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Duck <duck@obala.net>
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('whups');

$vars = Horde_Variables::getDefaultVariables();
$limit = (int) $vars->get('limit');
$form = new Whups_Form_Search($vars);

if ($form->validate($vars, true)) {
    $info = $form->getInfo($vars);
    $tickets = $whups_driver->getTicketsByProperties($info);
    Whups::sortTickets($tickets, 'date_updated', 'desc');
} else {
    throw new Horde_Exception(_("Invalid search"));
}

$count = 0;
$items = [];
foreach (array_keys($tickets) as $i) {
    if ($limit > 0 && $count++ == $limit) {
        break;
    }
    $description = sprintf(
        _("Type: %s; State: %s"),
        $tickets[$i]['type_name'],
        $tickets[$i]['state_name']
    );

    $items[$i]['title'] = htmlspecialchars(sprintf(
        '[%s] %s',
        $tickets[$i]['id'],
        $tickets[$i]['summary']
    ));
    $items[$i]['description'] = htmlspecialchars($description);
    $items[$i]['url'] = Whups::urlFor('ticket', $tickets[$i]['id'], true, -1);
    $items[$i]['pubDate'] = htmlspecialchars(date('r', $tickets[$i]['timestamp']));
}

$view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/rss']);
$view->xsl = Horde_Themes::getFeedXsl();
$view->pubDate = htmlspecialchars(date('r'));
$view->title = _("Search Results");
$view->items = $items;
$view->url = Horde::url('search.php');
$view->rss_url = Horde::selfUrl();
$view->description = _("Search Results");

$browser->downloadHeaders('search.rss', 'text/xml', true);
echo $view->render('items.rss');
