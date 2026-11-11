<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Phil\ContentWarningsBundle\Infrastructure\Form\ContentWarningsTransformer;
use Phil\ContentWarningsBundle\Infrastructure\Form\ContentWarningsType;
use Phil\ContentWarningsBundle\Infrastructure\Form\WarningPreferencesTransformer;
use Phil\ContentWarningsBundle\Infrastructure\Form\WarningPreferencesType;
use Phil\ContentWarningsBundle\Tests\Unit\Fixtures\Catalogues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(ContentWarningsType::class)]
#[CoversClass(ContentWarningsTransformer::class)]
#[CoversClass(WarningPreferencesType::class)]
#[CoversClass(WarningPreferencesTransformer::class)]
final class FormTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testThemesArePickedByCategoryInTheLocale(): void
    {
        $form = $this->forms()->create(ContentWarningsType::class);
        $view = $form->createView();

        self::assertSame('phil_content_warnings', $form->getConfig()->getType()->getBlockPrefix());
        self::assertTrue($form->getConfig()->getOption('expanded'));
        self::assertSame(['form.warnings', 'phil_content_warnings', false], [$view->vars['label'], $view->vars['translation_domain'], $view->vars['choice_translation_domain']]);
        $choices = $view->vars['choices'];
        self::assertIsArray($choices);
        self::assertSame(['Phobies', 'Violence'], array_keys($choices));
        $violence = $choices['Violence'];
        self::assertInstanceOf(ChoiceGroupView::class, $violence);
        self::assertSame(['Gore', 'Guerre'], $this->labels($violence->choices));

        $english = $this->forms()->create(ContentWarningsType::class, null, ['locale' => 'en', 'expanded' => false])->createView()->vars['choices'];
        self::assertIsArray($english);
        self::assertSame(['Phobias', 'Violence'], array_keys($english));
    }

    public function testCheckedThemesBecomeWarningsThatKeepTheirSeverityAndNote(): void
    {
        $form = $this->forms()->create(ContentWarningsType::class, ContentWarnings::of(new ContentWarning('gore', Severity::Severe, 'Act II'), new ContentWarning('retired', note: 'Kept'), new ContentWarning('war')));

        self::assertSame(['gore', 'war'], $form->getViewData());
        $form->submit(['spiders', 'gore']);

        self::assertTrue($form->isSynchronized());
        $warnings = $form->getData();
        self::assertInstanceOf(ContentWarnings::class, $warnings);
        self::assertSame(['gore', 'retired', 'spiders'], $warnings->themes());
        self::assertSame([Severity::Severe, 'Act II'], [$warnings->get('gore')?->severity, $warnings->get('gore')?->note]);
        self::assertSame('Kept', $warnings->get('retired')?->note);
    }

    public function testNothingCheckedIsNoWarnings(): void
    {
        $form = $this->forms()->create(ContentWarningsType::class);
        self::assertSame([], $form->getViewData());
        $form->submit([]);

        self::assertNull($form->getData());
    }

    public function testTheFieldIsAlwaysMultiple(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->forms()->create(ContentWarningsType::class, null, ['multiple' => false]);
    }

    public function testThePreferencesOptionsAreTyped(): void
    {
        $refusals = 0;

        foreach ([['themes' => 'yes'], ['locale' => 3]] as $options) {
            try {
                $this->forms()->create(WarningPreferencesType::class, null, $options);
            } catch (InvalidOptionsException) {
                ++$refusals;
            }
        }

        self::assertSame(2, $refusals);
    }

    public function testTheLocaleIsAString(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->forms()->create(ContentWarningsType::class, null, ['locale' => 3]);
    }

    public function testWithoutDataTheWarningsTransformerChecksNothing(): void
    {
        $transformer = new ContentWarningsTransformer(Catalogues::small());

        self::assertSame([], $transformer->transform(null));
        self::assertNull($transformer->reverseTransform(null));
        self::assertSame(['gore', '2024'], $transformer->reverseTransform(['gore', '2024'])?->themes());
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('Invalid content warning: a piece of content has at most 100 warnings.');
        $transformer->reverseTransform(array_map(static fn (int $index): string => 't'.$index, range(1, 101)));
    }

    public function testPreferencesAreChosenByCategoryAndTheme(): void
    {
        $form = $this->forms()->create(WarningPreferencesType::class);
        $view = $form->createView();

        self::assertSame('phil_content_warnings_preferences', $form->getConfig()->getType()->getBlockPrefix());
        self::assertSame(['default', 'categories', 'themes'], array_keys($form->all()));
        self::assertSame(['phobias', 'violence', 'empty'], array_keys($form->get('categories')->all()));
        self::assertSame(['gore', 'war'], array_keys($form->get('themes')->get('violence')->all()));
        self::assertSame([], $form->get('themes')->get('empty')->all());
        self::assertSame(Display::Fold, $form->get('default')->getData());
        self::assertSame(['Phobies', 'phil_content_warnings', 'preferences.inherit'], [$view['categories']['phobias']->vars['label'], $view['categories']['phobias']->vars['choice_translation_domain'], $view['categories']['phobias']->vars['placeholder']]);
        self::assertSame(['Guerre', false], [$view['themes']['violence']['war']->vars['label'], $view['themes']['violence']['war']->vars['required']]);
        self::assertFalse($view['categories']['violence']->vars['required']);
        self::assertSame(['Violence', false], [$view['themes']['violence']->vars['label'], $view['themes']['violence']->vars['translation_domain']]);
        self::assertSame(['preferences.default', 'phil_content_warnings', true], [$view['default']->vars['label'], $view['default']->vars['translation_domain'], $view['default']->vars['required']]);
        self::assertSame(['preferences.categories', 'preferences.themes'], [$view['categories']->vars['label'], $view['themes']->vars['label']]);
        $displays = $view['default']->vars['choices'];
        self::assertIsArray($displays);
        self::assertSame(['display.show', 'display.fold', 'display.hide'], $this->labels($displays));
        self::assertFalse($view->vars['label']);

        self::assertSame('Spiders', $this->forms()->create(WarningPreferencesType::class, null, ['locale' => 'en'])->createView()['themes']['phobias']['spiders']->vars['label']);
        self::assertSame(['default', 'categories'], array_keys($this->forms()->create(WarningPreferencesType::class, null, ['themes' => false])->all()));
        self::assertSame(Display::Show, $this->forms(Display::Show)->create(WarningPreferencesType::class)->get('default')->getData());
    }

    public function testSubmittedPreferencesKeepWhatTheFormDoesNotChange(): void
    {
        $preferences = WarningPreferences::defaults()
            ->withTheme('gore', Display::Hide, Severity::Severe)
            ->withTheme('war', Display::Fold, Severity::Moderate)
            ->withTheme('retired', Display::Hide)
            ->withCategory('phobias', Display::Show, Severity::Mild)
            ->withCategory('violence', Display::Fold);
        $form = $this->forms()->create(WarningPreferencesType::class, $preferences);

        self::assertSame(Display::Hide, $form->get('themes')->get('violence')->get('gore')->getData());
        self::assertSame(Display::Show, $form->get('categories')->get('phobias')->getData());
        self::assertNull($form->get('themes')->get('phobias')->get('spiders')->getData());

        $form->submit([
            'default' => 'show',
            'categories' => ['phobias' => 'show', 'violence' => ''],
            'themes' => ['phobias' => ['spiders' => 'fold'], 'violence' => ['gore' => 'hide', 'war' => 'hide']],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $saved = $form->getData();
        self::assertInstanceOf(WarningPreferences::class, $saved);
        self::assertSame(Display::Show, $saved->default);
        self::assertSame([Display::Hide, Severity::Severe], [$saved->theme('gore')?->display, $saved->theme('gore')?->atLeast]);
        self::assertSame([Display::Hide, null], [$saved->theme('war')?->display, $saved->theme('war')?->atLeast]);
        self::assertSame([Display::Fold, null], [$saved->theme('spiders')?->display, $saved->theme('spiders')?->atLeast]);
        self::assertSame(Display::Hide, $saved->theme('retired')?->display);
        self::assertSame([Display::Show, Severity::Mild], [$saved->category('phobias')?->display, $saved->category('phobias')?->atLeast]);
        self::assertNull($saved->category('violence'));

        $form = $this->forms()->create(WarningPreferencesType::class, $preferences);
        $form->submit(['default' => 'fold', 'categories' => ['phobias' => 'hide'], 'themes' => ['violence' => ['gore' => '']]]);

        $saved = $form->getData();
        self::assertInstanceOf(WarningPreferences::class, $saved);
        self::assertSame([Display::Hide, null], [$saved->category('phobias')?->display, $saved->category('phobias')?->atLeast]);
        self::assertNull($saved->theme('gore'));
    }

    public function testThePreferencesTransformerKeepsWhatItIsNotGiven(): void
    {
        $transformer = new WarningPreferencesTransformer(Catalogues::small(), Display::Hide);

        self::assertSame(Display::Hide, $transformer->transform(null)['default']);
        self::assertEquals(WarningPreferences::defaults(Display::Hide), $transformer->reverseTransform([]));
        self::assertEquals(WarningPreferences::defaults(Display::Hide), $transformer->reverseTransform(['default' => null]));
        self::assertEquals(WarningPreferences::defaults(Display::Hide)->withTheme('1', Display::Fold)->withCategory('2', Display::Show), $transformer->reverseTransform(['themes' => ['violence' => [1 => Display::Fold]], 'categories' => [2 => Display::Show]]));
    }

    /**
     * @param array<array-key, mixed> $choices
     *
     * @return list<string>
     */
    private function labels(array $choices): array
    {
        $labels = [];

        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->label);
            $labels[] = $choice->label;
        }

        return $labels;
    }

    private function forms(Display $default = Display::Fold): FormFactoryInterface
    {
        $labels = new CatalogueLabels(Catalogues::small());

        return Forms::createFormFactoryBuilder()
            ->addType(new ContentWarningsType($labels))
            ->addType(new WarningPreferencesType($labels, $default))
            ->getFormFactory();
    }
}
