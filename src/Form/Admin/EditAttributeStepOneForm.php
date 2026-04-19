<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditAttributeStepOneForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $attributes,
    ) {
        parent::__construct($vars, _("Edit or Delete Attributes"));
        $this->setButtons([
            _("Edit Attribute"),
            ['class' => 'horde-delete', 'value' => _("Delete Attribute")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);

        if (count($attributes)) {
            $this->addVariable(_("Attribute Name"), 'attribute', 'enum', false, false, null, [$attributes]);
        } else {
            $this->addVariable(_("Attribute Name"), 'attribute', 'invalid', false, false, null, [_("There are no attributes to edit")]);
        }
    }
}
