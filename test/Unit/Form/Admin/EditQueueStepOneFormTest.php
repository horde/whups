<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\EditQueueStepOneForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditQueueStepOneForm::class)]
class EditQueueStepOneFormTest extends TestCase
{
    private array $queues = [1 => 'Support', 2 => 'Development', 3 => 'Sales'];

    public function testConstructionWithDeletePermission(): void
    {
        $form = new EditQueueStepOneForm([], $this->queues, true);

        $buttons = $form->getButtons();
        $this->assertCount(2, $buttons);
        $this->assertSame('Edit Queue', $buttons[0]);
        $this->assertSame('Delete Queue', $buttons[1]['value']);
        $this->assertSame('horde-delete', $buttons[1]['class']);
    }

    public function testConstructionWithoutDeletePermission(): void
    {
        $form = new EditQueueStepOneForm([], $this->queues, false);

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('Edit Queue', $buttons[0]);
    }

    public function testConstructionWithEmptyQueues(): void
    {
        $form = new EditQueueStepOneForm([], [], true);

        $vars = $form->getVariables(flat: true);
        $queueVar = $vars[0];
        $this->assertSame('queue', $queueVar->getVarName());
        // With no queues, the type should be 'invalid'
        $this->assertInstanceOf(\Horde\Form\V3\InvalidVariable::class, $queueVar);
    }

    public function testValidationPassesWithValidQueue(): void
    {
        $form = new EditQueueStepOneForm([
            'formname' => 'horde_whups_form_admin_editqueuesteponeform',
            'queue' => '2',
        ], $this->queues, true);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutQueue(): void
    {
        $form = new EditQueueStepOneForm([
            'formname' => 'horde_whups_form_admin_editqueuesteponeform',
            'queue' => '',
        ], $this->queues, true);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsTypedQueueId(): void
    {
        $form = new EditQueueStepOneForm([
            'formname' => 'horde_whups_form_admin_editqueuesteponeform',
            'queue' => '2',
        ], $this->queues, true);

        $form->validate();
        $info = $form->getInfo();

        // EnumVariable should return the key in its original type (int)
        $this->assertSame(2, $info['queue']);
    }

    public function testGetClickedButtonReturnsButtonLabel(): void
    {
        $form = new EditQueueStepOneForm([
            'formname' => 'horde_whups_form_admin_editqueuesteponeform',
            'queue' => '1',
            'submitbutton' => 'Delete Queue',
        ], $this->queues, true);

        $this->assertSame('Delete Queue', $form->getClickedButton());
    }

    public function testGetClickedButtonEmptyWhenNoButton(): void
    {
        $form = new EditQueueStepOneForm([], $this->queues, true);

        $this->assertSame('', $form->getClickedButton());
    }

    public function testIsSubmitted(): void
    {
        $submitted = new EditQueueStepOneForm([
            'formname' => 'horde_whups_form_admin_editqueuesteponeform',
            'queue' => '1',
        ], $this->queues, true);

        $notSubmitted = new EditQueueStepOneForm([], $this->queues, true);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
