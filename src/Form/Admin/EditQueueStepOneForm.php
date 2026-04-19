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

class EditQueueStepOneForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars   Request or form data
     * @param array<int,string>            $queues Queue id => name map
     * @param bool                         $canDelete Whether delete button is shown
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $queues,
        bool $canDelete,
    ) {
        if ($canDelete) {
            parent::__construct($vars, _("Edit or Delete Queues"));
            $this->setButtons([
                _("Edit Queue"),
                ['class' => 'horde-delete', 'value' => _("Delete Queue")],
            ]);
        } else {
            parent::__construct($vars, _("Edit Queues"));
            $this->setButtons([_("Edit Queue")]);
        }

        if ($queues) {
            $modtype = 'enum';
            $type_params = [$queues];
        } else {
            $modtype = 'invalid';
            $type_params = [_("There are no queues to edit")];
        }

        $this->addVariable(
            _("Queue Name"),
            'queue',
            $modtype,
            true,
            false,
            null,
            $type_params
        );
    }
}
