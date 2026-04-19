<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditStateStepOneForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $states,
    ) {
        parent::__construct($vars, _("Edit or Delete States"));
        $this->setButtons([
            _("Edit State"),
            ['class' => 'horde-delete', 'value' => _("Delete State")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);

        if (count($states)) {
            $this->addVariable(_("State Name"), 'state', 'enum', false, false, null, [$states]);
        } else {
            $this->addVariable(_("State Name"), 'state', 'invalid', false, false, null, [_("There are no states to edit")]);
        }
    }
}
