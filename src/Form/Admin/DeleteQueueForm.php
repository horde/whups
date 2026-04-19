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

class DeleteQueueForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars      Request or form data
     * @param string                       $name      Queue name
     * @param string                       $description Queue description
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        string $name,
        string $description,
    ) {
        parent::__construct($vars, _("Delete Queue Confirmation"));

        $this->addHidden('', 'queue', 'int', true, true);

        $mname = $this->addVariable(_("Queue Name"), 'name', 'text', false, true);
        $mname->setDefault($name);

        $mdesc = $this->addVariable(_("Queue Description"), 'description', 'text', false, true);
        $mdesc->setDefault($description);

        $this->addVariable(
            _("Really delete this queue? This will also delete all associated tickets and their comments. This can not be undone!"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]]
        );

        $this->setButtons([['class' => 'horde-delete', 'value' => _("Delete Queue")]]);
    }
}
