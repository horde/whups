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

class AddTypeForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
    ) {
        parent::__construct($vars, _("Add Type"));
        $this->appendButtons(_("Add Type"));

        $this->addVariable(_("Type Name"), 'name', 'text', true);
        $this->addVariable(_("Type Description"), 'description', 'text', true);
    }
}
