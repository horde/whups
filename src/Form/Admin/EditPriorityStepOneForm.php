<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditPriorityStepOneForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $priorities,
    ) {
        parent::__construct($vars, _("Edit or Delete Priorities"));
        $this->setButtons([
            _("Edit Priority"),
            ['class' => 'horde-delete', 'value' => _("Delete Priority")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);

        if (count($priorities)) {
            $this->addVariable(_("Priority Name"), 'priority', 'enum', false, false, null, [$priorities]);
        } else {
            $this->addVariable(_("Priority Name"), 'priority', 'invalid', false, false, null, [_("There are no priorities to edit")]);
        }
    }
}
