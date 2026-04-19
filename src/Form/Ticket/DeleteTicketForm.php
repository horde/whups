<?php

declare(strict_types=1);

/**
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Ticket;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeleteTicketForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $title = '',
    ) {
        parent::__construct($vars, $title ?: _("Delete Ticket"));

        $this->addHidden('', 'id', 'int', true, true);

        $warn = $this->addVariable('', 'warn', 'html', false);
        $warn->setDefault(
            '<span class="horde-form-error">'
            . _("Really delete this ticket? It will NOT be archived, and will be gone forever.")
            . '</span>',
        );

        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete")],
            ['class' => 'horde-cancel', 'value' => _("Cancel")],
        ]);
    }
}
