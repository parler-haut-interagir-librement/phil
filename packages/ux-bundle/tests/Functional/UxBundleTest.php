<?php

declare(strict_types=1);

namespace Phil\UxBundle\Tests\Functional;

use Override;
use Phil\UxBundle\PhilUxBundle;
use Phil\UxBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Forms rendered through Symfony's Twig form renderer with the theme the bundle prepends, and the stylesheet of
 * phil/ux-css found by AssetMapper.
 */
#[CoversClass(PhilUxBundle::class)]
final class UxBundleTest extends KernelTestCase
{
    /**
     * @var array<string, mixed>
     */
    private static array $config = [];

    /**
     * @var list<string>
     */
    private static array $themes = [];

    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(FrameworkBundle::class)) {
            self::markTestSkipped('FrameworkBundle is installed for development only: no kernel can boot.');
        }

        self::$config = [];
        self::$themes = [];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(self::$config, self::$themes);
    }

    public function testARowIsAField(): void
    {
        self::bootKernel();

        self::assertSame(
            '<div class="isField" data-slot="field"><label class="isFieldLabel required" for="event_title">Title</label><input type="text" id="event_title" name="event[title]" required="required" /></div>',
            $this->render($this->form(), 'title'),
        );
    }

    public function testTheClassesOfTheApplicationAreKept(): void
    {
        self::bootKernel();

        self::assertSame(
            '<div class="isField wide" data-slot="field"><label class="isFieldLabel strong" for="event_place">Place</label><input type="text" id="event_place" name="event[place]" aria-describedby="event_place_help" /><div id="event_place_help" class="isFieldDescription muted" data-slot="field-description">Where it happens.</div></div>',
            $this->render($this->form(), 'place'),
        );
    }

    public function testAnErrorIsTiedToItsControl(): void
    {
        self::bootKernel();
        $form = $this->form();
        $form->get('title')->addError(new FormError('Too <short>.'));

        self::assertSame(
            '<div class="isField isDanger" data-slot="field" data-invalid="true"><label class="isFieldLabel required" for="event_title">Title</label><div class="isFieldError" data-slot="field-error"><p id="event_title_error1">Too &lt;short&gt;.</p></div><input type="text" id="event_title" name="event[title]" required="required" aria-describedby="event_title_error1" aria-invalid="true" /></div>',
            $this->render($form, 'title'),
        );
    }

    public function testSeveralErrorsAreAList(): void
    {
        self::bootKernel();
        $form = $this->form();
        $form->get('place')->addError(new FormError('Unknown.'));
        $form->get('place')->addError(new FormError('Closed.'));

        self::assertStringContainsString(
            '<div class="isFieldError" data-slot="field-error"><ul><li id="event_place_error1">Unknown.</li><li id="event_place_error2">Closed.</li></ul></div>',
            $this->render($form, 'place'),
        );
        self::assertStringContainsString('aria-describedby="event_place_help event_place_error1 event_place_error2" aria-invalid="true"', $this->render($form, 'place'));
    }

    public function testExpandedChoicesAreASetOfFields(): void
    {
        self::bootKernel();
        $form = $this->form();
        $form->get('format')->addError(new FormError('Choose one.'));

        self::assertSame(
            '<fieldset class="isFieldSet isDanger" data-slot="field-set" data-invalid="true"><legend class="isFieldLegend required">Format</legend><div class="isFieldError" data-slot="field-error"><p id="event_format_error1">Choose one.</p></div><div id="event_format" aria-describedby="event_format_error1" aria-invalid="true" class="isRadioGroup"><input type="radio" id="event_format_0" name="event[format]" required="required" value="online" /><label for="event_format_0" class="required">Online</label><input type="radio" id="event_format_1" name="event[format]" required="required" value="onsite" /><label for="event_format_1" class="required">On site</label></div></fieldset>',
            $this->render($form, 'format'),
        );
        self::assertStringContainsString('<div id="event_tags" class="tags"><input type="checkbox"', $this->render($form, 'tags'));
    }

    public function testTheErrorsOfTheFormComeFirst(): void
    {
        self::bootKernel();
        $form = $this->form();
        $form->addError(new FormError('Try again.'));

        self::assertStringStartsWith('<div id="event"><div class="isFieldError" data-slot="field-error"><p id="event_error1">Try again.</p></div>', $this->render($form));
    }

    public function testTheThemesOfTheApplicationWin(): void
    {
        self::$themes = ['application_theme.html.twig'];
        self::bootKernel();

        self::assertSame(
            '<div class="isField" data-slot="field"><label class="isFieldLabel strong" for="event_place">Place</label><input type="text" id="event_place" name="event[place]" aria-describedby="event_place_help" /><small id="event_place_help">Where it happens.</small></div>',
            str_replace(' wide', '', $this->render($this->form(), 'place')),
        );
    }

    public function testTheThemeCanBeTurnedOff(): void
    {
        self::$config = ['form_theme' => false];
        self::bootKernel();

        self::assertSame(
            '<div><label for="event_title" class="required">Title</label><input type="text" id="event_title" name="event[title]" required="required" /></div>',
            $this->render($this->form(), 'title'),
        );
    }

    public function testTheStylesheetIsAnAsset(): void
    {
        self::bootKernel();
        $mapper = self::getContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        $asset = $mapper->getAsset(PhilUxBundle::STYLESHEET);

        self::assertNotNull($asset);
        self::assertSame(realpath(PhilUxBundle::stylesheetDirectory().'/phil-ux.css'), realpath($asset->sourcePath));
    }

    /**
     * @return FormInterface<mixed>
     */
    private function form(): FormInterface
    {
        $factory = self::getContainer()->get('test.form_factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        return $factory->createNamedBuilder('event', FormType::class)
            ->add('title', TextType::class)
            ->add('place', TextType::class, ['required' => false, 'help' => 'Where it happens.', 'help_attr' => ['class' => 'muted'], 'row_attr' => ['class' => 'wide'], 'label_attr' => ['class' => 'strong']])
            ->add('format', ChoiceType::class, ['expanded' => true, 'choices' => ['Online' => 'online', 'On site' => 'onsite']])
            ->add('tags', ChoiceType::class, ['expanded' => true, 'multiple' => true, 'required' => false, 'choices' => ['Free' => 'free'], 'attr' => ['class' => 'tags']])
            ->getForm();
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function render(FormInterface $form, ?string $child = null): string
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $view = $form->createView();
        $template = null === $child ? '{{ form_widget(form) }}' : '{{ form_row(form[child]) }}';

        // The markup is compared without the whitespace next to tags, which the template is free to lay out.
        return trim((string) preg_replace(['/\s+/', '/> /', '/ </'], [' ', '>', '<'], $twig->createTemplate($template)->render(['form' => $view, 'child' => $child])));
    }
}
