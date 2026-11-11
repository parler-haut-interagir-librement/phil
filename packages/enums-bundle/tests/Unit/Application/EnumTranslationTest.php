<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Application;

use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Application\Translation\Translatable;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use Phil\EnumsBundle\Tests\Unit\Fixture\TicketKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(EnumTranslation::class)]
#[CoversClass(Translatable::class)]
final class EnumTranslationTest extends TestCase
{
    public function testTheLabelIsTheKey(): void
    {
        self::assertSame('In transit', EnumTranslation::labelKey(OrderStatus::IN_TRANSIT));
    }

    public function testWithoutLabelTheKeyIsGeneratedInSnakeCase(): void
    {
        self::assertSame('enum.order_status.refunded', EnumTranslation::labelKey(OrderStatus::REFUNDED));
        self::assertSame('enum.shape.big_square', EnumTranslation::labelKey(Shape::BigSquare));
        self::assertSame('ticket.ticket_kind.bug_report', EnumTranslation::labelKey(TicketKind::BugReport));
        self::assertSame('ticket.ticket_kind.feature_request', EnumTranslation::labelKey(TicketKind::FEATURE_REQUEST));
        self::assertSame('enum.shape.blank', EnumTranslation::labelKey(Shape::Blank));
        self::assertSame('enum.shape.éclair', EnumTranslation::labelKey(Shape::Éclair));
    }

    public function testThePlainLabelIsTheLabelOrTheName(): void
    {
        self::assertSame('Round', EnumTranslation::plainLabel(Shape::Circle));
        self::assertSame('BigSquare', EnumTranslation::plainLabel(Shape::BigSquare));
        self::assertSame('Blank', EnumTranslation::plainLabel(Shape::Blank));
    }

    public function testTheDescriptionIsItsKey(): void
    {
        self::assertSame('Waiting for payment.', EnumTranslation::descriptionKey(OrderStatus::PENDING));
        self::assertNull(EnumTranslation::descriptionKey(OrderStatus::CANCELLED));
        self::assertNull(EnumTranslation::descriptionKey(Shape::Blank));
    }

    public function testTheDomainIsTheAttributesOrTheDefault(): void
    {
        self::assertSame('enums', EnumTranslation::domain(OrderStatus::PENDING));
        self::assertSame('support', EnumTranslation::domain(TicketKind::BugReport));
        self::assertSame('enums', Translatable::DEFAULT_DOMAIN);
        self::assertSame('enum', Translatable::DEFAULT_PREFIX);
    }

    /**
     * The `Translatable` attribute is read once per enum and kept: every later lookup reuses the same settings.
     */
    public function testTheSettingsOfAnEnumAreReadOnce(): void
    {
        $cache = new ReflectionProperty(EnumTranslation::class, 'settings');
        $cache->setValue(null, []);

        EnumTranslation::domain(TicketKind::BugReport);
        $kept = $cache->getValue();
        self::assertIsArray($kept);
        self::assertArrayHasKey(TicketKind::class, $kept);
        $first = $kept[TicketKind::class];

        self::assertSame('ticket.ticket_kind.feature_request', EnumTranslation::labelKey(TicketKind::FEATURE_REQUEST));
        $again = $cache->getValue();
        self::assertIsArray($again);
        self::assertSame($first, $again[TicketKind::class]);
        self::assertCount(1, $again);
    }

    /**
     * The generated key of a case is built once and kept: a later lookup returns the key kept, without rebuilding it.
     */
    public function testTheGeneratedKeyOfACaseIsBuiltOnce(): void
    {
        $cache = new ReflectionProperty(EnumTranslation::class, 'generatedKeys');
        $cache->setValue(null, []);

        self::assertSame('enum.shape.big_square', EnumTranslation::labelKey(Shape::BigSquare));
        self::assertSame([Shape::class => ['BigSquare' => 'enum.shape.big_square']], $cache->getValue());

        $cache->setValue(null, [Shape::class => ['BigSquare' => 'kept']]);
        self::assertSame('kept', EnumTranslation::labelKey(Shape::BigSquare));
        self::assertSame('enum.shape.blank', EnumTranslation::labelKey(Shape::Blank));
        self::assertSame('In transit', EnumTranslation::labelKey(OrderStatus::IN_TRANSIT), 'a label is never kept as a generated key');
        self::assertSame([Shape::class => ['BigSquare' => 'kept', 'Blank' => 'enum.shape.blank']], $cache->getValue());

        $cache->setValue(null, []);
    }
}
