<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class AddAttributeForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $fieldTypeNames,
        array $fieldTypeParams,
        string $selectedType = 'text',
    ) {
        parent::__construct($vars, _("Add Attribute"));
        $this->appendButtons(_("Add Attribute"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addVariable(_("Attribute Name"), 'attribute_name', 'text', true);
        $this->addVariable(_("Attribute Description"), 'attribute_description', 'text', false);
        $this->addVariable(_("Required Attribute?"), 'attribute_required', 'boolean', false);

        $v = $this->addVariable(
            _("Attribute Type"),
            'attribute_type',
            'enum',
            true,
            false,
            null,
            [$fieldTypeNames],
        );
        $v->setDefault($selectedType);

        foreach ($fieldTypeParams as $param => $info) {
            $this->addVariable(
                $info['label'],
                'attribute_params[' . $param . ']',
                $info['type'],
                false,
            );
        }
    }
}
