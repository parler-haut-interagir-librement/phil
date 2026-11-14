<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Infrastructure\Form\SettingsForm;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\EchoTranslator;
use Phil\SettingsBundle\Tests\Unit\Fixture\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

#[CoversClass(SettingsForm::class)]
final class SettingsFormTest extends TestCase
{
    public function testEachTypeHasItsField(): void
    {
        $form = $this->authorForm();

        self::assertSame(SettingsForm::NAME, $form->getName());
        self::assertSame(['author-signature', 'author-columns', 'author-public', 'author-genres'], array_keys($form->all()));
        self::assertSame([], $form->getConfig()->getOption('attr'), 'nothing is written on the page');

        $signature = $form->get('author-signature')->getConfig();
        self::assertInstanceOf(TextType::class, $signature->getType()->getInnerType());
        self::assertSame(['author.signature.label', null, 'messages', false, '', ['maxlength' => 20]], [$signature->getOption('label'), $signature->getOption('help'), $signature->getOption('translation_domain'), $signature->getOption('required'), $signature->getOption('empty_data'), $signature->getOption('attr')]);

        $columns = $form->get('author-columns')->getConfig();
        self::assertInstanceOf(IntegerType::class, $columns->getType()->getInnerType());
        self::assertSame(['min' => 1, 'max' => 4], $columns->getOption('attr'));

        self::assertInstanceOf(CheckboxType::class, $form->get('author-public')->getConfig()->getType()->getInnerType());
        self::assertSame([], $form->get('author-public')->getConfig()->getOption('attr'));

        $genres = $form->get('author-genres')->getConfig();
        self::assertInstanceOf(ChoiceType::class, $genres->getType()->getInnerType());
        self::assertSame(
            [['author.genres.choices.fantasy' => 'fantasy', 'author.genres.choices.horror' => 'horror', 'author.genres.choices.science' => 'science'], 'messages', true, true, null, []],
            [$genres->getOption('choices'), $genres->getOption('choice_translation_domain'), $genres->getOption('expanded'), $genres->getOption('multiple'), $genres->getOption('placeholder'), $genres->getOption('attr')],
        );

        self::assertSame(['author-signature' => 'Ada', 'author-columns' => 3, 'author-public' => false, 'author-genres' => ['fantasy']], $form->getData());
    }

    public function testAFormWithoutChoiceWrittenOnThePageCarriesNoController(): void
    {
        $catalog = Catalogs::sample();

        self::assertCount(5, $catalog->settings(section: 'notifications'));
        self::assertSame([], new SettingsForm(Forms::factory())->create($catalog->settings(section: 'notifications'), [])->getConfig()->getOption('attr'));
        self::assertSame([], new SettingsForm(Forms::factory())->create([new SettingDefinition('a.count', SettingType::Integer)], [])->get('a-count')->getConfig()->getOption('attr'), 'an integer without bounds');
    }

    public function testAChoiceWrittenOnThePageCarriesItsAttribute(): void
    {
        $catalog = Catalogs::sample();
        $form = new SettingsForm(Forms::factory())->create($catalog->settings(section: 'display'), []);

        self::assertSame(['data-controller' => SettingsForm::CONTROLLER, 'data-action' => 'change->phil--settings-bundle--document#apply'], $form->getConfig()->getOption('attr'));

        $motion = $form->get('display-motion')->getConfig();
        self::assertSame([false, ['data-attribute' => 'motion', 'data-default' => 'system'], 'phil_settings'], [$motion->getOption('multiple'), $motion->getOption('attr'), $motion->getOption('translation_domain')]);
        self::assertSame('system', $form->get('display-motion')->getData());
        self::assertCount(2, $form->get('display-motion')->createView()->children, 'no empty choice is added');
        self::assertSame('notifications.quiet_start.help', new SettingsForm(Forms::factory())->create([$catalog->get('notifications.quiet_start')], [])->get('notifications-quiet_start')->getConfig()->getOption('help'));
    }

    public function testASubmittedFormGivesItsValuesByKey(): void
    {
        $form = $this->authorForm();
        $form->submit(['author-signature' => 'Grace', 'author-columns' => '4', 'author-public' => '1', 'author-genres' => ['science', 'horror']]);

        self::assertTrue($form->isValid());
        self::assertSame(['author.signature' => 'Grace', 'author.columns' => 4, 'author.public' => true, 'author.genres' => ['horror', 'science']], SettingsForm::values($form, Catalogs::sample()->settings(section: 'author')));
    }

    public function testARefusedValueIsAnErrorOnItsFieldInTheReadersLanguage(): void
    {
        $form = $this->authorForm(new EchoTranslator());
        $form->submit(['author-signature' => str_repeat('x', 21), 'author-columns' => '9', 'author-genres' => []]);

        self::assertFalse($form->isValid());
        self::assertSame('phil_settings.error.length@phil_settings{"%max%":20}', $form->get('author-signature')->getErrors()->current()->getMessage());
        self::assertSame('phil_settings.error.length', $form->get('author-signature')->getErrors()->current()->getMessageTemplate());
        self::assertSame(['%max%' => 20], $form->get('author-signature')->getErrors()->current()->getMessageParameters());
        self::assertSame('phil_settings.error.range@phil_settings{"%min%":1,"%max%":4}', $form->get('author-columns')->getErrors()->current()->getMessage());
        self::assertCount(0, $form->get('author-genres')->getErrors());
    }

    public function testWithoutTranslatorTheErrorIsTheReason(): void
    {
        $form = $this->authorForm();
        $form->submit(['author-columns' => '']);

        self::assertSame('The value of the setting "author.columns" is refused: an integer is expected.', $form->get('author-columns')->getErrors()->current()->getMessage());
    }

    public function testAFieldTheFormCouldNotReadKeepsItsOwnErrorOnly(): void
    {
        $catalog = Catalogs::sample();
        $form = new SettingsForm(Forms::factory(), new EchoTranslator())->create([$catalog->get('author.columns'), $catalog->get('author.signature')], []);
        $form->submit(['author-signature' => str_repeat('x', 21), 'author-columns' => 'three']);

        self::assertFalse($form->get('author-columns')->isSynchronized());
        self::assertCount(1, $form->get('author-columns')->getErrors());
        self::assertStringNotContainsString('phil_settings.error', $form->get('author-columns')->getErrors()->current()->getMessage());
        self::assertCount(1, $form->get('author-signature')->getErrors(), 'the other fields are still checked');
    }

    /**
     * @return FormInterface<mixed>
     */
    private function authorForm(?EchoTranslator $translator = null): FormInterface
    {
        return new SettingsForm(Forms::factory(), $translator)->create(Catalogs::sample()->settings(section: 'author'), ['author.signature' => 'Ada', 'author.columns' => 3]);
    }
}
