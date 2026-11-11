<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\Enums\Attribute\Label;
use Phil\EnumsBundle\Infrastructure\Twig\EnumsExtension;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Priority;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader as TemplateLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use UnitEnum;

#[CoversClass(EnumsExtension::class)]
final class EnumsExtensionTest extends TestCase
{
    public function testTheCasesAreNarrowedAsInAForm(): void
    {
        $twig = new EnumsExtension();

        self::assertSame(OrderStatus::cases(), $twig->cases(OrderStatus::class)->all());
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT], $twig->cases(OrderStatus::class, ['group' => 'customer'])->all());
        self::assertSame([OrderStatus::PENDING, OrderStatus::CANCELLED], $twig->cases(OrderStatus::class, ['group' => ['staff']])->all());
        self::assertSame([OrderStatus::IN_TRANSIT], $twig->cases(OrderStatus::class, ['only' => [OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], 'except' => [OrderStatus::CANCELLED]])->all());
        self::assertSame([OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], $twig->cases(OrderStatus::class, ['where_meta' => [Priority::class => 2]])->all());
    }

    public function testAMetaValueIsRead(): void
    {
        self::assertSame(2, new EnumsExtension()->meta(OrderStatus::CANCELLED, Priority::class));
        self::assertSame('Round', new EnumsExtension()->meta(Shape::Circle, Label::class));
    }

    public function testALabelIsTranslatedWhenTheEnumIsTranslatable(): void
    {
        $twig = new EnumsExtension($this->translator());

        self::assertSame('En transit', $twig->label(OrderStatus::IN_TRANSIT));
        self::assertSame('Round', $twig->label(Shape::Circle));
        self::assertSame('BigSquare', $twig->label(Shape::BigSquare));
    }

    public function testWithoutTranslatorALabelIsShownAsWritten(): void
    {
        self::assertSame('In transit', new EnumsExtension()->label(OrderStatus::IN_TRANSIT));
        self::assertSame('REFUNDED', new EnumsExtension()->label(OrderStatus::REFUNDED));
    }

    public function testADescriptionIsTranslatedWhenTheEnumIsTranslatable(): void
    {
        $twig = new EnumsExtension($this->translator());

        self::assertSame('En attente du paiement.', $twig->description(OrderStatus::PENDING));
        self::assertNull($twig->description(OrderStatus::CANCELLED));
        self::assertNull($twig->description(Shape::Circle));
    }

    public function testWithoutTranslatorADescriptionIsShownAsWritten(): void
    {
        self::assertSame('Waiting for payment.', new EnumsExtension()->description(OrderStatus::PENDING));
    }

    public function testTheFunctionsAndFiltersWorkInATemplate(): void
    {
        $twig = new Environment(new TemplateLoader([
            'page' => "{% for status in enum_cases(enum, {group: 'customer'}) %}{{ status|enum_label }}|{{ status|enum_description }}|{{ status|enum_meta(priority) }};{% endfor %}",
        ]));
        $twig->addExtension(new AttributeExtension(EnumsExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([EnumsExtension::class => fn (): EnumsExtension => new EnumsExtension($this->translator())]));

        self::assertSame(
            'Pending|En attente du paiement.|1;En transit|The parcel has left.|2;',
            $twig->render('page', ['enum' => OrderStatus::class, 'priority' => Priority::class]),
        );
    }

    public function testABadgeShowsTheLabelColourAndIcon(): void
    {
        self::assertSame(
            '<span class="enum-badge enum-badge--green" data-enum-case="Read"><span class="enum-badge__icon" data-icon="eye" aria-hidden="true"></span>Read</span>',
            $this->badge(Access::Read),
        );
    }

    public function testABadgeWithoutColourOrIconIsItsTranslatedLabelEscaped(): void
    {
        self::assertSame('<span class="enum-badge" data-enum-case="IN_TRANSIT">En transit</span>', $this->badge(OrderStatus::IN_TRANSIT));
        self::assertSame('<span class="enum-badge" data-enum-case="IN_TRANSIT">&lt;b&gt;En transit&lt;/b&gt;</span>', $this->badge(OrderStatus::IN_TRANSIT, '<b>En transit</b>'));
    }

    /**
     * The badge rendered through the filter, its whitespace collapsed; `$label` replaces the translation of « In transit ».
     */
    private function badge(UnitEnum $case, ?string $label = null): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__.'/../../../templates', 'PhilEnums');

        $twig = new Environment(new ChainLoader([new TemplateLoader(['page' => '{{ case|enum_badge }}']), $loader]));
        $twig->addExtension(new AttributeExtension(EnumsExtension::class));

        $translator = $this->translator();

        if (null !== $label) {
            $translator->addResource('array', ['In transit' => $label], 'fr', 'enums');
        }

        $twig->addRuntimeLoader(new FactoryRuntimeLoader([EnumsExtension::class => static fn (): EnumsExtension => new EnumsExtension($translator)]));
        $html = $twig->render('page', ['case' => $case]);

        return trim((string) preg_replace('/>\s+</', '><', (string) preg_replace('/\s+/', ' ', $html)));
    }

    private function translator(): Translator
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['In transit' => 'En transit', 'Waiting for payment.' => 'En attente du paiement.'], 'fr', 'enums');

        return $translator;
    }
}
