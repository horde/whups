<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeleteAttributeForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $attributeName,
        string $attributeDescription,
    ) {
        parent::__construct($vars, _("Delete Attribute Confirmation"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete Attribute")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'attribute', 'int', true, true);

        $pname = $this->addVariable(_("Attribute Name"), 'attribute_name', 'text', false, true);
        $pname->setDefault($attributeName);

        $pdesc = $this->addVariable(_("Attribute Description"), 'attribute_description', 'text', false, true);
        $pdesc->setDefault($attributeDescription);

        $this->addVariable(
            _("Really delete this attribute? This may cause data problems!"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]],
        );
    }
}
