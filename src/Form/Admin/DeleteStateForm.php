<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeleteStateForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $stateName,
        string $stateDescription,
    ) {
        parent::__construct($vars, _("Delete State Confirmation"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete State")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'state', 'int', true, true);

        $sname = $this->addVariable(_("State Name"), 'name', 'text', false, true);
        $sname->setDefault($stateName);

        $sdesc = $this->addVariable(_("State Description"), 'description', 'text', false, true);
        $sdesc->setDefault($stateDescription);

        $this->addVariable(
            _("Really delete this state? This may cause data problems!"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]],
        );
    }
}
