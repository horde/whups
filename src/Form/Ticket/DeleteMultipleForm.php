<?php

declare(strict_types=1);

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Ticket;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeleteMultipleForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars    Request or form data
     * @param array<int,string>            $tickets Ticket id => summary (pre-filtered by permission)
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $tickets,
    ) {
        parent::__construct($vars, sprintf(_("Delete %d tickets?"), count($tickets)));

        $this->addHidden('', 'tickets', 'text', true, true);
        $this->addHidden('', 'url', 'text', true, true);

        foreach ($tickets as $id => $summary) {
            $var = $this->addVariable(
                _("Ticket") . ' ' . $id,
                'summary' . $id,
                'text',
                false,
                true,
            );
            $var->setDefault($summary);
        }

        $this->addVariable('', 'warn', 'html', false)->setDefault(
            '<span class="horde-form-error">'
            . _("Really delete these tickets? They will NOT be archived, and will be gone forever.")
            . '</span>',
        );

        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete")],
            ['class' => 'horde-cancel', 'value' => _("Cancel")],
        ]);
    }
}
