<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Contact\SchemaOrg;
use Phil\Phone\PhoneNumber;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaOrg::class)]
final class SchemaOrgTest extends TestCase
{
    public function testAPersonWorksForItsOrganisation(): void
    {
        $card = ContactCard::of(
            'Ada Lovelace',
            'Analytical Engines',
            'Countess',
            [Email::of('ada@example.com')],
            [
                new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+15145550124')),
                new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+15145550123')),
                new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+15145550125 x3')),
            ],
            [TypedUrl::of(KnownUrlType::Website, 'https://example.com'), TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/ada')],
        );

        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => 'Ada Lovelace',
            'jobTitle' => 'Countess',
            'worksFor' => ['@type' => 'Organization', 'name' => 'Analytical Engines'],
            'email' => ['ada@example.com'],
            'telephone' => ['+15145550123', '+15145550125'],
            'faxNumber' => ['+15145550124'],
            'sameAs' => ['https://example.com/', 'https://facebook.com/ada'],
        ], SchemaOrg::of($card));
    }

    public function testAStructuredNameGivesItsParts(): void
    {
        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => 'Dr. Ada',
            'honorificPrefix' => 'Dr.',
            'givenName' => 'Ada',
            'additionalName' => ['Augusta'],
            'familyName' => 'de Lovelace King',
            'honorificSuffix' => 'II, PhD',
        ], SchemaOrg::of(ContactCard::of('Dr. Ada', structuredName: PersonName::of('Ada', 'Lovelace', 'de', ['Augusta'], 'King', 'Dr.', 'II', 'PhD'))));
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'Ada', 'givenName' => 'Ada', 'honorificSuffix' => 'PhD'], SchemaOrg::of(ContactCard::of(structuredName: PersonName::of('Ada', credential: 'PhD'))));
        self::assertSame('II', SchemaOrg::of(ContactCard::of(structuredName: PersonName::of('Ada', generation: 'II')))['honorificSuffix']);
    }

    public function testAnOrganisationHasNoJobTitleAndEmptyPropertiesAreLeftOut(): void
    {
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Analytical Engines'], SchemaOrg::of(ContactCard::of(organization: 'Analytical Engines', title: 'Shop')));
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'Ada'], SchemaOrg::of(ContactCard::of('Ada')));
    }
}
