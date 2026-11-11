<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Unit\Infrastructure;

use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessengerBundle\Infrastructure\Form\HandleTransformer;
use Phil\InstantMessengerBundle\Infrastructure\Form\HandleType;
use Phil\InstantMessengerBundle\Tests\Unit\Fixtures\ForumMessenger;
use Phil\InstantMessengerBundle\Tests\Unit\Fixtures\LabelledMessenger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(HandleType::class)]
#[CoversClass(HandleTransformer::class)]
final class HandleTypeTest extends TestCase
{
    public function testAHandleIsEnteredWithItsService(): void
    {
        $form = $this->forms()->create(HandleType::class);
        $form->submit(['type' => 'Phil.InstantMessenger.KnownMessenger.Telegram', 'handle' => 't.me/AdaLove']);

        self::assertTrue($form->isValid());
        self::assertEquals(Handle::of(KnownMessenger::Telegram, 'adalove'), $form->getData());

        $type = $form->get('type')->getConfig();
        self::assertSame([false, false, KnownMessenger::cases()], [$type->getOption('label'), $type->getOption('choice_translation_domain'), $type->getOption('choices')]);
        self::assertSame([false, true], [$form->get('handle')->getConfig()->getOption('label'), $form->get('handle')->getConfig()->getOption('required')]);
        self::assertSame([true, false, null, 'This value is not a valid handle on this service.'], [$form->getConfig()->getCompound(), $form->getConfig()->getErrorBubbling(), $form->getConfig()->getDataClass(), $form->getConfig()->getOption('invalid_message')]);
    }

    public function testTheChoicesAreTheAcceptedServices(): void
    {
        $view = $this->forms()->create(HandleType::class, null, ['types' => [ForumMessenger::class, KnownMessenger::Signal]])->createView();

        $choices = $view['type']->vars['choices'];
        self::assertIsArray($choices);
        $labels = [];
        $values = [];

        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            $labels[] = $choice->label;
            $values[] = $choice->value;
        }

        self::assertSame(['Forum', 'Signal'], $labels);
        self::assertSame(['Phil.InstantMessengerBundle.Tests.Unit.Fixtures.ForumMessenger.Forum', 'Phil.InstantMessenger.KnownMessenger.Signal'], $values);
        self::assertSame(['XMPP', 'Forum', ''], [HandleType::label(KnownMessenger::Xmpp), HandleType::label(ForumMessenger::Forum), HandleType::value(null)]);
    }

    public function testAHandleIsShownAsPeopleReadIt(): void
    {
        $view = $this->forms()->create(HandleType::class, Handle::of(KnownMessenger::Telegram, 'adalove'))->createView();

        self::assertSame(['Phil.InstantMessenger.KnownMessenger.Telegram', '@adalove'], [$view['type']->vars['value'], $view['handle']->vars['value']]);
    }

    /**
     * A service whose `display()` is no handle it reads shows the canonical handle, so that an edit form submitted
     * unchanged keeps the handle; a phone number is shown in the international format it reads back.
     */
    public function testAnEditFormSubmittedUnchangedKeepsTheHandle(): void
    {
        $handle = Handle::of(LabelledMessenger::Board, 'ada');
        $form = $this->forms()->create(HandleType::class, $handle, ['types' => LabelledMessenger::class]);

        self::assertSame('ada', $form->createView()['handle']->vars['value']);

        $form->submit(['type' => 'Phil.InstantMessengerBundle.Tests.Unit.Fixtures.LabelledMessenger.Board', 'handle' => 'ada']);
        self::assertTrue($form->isValid());
        self::assertEquals($handle, $form->getData());

        $transformer = new HandleTransformer();
        self::assertSame(['type' => KnownMessenger::WhatsApp, 'handle' => '+1 514-555-0123'], $transformer->transform(Handle::of(KnownMessenger::WhatsApp, '+15145550123')));
        self::assertSame(['type' => ForumMessenger::Forum, 'handle' => 'forum:ada'], $transformer->transform(Handle::of(ForumMessenger::Forum, 'forum:ada')));
    }

    public function testAnEmptyHandleIsNoneAndABadOneIsInvalid(): void
    {
        $empty = $this->forms()->create(HandleType::class, null, ['required' => false]);
        $empty->submit(['type' => 'Phil.InstantMessenger.KnownMessenger.Telegram', 'handle' => '']);
        self::assertTrue($empty->isValid());
        self::assertNull($empty->getData());
        self::assertFalse($empty->get('handle')->getConfig()->getOption('required'));

        $bad = $this->forms()->create(HandleType::class);
        $bad->submit(['type' => 'Phil.InstantMessenger.KnownMessenger.WhatsApp', 'handle' => '514 555-0123']);
        self::assertFalse($bad->isValid());
        self::assertNull($bad->getData());
    }

    public function testTheTransformerSaysWhy(): void
    {
        $transformer = new HandleTransformer();

        self::assertSame(['type' => null, 'handle' => null], $transformer->transform(null));
        self::assertNull($transformer->reverseTransform([]));
        self::assertNull($transformer->reverseTransform(['type' => KnownMessenger::Signal, 'handle' => null]));

        foreach ([[['handle' => 'adalove'], 'A handle is given with its messaging service.'], [['type' => KnownMessenger::WhatsApp, 'handle' => 'ada'], '"ada" is not a WhatsApp handle.']] as [$value, $message]) {
            try {
                $transformer->reverseTransform($value);
                self::fail($message);
            } catch (TransformationFailedException $failed) {
                self::assertSame([$message, 0], [$failed->getMessage(), $failed->getCode()]);
            }
        }
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new HandleType())->getFormFactory();
    }
}
