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

class EditVersionStepOneForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars      Request or form data
     * @param array<int,string>            $versions  Version id => name (including inactive)
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $versions,
    ) {
        parent::__construct($vars, _("Edit or Delete Versions"));
        $this->setButtons([
            _("Edit Version"),
            ['class' => 'horde-delete', 'value' => _("Delete Version")],
        ]);

        $this->addHidden('', 'queue', 'int', true, true);

        if (count($versions)) {
            $this->addVariable(_("Version Name"), 'version', 'enum', true, false, null, [$versions]);
        } else {
            $this->addVariable(_("Version Name"), 'version', 'invalid', false, false, null, [_("There are no versions to edit")]);
        }
    }
}
