<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class SendReminderForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $queues,
        array $categories,
    ) {
        parent::__construct($vars, _("Send Reminders"));
        $this->appendButtons(_("Send Reminders"));

        $this->addVariable(
            _("Send only for this list of ticket ids"),
            'id',
            'intlist',
            false,
        );

        if (count($queues)) {
            $this->addVariable(
                _("For tickets from these queues"),
                'queue',
                'enum',
                false,
                false,
                null,
                [$queues],
            );
        } else {
            $this->addVariable(
                _("For tickets from these queues"),
                'queue',
                'invalid',
                false,
                false,
                null,
                [_("There are no queues available.")],
            );
        }

        $catVar = $this->addVariable(
            _("For tickets which are"),
            'category',
            'multienum',
            false,
            false,
            null,
            [$categories, 3],
        );
        $catVar->setDefault(['assigned']);

        $this->addVariable(
            _("Unassigned tickets"),
            'unassigned',
            'email',
            false,
            false,
            _("If you select any tickets that do not have an owner, who should we send email to?"),
        );
    }
}
