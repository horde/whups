<?php

declare(strict_types=1);

/**
 * Single-form replacement for CreateStepOne/Two/Three/FourForm.
 *
 * Uses FieldGroup sections with enabled/disabled state to implement
 * a 4-step wizard within a single form. Completed steps are rendered
 * read-only with hidden field preservation; the current step is
 * rendered as editable controls.
 *
 * Step 4 (owner assignment) is optional — only added when the selected
 * state is "assigned" and the user is authenticated.
 *
 * Copyright 2001-2026 Robert E. Coyle <robertecoyle@hotmail.com>
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
use Horde\Form\V3\SubmitAction;
use Psr\Http\Message\ServerRequestInterface;

class CreateTicketForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars            Request or form data
     * @param int                          $step            Active wizard step (1-4)
     * @param array<int,string>            $queues          Queue id => "name [description]"
     * @param array<int,string>            $types           Type id => name (step 2+)
     * @param int|null                     $defaultType     Default type id (step 2+)
     * @param array<int,string>|null       $versions        Version id => name, null if not versioned (step 2+)
     * @param array<int,string>            $states          State id => name (step 3+)
     * @param int|null                     $defaultState    Default state id (step 3+)
     * @param array<int,string>            $priorities      Priority id => name (step 3+)
     * @param int|null                     $defaultPriority Default priority id (step 3+)
     * @param array<int,array>             $attributes      Type attributes from getAttributesForType (step 3+)
     * @param bool                         $isGuest         True if user is not authenticated
     * @param bool                         $canSetRequester True if queue has 'requester' permission
     * @param string|null                  $captchaText     CAPTCHA text for guests (step 3)
     * @param string|null                  $captchaFont     Figlet font path for CAPTCHA
     * @param array<int|string,string>     $groups          Group id => name for comment visibility
     * @param array<string,string>         $ownerUsers      "user:uid" => formatted name (step 4)
     * @param array<string,string>         $ownerGroups     "group:gid" => group name (step 4)
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        int $step,
        array $queues,
        array $types = [],
        ?int $defaultType = null,
        ?array $versions = null,
        array $states = [],
        ?int $defaultState = null,
        array $priorities = [],
        ?int $defaultPriority = null,
        array $attributes = [],
        bool $isGuest = false,
        bool $canSetRequester = false,
        ?string $captchaText = null,
        ?string $captchaFont = null,
        array $groups = [],
        array $ownerUsers = [],
        array $ownerGroups = [],
    ) {
        parent::__construct($vars, $this->stepTitle($step));

        // CSRF token only on steps 3+ (steps 1-2 are progressively
        // re-validated on later submissions; consuming a token early
        // would cause failures when re-validating during later steps).
        if ($step < 3) {
            $this->useFormToken = false;
        }

        // ================================================================
        // Step 1: Queue selection
        // ================================================================
        $this->setSection('step1', _("Step 1 - Queue"));

        if (!$queues) {
            $this->addVariable(
                _("Queue Name"),
                'queue',
                'invalid',
                true,
                false,
                null,
                [_("There are no queues which you can create tickets in.")],
            );
        } else {
            $queueVar = $this->addVariable(
                _("Queue Name"),
                'queue',
                'enum',
                true,
                false,
                null,
                [$queues, _("Choose:")],
            );
            $queueVar->setAction(new SubmitAction());
        }

        // ================================================================
        // Step 2: Type and optional version
        // ================================================================
        $this->setSection('step2', _("Step 2 - Type"));

        if (count($types) === 0) {
            $this->addVariable(
                _("Ticket Type"),
                'type',
                'invalid',
                true,
                false,
                null,
                [_("There are no ticket types associated with this queue; until there are, you cannot create any tickets in this queue.")],
            );
        } else {
            $params = [$types];
            if ($defaultType === null || !isset($types[$defaultType])) {
                $params[] = _("Choose:");
            }
            $typeVar = $this->addVariable(
                _("Ticket Type"),
                'type',
                'enum',
                true,
                false,
                null,
                $params,
            );
            if ($defaultType !== null) {
                $typeVar->setDefault($defaultType);
            }

            // Auto-submit when type is selected if queue is not versioned.
            if ($versions === null) {
                $typeVar->setAction(new SubmitAction());
            }
        }

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
                $this->addVariable(
                    _("Queue Version"),
                    'version',
                    'enum',
                    true,
                    false,
                    null,
                    [$versions],
                );
            }
        }

        // ================================================================
        // Step 3: Ticket details
        // ================================================================
        $this->setSection('step3', _("Step 3 - Details"));

        // Requester email — permission-gated or mandatory guest email.
        if ($canSetRequester) {
            $this->addVariable(
                _("The Requester's Email Address"),
                'user_email',
                'email',
                false,
            );
        } elseif ($isGuest) {
            $this->addVariable(
                _("Your Email Address"),
                'user_email',
                'email',
                true,
            );
            if ($captchaText !== null) {
                $this->addVariable(
                    _("Spam protection"),
                    'captcha',
                    'figlet',
                    true,
                    false,
                    null,
                    [$captchaText, $captchaFont],
                );
            }
        }

        // State — silently hidden if only one choice.
        if (count($states) === 1) {
            $this->addHidden(_("Ticket State"), 'state', 'enum', true, false, null, [$states]);
        } else {
            $stateVar = $this->addVariable(
                _("Ticket State"),
                'state',
                'enum',
                true,
                false,
                null,
                [$states],
            );
            if ($defaultState !== null) {
                $stateVar->setDefault($defaultState);
            }
        }

        // Priority.
        $priorityVar = $this->addVariable(
            _("Priority"),
            'priority',
            'enum',
            true,
            false,
            null,
            [$priorities],
        );
        if ($defaultPriority !== null) {
            $priorityVar->setDefault($defaultPriority);
        }

        $this->addVariable(_("Due Date"), 'due', 'monthdayyear', false);
        $this->addVariable(_("Summary"), 'summary', 'text', true);
        $this->addVariable(_("Attachment"), 'newattachment', 'file', false);
        $this->addVariable(_("Description"), 'comment', 'longtext', true);

        // Dynamic attributes for the selected ticket type.
        foreach ($attributes as $attrId => $attr) {
            $this->addVariable(
                $attr['human_name'],
                'attributes[' . $attrId . ']',
                $attr['type'],
                $attr['required'],
                $attr['readonly'],
                $attr['desc'],
                $attr['params'] ?? [],
            );
        }

        // Comment visibility groups.
        if ($groups) {
            $groupVar = $this->addVariable(
                _("Make this comment visible only to members of a group?"),
                'group',
                'enum',
                false,
                false,
                null,
                [$groups],
            );
            $groupVar->setDefault(0);
        }

        // ================================================================
        // Step 4: Owner assignment (conditional)
        // ================================================================
        if ($step === 4) {
            $this->setSection('step4', _("Step 4 - Owners"));

            // Preserve deferred attachment filename from step 3.
            $this->addHidden('', 'deferred_attachment', 'text', false, true);

            if ($ownerUsers) {
                $this->addVariable(
                    _("Owners"),
                    'owners',
                    'multienum',
                    false,
                    false,
                    null,
                    [$ownerUsers],
                );
            }

            if ($ownerGroups) {
                $this->addVariable(
                    _("Group Owners"),
                    'group_owners',
                    'multienum',
                    false,
                    false,
                    null,
                    [$ownerGroups],
                );
            }

            if (!$ownerUsers && !$ownerGroups) {
                $this->addVariable(
                    _("Owners"),
                    'owners',
                    'invalid',
                    false,
                    false,
                    null,
                    [_("There are no users to which this ticket can be assigned.")],
                );
            }
        }

        // Activate only the current step.
        $this->setActiveGroup('step' . $step);
    }

    private function stepTitle(int $step): string
    {
        return match ($step) {
            1 => _("Create Ticket - Step 1"),
            2 => _("Create Ticket - Step 2"),
            3 => _("Create Ticket - Step 3"),
            4 => _("Create Ticket - Step 4"),
            default => _("Create Ticket"),
        };
    }
}
