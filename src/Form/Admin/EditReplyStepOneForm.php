<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditReplyStepOneForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $replies,
    ) {
        parent::__construct($vars, _("Edit or Delete Form Replies"));
        $this->setButtons([
            _("Edit Form Reply"),
            ['class' => 'horde-delete', 'value' => _("Delete Form Reply")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);

        if (count($replies)) {
            $this->addVariable(_("Form Reply Name"), 'reply', 'enum', false, false, null, [$replies]);
        } else {
            $this->addVariable(_("Form Reply Name"), 'reply', 'invalid', false, false, null, [_("There are no form replies to edit")]);
        }
    }
}
