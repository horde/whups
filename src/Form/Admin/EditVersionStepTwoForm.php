<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditVersionStepTwoForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars         Request or form data
     * @param array                        $versionInfo  Version record (name, description, active)
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $versionInfo,
    ) {
        parent::__construct($vars, sprintf(_("Edit %s"), $versionInfo['name']));

        $this->addHidden('', 'queue', 'int', true, true);
        $this->addHidden('', 'version', 'int', true, true);

        $vname = $this->addVariable(_("Version Name"), 'name', 'text', true);
        $vname->setDefault($versionInfo['name']);

        $vdesc = $this->addVariable(_("Version Description"), 'description', 'text', true);
        $vdesc->setDefault($versionInfo['description']);

        $vactive = $this->addVariable(_("Version Active?"), 'active', 'boolean', false);
        $vactive->setDefault($versionInfo['active']);
    }
}
