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

class DeleteVersionForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $versionName,
        string $versionDescription,
    ) {
        parent::__construct($vars, _("Delete Version Confirmation"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete Version")],
        ]);

        $this->addHidden('', 'queue', 'int', true, true);
        $this->addHidden('', 'version', 'int', true, true);

        $vname = $this->addVariable(_("Version Name"), 'name', 'text', false, true);
        $vname->setDefault($versionName);

        $vdesc = $this->addVariable(_("Version Description"), 'description', 'text', false, true);
        $vdesc->setDefault($versionDescription);

        $this->addVariable(
            _("Really delete this version? This may cause data problems!"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]],
        );
    }
}
