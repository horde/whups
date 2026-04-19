<?php

declare(strict_types=1);

/**
 * Single-form replacement for QueueMoveStepOne/Two/ThreeForm.
 *
 * Uses FieldGroup sections with enabled/disabled state to implement
 * a 3-step wizard within a single form. Completed steps are rendered
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

class QueueMoveForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars        Request or form data
     * @param int                          $step        Active wizard step (1, 2, or 3)
     * @param array<int,string>            $queues      Queue id => name (permission-filtered)
     * @param array<int|string,string>     $groups      Group id => name (0 => "Any Group"), empty to omit
     * @param array<int,string>            $types       Type id => name (for step 2+)
     * @param array<int,string>|null       $versions    Version id => name, null if not versioned (step 2+)
     * @param array<int,string>            $states      State id => name (for step 3)
     * @param array<int,string>            $priorities  Priority id => name (for step 3)
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        int $step,
        array $queues,
        array $groups = [],
        array $types = [],
        ?array $versions = null,
        array $states = [],
        array $priorities = [],
    ) {
        parent::__construct($vars, $this->stepTitle($step));

        // Hidden ticket ID — shared across all steps.
        $this->addHidden('', 'id', 'int', true, true);

        // Step 1: Queue selection + optional comment/group.
        $this->setSection('step1', _("Step 1 - Queue"));
        $this->addVariable(_("New Queue"), 'queue', 'enum', true, false, null, [$queues]);
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

        // Step 2: Type and optional version.
        $this->setSection('step2', _("Step 2 - Type"));
        if ($versions !== null) {
            if (count($versions) === 0) {
                $this->addVariable(
                    _("Queue Version"),
                    'version',
                    'invalid',
                    true,
                    false,
                    null,
                    [_("This queue requires that you specify a version, but there are no versions associated with it. Until versions are created for this queue, you will not be able to create tickets.")],
                );
            } else {
                $this->addVariable(_("Queue Version"), 'version', 'enum', true, false, null, [$versions]);
            }
        }
        $this->addVariable(_("Type"), 'type', 'enum', true, false, null, [$types]);

        // Step 3: State and priority.
        $this->setSection('step3', _("Step 3 - State & Priority"));
        $this->addVariable(_("State"), 'state', 'enum', true, false, null, [$states]);
        $this->addVariable(_("Priority"), 'priority', 'enum', true, false, null, [$priorities]);

        // Activate only the current step.
        $this->setActiveGroup('step' . $step);
    }

    private function stepTitle(int $step): string
    {
        return match ($step) {
            1 => _("Set Queue - Step 1"),
            2 => _("Set Queue - Step 2"),
            3 => _("Set Queue - Step 3"),
            default => _("Set Queue"),
        };
    }
}
