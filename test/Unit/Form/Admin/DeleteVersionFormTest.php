<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\DeleteVersionForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteVersionForm::class)]
class DeleteVersionFormTest extends TestCase
{
    public function testConstructionSetsReadonlyDefaults(): void
    {
        $form = new DeleteVersionForm([], '2.0', 'Second release');

        $vars = $form->getVariables(flat: true);
        $byName = [];
        foreach ($vars as $var) {
            $byName[$var->getVarName()] = $var;
        }

        $this->assertSame('2.0', $byName['name']->getDefault());
        $this->assertSame('Second release', $byName['description']->getDefault());
        $this->assertTrue($byName['name']->isReadonly());
        $this->assertTrue($byName['description']->isReadonly());
    }

    public function testDeleteButtonHasDeleteClass(): void
    {
        $form = new DeleteVersionForm([], '2.0', 'desc');

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('horde-delete', $buttons[0]['class']);
        $this->assertSame('Delete Version', $buttons[0]['value']);
    }

    public function testHiddenFields(): void
    {
        $form = new DeleteVersionForm([], '2.0', 'desc');

        $all = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($all as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
        $this->assertContains('version', $hiddenNames);
    }

    public function testYesNoFieldExists(): void
    {
        $form = new DeleteVersionForm([], '2.0', 'desc');

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn ($v) => $v->getVarName(), $vars);

        $this->assertContains('yesno', $names);
    }

    public function testValidationPassesWithYesSelected(): void
    {
        $form = new DeleteVersionForm([
            'formname' => 'horde_whups_form_admin_deleteversionform',
            'queue' => '1',
            'version' => '5',
            'yesno' => '1',
        ], '2.0', 'desc');

        $this->assertTrue($form->validate());
    }

    public function testValidationPassesWithNoSelected(): void
    {
        $form = new DeleteVersionForm([
            'formname' => 'horde_whups_form_admin_deleteversionform',
            'queue' => '1',
            'version' => '5',
            'yesno' => '0',
        ], '2.0', 'desc');

        $this->assertTrue($form->validate());
    }

    public function testGetInfoReturnsTypedValues(): void
    {
        $form = new DeleteVersionForm([
            'formname' => 'horde_whups_form_admin_deleteversionform',
            'queue' => '1',
            'version' => '5',
            'yesno' => '1',
        ], '2.0', 'desc');

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(1, $info['queue']);
        $this->assertSame(5, $info['version']);
        $this->assertSame(1, $info['yesno']);
    }

    public function testGetInfoWhenNoSelected(): void
    {
        $form = new DeleteVersionForm([
            'formname' => 'horde_whups_form_admin_deleteversionform',
            'queue' => '1',
            'version' => '5',
            'yesno' => '0',
        ], '2.0', 'desc');

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(0, $info['yesno']);
    }

    public function testIsSubmitted(): void
    {
        $submitted = new DeleteVersionForm([
            'formname' => 'horde_whups_form_admin_deleteversionform',
            'queue' => '1',
            'version' => '5',
            'yesno' => '1',
        ], '2.0', 'desc');

        $notSubmitted = new DeleteVersionForm([], '2.0', 'desc');

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
