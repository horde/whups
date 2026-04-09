<?php

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Jan Schneider <jan@horde.org>
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('whups');

use Horde\Date\Format as DateFormat;

$vars = Horde_Variables::getDefaultVariables();
$ticket = Whups::getCurrentTicket();

$view = $injector->createInstance('Horde_View');
try {
    $files = $ticket->listAllAttachments();
} catch (Whups_Exception $e) {
    $notification->push($e);
}
if ($files) {
    $format = [
        $prefs->getValue('date_format'),
        $prefs->getValue('time_format'),
    ];
    $attachments = Whups::getAttachments($ticket->getId());
    $view->attachments = [];
    foreach ($files as $file) {
        $view->attachments[] = array_merge(
            [
                'timestamp' => $file['timestamp'],
                'date' => DateFormat::formatDate(
                    $file['timestamp'],
                    $format[0] . ' ' . $format[1]
                ),
                'user' => Whups::formatUser(
                    Whups::getUserAttributes($file['user_id']),
                    true,
                    true,
                    true
                ),
            ],
            Whups::attachmentUrl(
                $ticket->getId(),
                $attachments[$file['value']],
                $ticket->get('queue')
            )
        );
    }
}

Whups::addTopbarSearch();
Whups::addFeedLink();
$page_output->addLinkTag($ticket->feedLink());
$page_output->addScriptFile('tables.js', 'horde');
$page_output->header([
    'title' => sprintf(_("Attachments for %s"), '[#' . $ticket->getId() . '] ' . $ticket->get('summary')),
]);
$notification->notify(['listeners' => 'status']);
echo Whups::getTicketTabs($vars, $ticket->getId())->render('attachments');
echo $view->render('ticket/attachments');
$page_output->footer();
