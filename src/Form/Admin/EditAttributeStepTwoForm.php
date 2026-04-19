<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditAttributeStepTwoForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $attributeInfo,
        array $fieldTypeNames,
        array $fieldTypeParams,
        string $selectedType,
    ) {
        parent::__construct($vars, _("Edit Attribute"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'attribute', 'int', true, true);

        $pname = $this->addVariable(_("Attribute Name"), 'attribute_name', 'text', true);
        $pname->setDefault($attributeInfo['name']);

        $pdesc = $this->addVariable(_("Attribute Description"), 'attribute_description', 'text', false);
        $pdesc->setDefault($attributeInfo['description']);

        $preq = $this->addVariable(_("Required Attribute?"), 'attribute_required', 'boolean', false);
        $preq->setDefault($attributeInfo['required']);

        $ptype = $this->addVariable(
            _("Attribute Type"),
            'attribute_type',
            'enum',
            true,
            false,
            null,
            [$fieldTypeNames],
        );
        $ptype->setDefault($selectedType);

        foreach ($fieldTypeParams as $param => $paramInfo) {
            $pparam = $this->addVariable(
                $paramInfo['label'],
                'attribute_params[' . $param . ']',
                $paramInfo['type'],
                false,
            );
            if (isset($attributeInfo['params'][$param])) {
                $pparam->setDefault($attributeInfo['params'][$param]);
            }
        }
    }
}
