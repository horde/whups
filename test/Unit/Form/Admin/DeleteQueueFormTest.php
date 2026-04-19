<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\DeleteQueueForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteQueueForm::class)]
class DeleteQueueFormTest extends TestCase
{
    public function testConstructionSetsReadonlyDefaults(): void
    {
        $form = new DeleteQueueForm([], 'Support', 'Help desk queue');

        $vars = $form->getVariables(flat: true);
        $byName = [];
        foreach ($vars as $var) {
            $byName[$var->getVarName()] = $var;
        }

        $this->assertSame('Support', $byName['name']->getDefault());
        $this->assertSame('Help desk queue', $byName['description']->getDefault());
        $this->assertTrue($byName['name']->isReadonly());
        $this->assertTrue($byName['description']->isReadonly());
    }

    public function testDeleteButtonHasDeleteClass(): void
    {
        $form = new DeleteQueueForm([], 'Support', 'desc');

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('horde-delete', $buttons[0]['class']);
        $this->assertSame('Delete Queue', $buttons[0]['value']);
    }

    public function testHiddenQueueField(): void
    {
        $form = new DeleteQueueForm([], 'Support', 'desc');

        $hidden = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($hidden as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
    }

    public function testValidationPassesWithYesSelected(): void
    {
        $form = new DeleteQueueForm([
            'formname' => 'horde_whups_form_admin_deletequeueform',
            'queue' => '5',
            'yesno' => '1',
        ], 'Support', 'desc');

        $this->assertTrue($form->validate());
    }

    public function testValidationPassesWithNoSelected(): void
    {
        $form = new DeleteQueueForm([
            'formname' => 'horde_whups_form_admin_deletequeueform',
            'queue' => '5',
            'yesno' => '0',
        ], 'Support', 'desc');

        $this->assertTrue($form->validate());
    }

    public function testGetInfoReturnsTypedValues(): void
    {
        $form = new DeleteQueueForm([
            'formname' => 'horde_whups_form_admin_deletequeueform',
            'queue' => '5',
            'yesno' => '1',
        ], 'Support', 'desc');

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(5, $info['queue']);
        // yesno is an enum with int keys 0/1
        $this->assertSame(1, $info['yesno']);
    }

    public function testGetInfoWhenNoSelected(): void
    {
        $form = new DeleteQueueForm([
            'formname' => 'horde_whups_form_admin_deletequeueform',
            'queue' => '5',
            'yesno' => '0',
        ], 'Support', 'desc');

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(0, $info['yesno']);
    }

    public function testIsSubmitted(): void
    {
        $submitted = new DeleteQueueForm([
            'formname' => 'horde_whups_form_admin_deletequeueform',
            'queue' => '5',
            'yesno' => '1',
        ], 'Support', 'desc');

        $notSubmitted = new DeleteQueueForm([], 'Support', 'desc');

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
