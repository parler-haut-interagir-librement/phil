<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
use Phil\EnumsBundle\Infrastructure\Translation\TranslatableText;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\TicketKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(TranslatableText::class)]
#[CoversTrait(AsTranslatableEnumTrait::class)]
final class TranslationTest extends TestCase
{
    public function testACaseTranslatesItsLabelInItsDomainAndLocale(): void
    {
        $translator = $this->translator();

        self::assertSame('En transit', OrderStatus::IN_TRANSIT->trans($translator));
        self::assertSame('Unterwegs', OrderStatus::IN_TRANSIT->trans($translator, 'de'));
        self::assertSame('Rapport de bogue', TicketKind::BugReport->trans($translator));
    }

    public function testAnUntranslatedLabelShowsItsKey(): void
    {
        self::assertSame('Cancelled', OrderStatus::CANCELLED->trans($this->translator()));
    }

    public function testACaseTranslatesItsDescription(): void
    {
        $description = OrderStatus::PENDING->translatableDescription();

        self::assertInstanceOf(TranslatableText::class, $description);
        self::assertSame('Waiting for payment.', $description->key);
        self::assertSame('enums', $description->domain);
        self::assertSame('En attente du paiement.', $description->trans($this->translator()));
        self::assertSame('Wartet auf Zahlung.', $description->trans($this->translator(), 'de'));
    }

    public function testACaseWithoutDescriptionHasNone(): void
    {
        self::assertNull(OrderStatus::CANCELLED->translatableDescription());
    }

    private function translator(): Translator
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['In transit' => 'En transit', 'Waiting for payment.' => 'En attente du paiement.'], 'fr', 'enums');
        $translator->addResource('array', ['In transit' => 'Unterwegs', 'Waiting for payment.' => 'Wartet auf Zahlung.'], 'de', 'enums');
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Rapport de bogue'], 'fr', 'support');

        return $translator;
    }
}
