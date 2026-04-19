<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Form\V3\InvalidVariable;
use Horde\Whups\Form\Admin\AddUserForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddUserForm::class)]
class AddUserFormTest extends TestCase
{
    private array $availableUsers = [
        'alice' => 'Alice Smith',
        'bob' => 'Bob Jones',
        'charlie' => 'Charlie Brown',
    ];

    public function testConstructionWithListableUsersShowsMultienum(): void
    {
        $form = new AddUserForm([], $this->availableUsers, true);

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

    public function testConstructionWithNoListCapabilityShowsText(): void
    {
        $form = new AddUserForm([], [], false);

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
        $form = new AddUserForm([], [], true);

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

    public function testButtonLabel(): void
    {
        $form = new AddUserForm([], $this->availableUsers, true);

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('Add User', $buttons[0]);
    }

    public function testHiddenQueueField(): void
    {
        $form = new AddUserForm([], $this->availableUsers, true);

        $all = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($all as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
    }

    public function testValidationPassesWithTextInput(): void
    {
        $form = new AddUserForm([
            'formname' => 'horde_whups_form_admin_adduserform',
            'queue' => '1',
            'user' => 'newuser',
        ], [], false);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutUserWhenTextMode(): void
    {
        $form = new AddUserForm([
            'formname' => 'horde_whups_form_admin_adduserform',
            'queue' => '1',
            'user' => '',
        ], [], false);

        $this->assertFalse($form->validate());
    }

    public function testIsSubmitted(): void
    {
        $submitted = new AddUserForm([
            'formname' => 'horde_whups_form_admin_adduserform',
            'queue' => '1',
            'user' => 'alice',
        ], $this->availableUsers, true);

        $notSubmitted = new AddUserForm([], $this->availableUsers, true);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }

    public function testGetInfoReturnsSubmittedUser(): void
    {
        $form = new AddUserForm([
            'formname' => 'horde_whups_form_admin_adduserform',
            'queue' => '1',
            'user' => 'newuser',
        ], [], false);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame('newuser', $info['user']);
        $this->assertSame(1, $info['queue']);
    }
}
