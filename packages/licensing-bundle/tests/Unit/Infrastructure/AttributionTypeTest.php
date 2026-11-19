<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Infrastructure\Form\AttributionTransformer;
use Phil\LicensingBundle\Infrastructure\Form\AttributionType;
use Phil\LicensingBundle\Tests\Unit\Support\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

#[CoversClass(AttributionType::class)]
#[CoversClass(AttributionTransformer::class)]
final class AttributionTypeTest extends TestCase
{
    private const array FIELDS = [
        'kind' => 'image',
        'title' => 'Cover',
        'license' => LicenseCatalog::CC_BY,
        'author' => 'Jane Doe',
        'url' => 'https://example.com/cover',
        'notice' => '© 2024 Jane Doe',
        'changes' => 'Cropped',
    ];

    public function testAnAttributionIsSubmitted(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(self::FIELDS);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertEquals(Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, 'Jane Doe', 'https://example.com/cover', '© 2024 Jane Doe', 'Cropped'), $form->getData());
    }

    public function testCreativeCommonsAttributionAsksForTheChanges(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['changes' => ''] + self::FIELDS);

        self::assertFalse($form->isValid());
        self::assertSame(['The Creative Commons Attribution 4.0 International asks for the changes made to be indicated: describe them, or check “No modifications”.'], $this->messages($form->get('changes')));
        self::assertCount(0, $form->getErrors());
    }

    public function testTheChangesAreAskedInTheReadersLanguage(): void
    {
        $form = Forms::factory('fr')->create(AttributionType::class);
        $form->submit(['changes' => '   '] + self::FIELDS);

        self::assertSame(['La Licence Creative Commons Attribution 4.0 International demande d’indiquer les modifications apportées : décrivez-les, ou cochez « Aucune modification ».'], $this->messages($form->get('changes')));
    }

    public function testNoModificationsFillsTheChanges(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['changes' => '', 'unchanged' => '1'] + self::FIELDS);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('No changes were made.', $form->get('changes')->getData());
        self::assertSame('No changes were made.', $this->attribution($form)->changes);

        $form = Forms::factory('fr')->create(AttributionType::class);
        $form->submit(['changes' => 'Cropped', 'unchanged' => '1'] + self::FIELDS);
        self::assertSame('Aucune modification n’a été apportée.', $this->attribution($form)->changes);
    }

    public function testALicenseThatAsksNoChangesLeavesThemOut(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['license' => LicenseCatalog::CC0, 'changes' => ''] + self::FIELDS);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertNull($this->attribution($form)->changes);
    }

    public function testAResourceOutsideTheCatalogIsRefused(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['license' => 'house'] + self::FIELDS);

        self::assertFalse($form->isValid());
        self::assertNotSame([], $this->messages($form->get('license')));
    }

    public function testATitleAndAnAddressAreChecked(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['title' => '', 'url' => 'ftp://example.com/cover'] + self::FIELDS);

        self::assertFalse($form->isValid());
        self::assertNotSame([], $this->messages($form->get('title')));
        self::assertNotSame([], $this->messages($form->get('url')));
        self::assertSame([], $this->messages($form));
    }

    public function testTheFieldsShowAnAttribution(): void
    {
        $attribution = Attribution::of(ResourceKind::Map, 'Map', LicenseCatalog::CC_BY_SA, changes: 'No changes were made.');
        $form = Forms::factory()->create(AttributionType::class, $attribution);
        $view = $form->createView();

        self::assertSame(['kind', 'title', 'license', 'author', 'url', 'notice', 'changes', 'unchanged'], array_keys($view->children));
        self::assertTrue($form->get('unchanged')->getData());
        self::assertSame(ResourceKind::Map, $form->get('kind')->getData());
        $prefixes = $view->vars['block_prefixes'];
        self::assertIsArray($prefixes);
        self::assertSame('phil_licensing_attribution', $prefixes[1]);
        self::assertSame('phil_licensing', $view->vars['translation_domain']);
        self::assertSame(['%changes%' => 'No changes were made.'], $view->children['unchanged']->vars['help_translation_parameters']);
        $choices = $view->children['license']->vars['choices'];
        self::assertIsArray($choices);
        self::assertSame('Creative Commons Attribution-ShareAlike 4.0 International', $this->choiceLabel($choices, LicenseCatalog::CC_BY_SA));
        self::assertFalse(Forms::factory()->create(AttributionType::class, Attribution::of(ResourceKind::Map, 'Map', LicenseCatalog::CC_BY_SA, changes: 'Cropped'))->get('unchanged')->getData());
    }

    public function testEachFieldIsLabelledAndOptionalOrRequired(): void
    {
        $view = Forms::factory()->create(AttributionType::class)->createView();
        $fields = [];

        foreach ($view->children as $name => $field) {
            $fields[$name] = [$field->vars['label'], $field->vars['required'], $field->vars['help']];
        }

        self::assertSame([
            'kind' => ['form.attribution.kind', true, null],
            'title' => ['form.attribution.title', true, null],
            'license' => ['form.attribution.license', true, null],
            'author' => ['form.attribution.author', false, null],
            'url' => ['form.attribution.url', false, null],
            'notice' => ['form.attribution.notice', false, null],
            'changes' => ['form.attribution.changes', false, 'form.attribution.changes_help'],
            'unchanged' => ['form.attribution.unchanged', false, 'form.attribution.unchanged_help'],
        ], $fields);
        self::assertSame('form.attribution.license_placeholder', $view->children['license']->vars['placeholder']);
        self::assertFalse($view->children['license']->vars['choice_translation_domain']);
        self::assertSame(['form.kind.text', 'form.kind.image', 'form.kind.map', 'form.kind.font', 'form.kind.other'], array_map(static fn (ChoiceView $choice): string => \is_string($choice->label) ? $choice->label : '', $this->choices($view->children['kind']->vars['choices'])));
        $prefixes = $view->children['notice']->vars['block_prefixes'];
        self::assertIsArray($prefixes);
        self::assertContains('textarea', $prefixes);
        $prefixes = $view->children['changes']->vars['block_prefixes'];
        self::assertIsArray($prefixes);
        self::assertContains('textarea', $prefixes);
    }

    public function testAnAddressIsAbsoluteHttpWithATopLevelDomain(): void
    {
        foreach (['http://localhost/cover', 'example.com/cover', 'https://example.com/'.str_repeat('a', 2029), "https://example.com/a\x07b"] as $url) {
            $form = Forms::factory()->create(AttributionType::class);
            $form->submit(['url' => $url] + self::FIELDS);
            self::assertFalse($form->isValid(), $url);
            self::assertNotSame([], $this->messages($form->get('url')), $url);
        }

        foreach (['https://example.com/'.str_repeat('a', 2028), 'http://example.com/cover'] as $url) {
            $form = Forms::factory()->create(AttributionType::class);
            $form->submit(['url' => $url] + self::FIELDS);
            self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        }

        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['license' => ''] + self::FIELDS);
        self::assertFalse($form->isValid());
        self::assertNotSame([], $this->messages($form->get('license')));

        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['title' => "Co\x07ver", 'notice' => "a\x01b", 'changes' => "Crop\x1Fped"] + self::FIELDS);
        self::assertSame(['This text holds characters that are not allowed.'], $this->messages($form->get('title')));
        self::assertSame(['This text holds characters that are not allowed.'], $this->messages($form->get('notice')));
        self::assertSame(['This text holds characters that are not allowed.'], $this->messages($form->get('changes')));

        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['author' => str_repeat('a', 256), 'changes' => str_repeat('c', 1001)] + self::FIELDS);
        self::assertNotSame([], $this->messages($form->get('author')));
        self::assertNotSame([], $this->messages($form->get('changes')));
    }

    public function testTheFieldsOfAnAttribution(): void
    {
        $transformer = new AttributionTransformer('No changes were made.');

        self::assertSame(
            ['kind' => ResourceKind::Font, 'title' => 'Serif', 'license' => LicenseCatalog::CC_BY, 'author' => 'Jane', 'url' => 'https://example.com/serif', 'notice' => '© Jane', 'changes' => 'Subset', 'unchanged' => false],
            $transformer->transform(Attribution::of(ResourceKind::Font, 'Serif', LicenseCatalog::CC_BY, 'Jane', 'https://example.com/serif', '© Jane', 'Subset')),
        );
    }

    public function testAnEmptyFormIsNoAttribution(): void
    {
        $transformer = new AttributionTransformer('No changes were made.');

        self::assertSame(['kind' => ResourceKind::Text, 'title' => null, 'license' => null, 'author' => null, 'url' => null, 'notice' => null, 'changes' => null, 'unchanged' => false], $transformer->transform(null));
        $empty = $transformer->transform(null);
        self::assertNull($transformer->reverseTransform(null));
        self::assertNull($transformer->reverseTransform(['title' => ' ', 'license' => LicenseCatalog::CC0] + $empty));
        self::assertNull($transformer->reverseTransform(['title' => 'Cover', 'license' => ''] + $empty));
        self::assertNull($transformer->reverseTransform(['title' => 'Cover'] + $empty));
        self::assertNull($transformer->reverseTransform(['title' => 'Cover', 'license' => 'Not A Code'] + $empty));
        self::assertEquals(Attribution::of(ResourceKind::Other, 'Cover', LicenseCatalog::CC0), $transformer->reverseTransform(['title' => 'Cover', 'license' => LicenseCatalog::CC0, 'kind' => null, 'author' => ' '] + $empty));
        self::assertNull($transformer->reverseTransform(['title' => null, 'license' => LicenseCatalog::CC0] + $empty));
        self::assertNull($transformer->reverseTransform(['title' => 'Cover', 'license' => null] + $empty));
        self::assertEquals(Attribution::of(ResourceKind::Font, ' Cover ', LicenseCatalog::CC0, 'Jane', 'https://example.com', '© Jane', 'None'), $transformer->reverseTransform(['kind' => ResourceKind::Font, 'title' => ' Cover ', 'license' => LicenseCatalog::CC0, 'author' => 'Jane', 'url' => 'https://example.com', 'notice' => '© Jane', 'changes' => 'None', 'unchanged' => false]));

        $form = Forms::factory()->create(AttributionType::class);
        $form->submit([]);
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertNull($form->getData());
    }

    public function testTextsAreCheckedAsTheAttributionChecksThem(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['title' => str_repeat('a', 256), 'author' => "Ja\x07ne", 'notice' => str_repeat('n', 4001), 'changes' => " Cropped\t\n"] + self::FIELDS);

        self::assertFalse($form->isValid());
        self::assertTrue($form->isSynchronized());
        self::assertNotSame([], $this->messages($form->get('title')));
        self::assertSame(['This text holds characters that are not allowed.'], $this->messages($form->get('author')));
        self::assertNotSame([], $this->messages($form->get('notice')));
        self::assertSame([], $this->messages($form->get('changes')));
        self::assertSame([], $this->messages($form));

        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['title' => ' '.str_repeat('a', 255).' '] + self::FIELDS);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testATitleThatIsNotUtf8IsRefused(): void
    {
        $form = Forms::factory()->create(AttributionType::class);
        $form->submit(['title' => "Cover\xC3"] + self::FIELDS);

        self::assertFalse($form->isValid());
        self::assertSame(['This value does not match the expected UTF-8 charset.'], $this->messages($form->get('title')));
        self::assertSame([], $this->messages($form));
    }

    /**
     * @template T
     *
     * @param FormInterface<T> $form
     *
     * @return list<string>
     */
    private function messages(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors() as $error) {
            self::assertInstanceOf(FormError::class, $error);
            $messages[] = $error->getMessage();
        }

        return $messages;
    }

    /**
     * @template T
     *
     * @param FormInterface<T> $form
     */
    private function attribution(FormInterface $form): Attribution
    {
        $attribution = $form->getData();
        self::assertInstanceOf(Attribution::class, $attribution);

        return $attribution;
    }

    /**
     * @return list<ChoiceView>
     */
    private function choices(mixed $choices): array
    {
        self::assertIsArray($choices);

        return array_values(array_filter($choices, static fn (mixed $choice): bool => $choice instanceof ChoiceView));
    }

    /**
     * @param array<array-key, mixed> $choices
     */
    private function choiceLabel(array $choices, string $value): ?string
    {
        foreach ($choices as $choice) {
            if (\is_object($choice) && property_exists($choice, 'value') && $value === $choice->value && property_exists($choice, 'label') && \is_string($choice->label)) {
                return $choice->label;
            }
        }

        return null;
    }
}
