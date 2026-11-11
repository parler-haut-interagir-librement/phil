<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\EnumsBundle\Infrastructure\Translation\EnumTranslationExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;

#[CoversClass(EnumTranslationExtractor::class)]
final class EnumTranslationExtractorTest extends TestCase
{
    public function testItGivesTheLabelAndDescriptionKeysOfTheTranslatableEnumsInTheirDomains(): void
    {
        $catalogue = new MessageCatalogue('fr');
        $extractor = new EnumTranslationExtractor();
        $extractor->setPrefix('__');
        $extractor->extract(__DIR__.'/../Fixture', $catalogue);

        self::assertSame(['enums', 'support'], $catalogue->getDomains());
        self::assertSame([
            'Pending' => '__Pending',
            'Waiting for payment.' => '__Waiting for payment.',
            'In transit' => '__In transit',
            'The parcel has left.' => '__The parcel has left.',
            'Cancelled' => '__Cancelled',
            'enum.order_status.refunded' => '__enum.order_status.refunded',
        ], $catalogue->all('enums'));
        self::assertSame([
            'ticket.ticket_kind.bug_report' => '__ticket.ticket_kind.bug_report',
            'ticket.ticket_kind.feature_request' => '__ticket.ticket_kind.feature_request',
        ], $catalogue->all('support'));
    }

    public function testWithoutPrefixTheMessageIsTheKey(): void
    {
        $catalogue = new MessageCatalogue('fr');
        new EnumTranslationExtractor()->extract([__DIR__.'/../Fixture/TicketKind.php'], $catalogue);

        self::assertSame('ticket.ticket_kind.bug_report', $catalogue->get('ticket.ticket_kind.bug_report', 'support'));
    }

    public function testEnumsThatAreNotTranslatableGiveNothing(): void
    {
        $catalogue = new MessageCatalogue('fr');
        new EnumTranslationExtractor()->extract([__DIR__.'/../Fixture/Shape.php', __DIR__.'/../Fixture/Access.php'], $catalogue);

        self::assertSame([], $catalogue->all());
    }
}
