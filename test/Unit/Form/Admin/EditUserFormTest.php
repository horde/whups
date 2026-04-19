<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Form\V3\InvalidVariable;
use Horde\Whups\Form\Admin\EditUserForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditUserForm::class)]
class EditUserFormTest extends TestCase
{
    private array $queueUsers = [
        'alice' => 'Alice Smith',
        'bob' => 'Bob Jones',
    ];

    public function testConstructionWithUsersShowsEnum(): void
    {
        $form = new EditUserForm([], $this->queueUsers);

        $vars = $form->getVariables(flat: true);
        $userVar = null;
        foreach ($vars as $var) {
            if ($var->getVarName() === 'user') {
                $userVar = $var;
                break;
            }
        }

        $this->assertNotNull($userVar);
        $this->assertNotInstanceOf(InvalidVariable::class, $userVar);
    }

    public function testConstructionWithEmptyUsersShowsInvalid(): void
    {
        $form = new EditUserForm([], []);

        $vars = $form->getVariables(flat: true);
        $userVar = null;
        foreach ($vars as $var) {
            if ($var->getVarName() === 'user') {
                $userVar = $var;
                break;
            }
        }

        $this->assertInstanceOf(InvalidVariable::class, $userVar);
    }

    public function testRemoveButtonHasDeleteClass(): void
    {
        $form = new EditUserForm([], $this->queueUsers);

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('Remove User', $buttons[0]['value']);
        $this->assertSame('horde-delete', $buttons[0]['class']);
    }

    public function testHiddenQueueField(): void
    {
        $form = new EditUserForm([], $this->queueUsers);

        $all = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($all as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
    }

    public function testValidationPassesWithValidUser(): void
    {
        $form = new EditUserForm([
            'formname' => 'horde_whups_form_admin_edituserform',
            'queue' => '1',
            'user' => 'alice',
        ], $this->queueUsers);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutUser(): void
    {
        $form = new EditUserForm([
            'formname' => 'horde_whups_form_admin_edituserform',
            'queue' => '1',
            'user' => '',
        ], $this->queueUsers);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsSelectedUser(): void
    {
        $form = new EditUserForm([
            'formname' => 'horde_whups_form_admin_edituserform',
            'queue' => '1',
            'user' => 'bob',
        ], $this->queueUsers);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame('bob', $info['user']);
        $this->assertSame(1, $info['queue']);
    }

    public function testIsSubmitted(): void
    {
        $submitted = new EditUserForm([
            'formname' => 'horde_whups_form_admin_edituserform',
            'queue' => '1',
            'user' => 'alice',
        ], $this->queueUsers);

        $notSubmitted = new EditUserForm([], $this->queueUsers);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
