<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Delete multiple tickets.
 *
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller\Ticket;

use Horde;
use Horde\Core\Service\PrefsService;
use Horde\Whups\Controller\ResponseTrait;
use Horde_Exception_NotFound;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_Session;
use Horde_Url;
use Horde_Variables;
use Horde_View_Topbar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Exception;
use Whups_Form_Ticket_DeleteMultiple;
use Whups_Ticket;

class DeleteMultipleController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly Horde_Session $session,
        private readonly Horde_View_Topbar $topbar,
        private readonly PrefsService $prefs,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $webroot = $this->registry->get('webroot', 'whups');
        $uid = $this->registry->getAuth() ?: '';

        $vars = Horde_Variables::getDefaultVariables();
        $deleteForm = new Whups_Form_Ticket_DeleteMultiple($vars);
        $title = sprintf(_("Delete %d tickets?"), count($deleteForm->getTickets()));
        $deleteForm->setTitle($title);

        // Handle form submission.
        if ($vars->get('formname') == 'whups_form_ticket_deletemultiple'
            && $deleteForm->validate($vars)
        ) {
            if ($vars->get('submitbutton') == _("Delete")) {
                $info = $deleteForm->getInfo($vars);
                $tickets = @unserialize($info['tickets']);
                foreach ($tickets as $id) {
                    try {
                        $details = $this->driver->getTicketDetails($id);
                        (new Whups_Ticket($id, $details))->delete();
                        $this->notification->push(
                            sprintf(_("Ticket %d has been deleted."), $id),
                            'horde.success',
                        );
                    } catch (Whups_Exception $e) {
                        $this->notification->push(
                            _("There was an error deleting the ticket:") . ' ' . $e->getMessage(),
                            'horde.error',
                        );
                    } catch (Horde_Exception_NotFound $e) {
                        $this->notification->push(sprintf(_("Ticket %d not found."), $id));
                    }
                }
            } else {
                $this->notification->push(_("The tickets were not deleted."), 'horde.message');
            }

            $url = Horde::verifySignedUrl($vars->get('url'));
            if (!$url) {
                $defaultView = $this->prefs->getValue($uid, 'whups', 'whups_default_view') ?: 'mybugs';
                $url = $webroot . '/' . $defaultView;
            }
            return $this->redirect((new Horde_Url($url, true))->toString());
        }

        // Render the confirmation form.
        $vars->set('tickets', serialize($deleteForm->getTickets()));

        $html = $this->renderChrome($title, function () use ($vars, $deleteForm, $webroot) {
            // Topbar search.
            $this->topbar->search = true;
            $this->topbar->searchAction = new Horde_Url($webroot . '/ticket');
            $this->topbar->searchLabel = $this->session->get('whups', 'search') ?: _("Ticket #Id");

            // Notifications.
            $this->notification->notify(['listeners' => 'status']);

            // Delete form.
            $deleteForm->renderActive(
                $deleteForm->getRenderer(),
                $vars,
                new Horde_Url($webroot . '/ticket/delete-multiple'),
                'post',
            );
        });

        return $this->htmlResponse($html);
    }
}
