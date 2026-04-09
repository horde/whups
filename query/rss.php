<?php

/**
 * Whups RSS feed.
 *
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @author Jan Schneider <jan@horde.org>
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('whups');

$qManager = new Whups_Query_Manager();
$vars = new Horde_Variables();

// See if we were passed a slug or id. Slug is tried first.
$whups_query = null;
$slug = Horde_Util::getFormData('slug');
if ($slug) {
    $whups_query = $qManager->getQueryBySlug($slug);
} else {
    $whups_query = $qManager->getQuery(Horde_Util::getFormData('query'));
}

if (!isset($whups_query) ||
    $whups_query->parameters ||
    !$whups_query->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::READ)) {
    exit;
}

$tickets = $whups_driver->executeQuery($whups_query, $vars);
if (!count($tickets)) {
    exit;
}

Whups::sortTickets($tickets, 'date_updated', 'desc');
$cnt = 0;
foreach (array_keys($tickets) as $i) {
    $description = 'Type: ' . $tickets[$i]['type_name'] . '; State: '
        . $tickets[$i]['state_name'];

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
$view->title = htmlspecialchars($whups_query->name ? $whups_query->name : _("Query Results"));
$view->items = $items;
$url_param = isset($slug)
    ? ['slug' => $slug]
    : ['id' => Horde_Util::getFormData('query')];
$view->url = Whups::urlFor('query', $url_param, true, -1);
$view->rss_url = Whups::urlFor('query_rss', $url_param, true, -1);
$view->description = htmlspecialchars(sprintf(_("Tickets matching the query \"%s\"."), $whups_query->name));

$browser->downloadHeaders(($slug ?? 'query') . '.rss', 'text/xml', true);
echo $view->render('items.rss');
