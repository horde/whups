<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Form\V3\InvalidVariable;
use Horde\Whups\Form\Admin\EditVersionStepOneForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditVersionStepOneForm::class)]
class EditVersionStepOneFormTest extends TestCase
{
    private array $versions = [10 => '1.0', 20 => '2.0', 30 => '3.0-beta'];

    public function testConstructionWithVersions(): void
    {
        $form = new EditVersionStepOneForm([], $this->versions);

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn($v) => $v->getVarName(), $vars);

        $this->assertContains('version', $names);
    }

    public function testButtonLabels(): void
    {
        $form = new EditVersionStepOneForm([], $this->versions);

        $buttons = $form->getButtons();
        $this->assertCount(2, $buttons);
        $this->assertSame('Edit Version', $buttons[0]);
        $this->assertSame('Delete Version', $buttons[1]['value']);
        $this->assertSame('horde-delete', $buttons[1]['class']);
    }

    public function testConstructionWithEmptyVersions(): void
    {
        $form = new EditVersionStepOneForm([], []);

        $vars = $form->getVariables(flat: true);
        $versionVar = $vars[0];

        $this->assertSame('version', $versionVar->getVarName());
        $this->assertInstanceOf(InvalidVariable::class, $versionVar);
    }

    public function testHiddenQueueField(): void
    {
        $form = new EditVersionStepOneForm([], $this->versions);

        $all = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($all as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
    }

    public function testValidationPassesWithValidVersion(): void
    {
        $form = new EditVersionStepOneForm([
            'formname' => 'horde_whups_form_admin_editversionsteponeform',
            'queue' => '1',
            'version' => '20',
        ], $this->versions);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutVersion(): void
    {
        $form = new EditVersionStepOneForm([
            'formname' => 'horde_whups_form_admin_editversionsteponeform',
            'queue' => '1',
            'version' => '',
        ], $this->versions);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsTypedVersionId(): void
    {
        $form = new EditVersionStepOneForm([
            'formname' => 'horde_whups_form_admin_editversionsteponeform',
            'queue' => '1',
            'version' => '20',
        ], $this->versions);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(20, $info['version']);
    }

    public function testGetClickedButton(): void
    {
        $form = new EditVersionStepOneForm([
            'formname' => 'horde_whups_form_admin_editversionsteponeform',
            'queue' => '1',
            'version' => '10',
            'submitbutton' => 'Delete Version',
        ], $this->versions);

        $this->assertSame('Delete Version', $form->getClickedButton());
    }

    public function testIsSubmitted(): void
    {
        $submitted = new EditVersionStepOneForm([
            'formname' => 'horde_whups_form_admin_editversionsteponeform',
            'queue' => '1',
            'version' => '10',
        ], $this->versions);

        $notSubmitted = new EditVersionStepOneForm([], $this->versions);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
