<?php

declare(strict_types=1);

/**
 * Single-form replacement for SetTypeStepOneForm / SetTypeStepTwoForm.
 *
 * Uses FieldGroup sections with enabled/disabled state to implement
 * a 2-step wizard within a single form. The completed step is rendered
 * read-only with hidden field preservation; the current step is
 * rendered as editable controls.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Form\Ticket;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class TypeChangeForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars        Request or form data
     * @param int                          $step        Active wizard step (1 or 2)
     * @param array<int,string>            $types       Type id => name
     * @param array<int|string,string>     $groups      Group id => name (0 => "Any Group"), empty to omit
     * @param array<int,string>            $states      State id => name (for step 2)
     * @param array<int,string>            $priorities  Priority id => name (for step 2)
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        int $step,
        array $types,
        array $groups = [],
        array $states = [],
        array $priorities = [],
    ) {
        parent::__construct($vars, $this->stepTitle($step));

        // Hidden ticket ID — shared across both steps.
        $this->addHidden('', 'id', 'int', true, true);

        // Step 1: Type selection + optional comment/group.
        $this->setSection('step1', _("Step 1 - Type"));
        $this->addVariable(_("New Type"), 'type', 'enum', true, false, null, [$types]);
        $this->addVariable(_("Comment"), 'newcomment', 'longtext', false);
        if ($groups) {
            $this->addVariable(
                _("Viewable only by members of"),
                'group',
                'enum',
                true,
                false,
                null,
                [$groups],
            );
        }

        // Step 2: State and priority for the selected type.
        $this->setSection('step2', _("Step 2 - State & Priority"));
        $this->addVariable(_("State"), 'state', 'enum', true, false, null, [$states]);
        $this->addVariable(_("Priority"), 'priority', 'enum', true, false, null, [$priorities]);

        // Activate only the current step.
        $this->setActiveGroup('step' . $step);
    }

    private function stepTitle(int $step): string
    {
        return match ($step) {
            1 => _("Set Type - Step 1"),
            2 => _("Set Type - Step 2"),
            default => _("Set Type"),
        };
    }
}
