<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use Phil\Contact\ContactCard;
use Phil\Contact\PersonName;
use Phil\ContactBundle\Infrastructure\Twig\ContactExtension;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContactExtension::class)]
final class ContactExtensionTest extends TestCase
{
    public function testACardIsAVCardAndJsonLd(): void
    {
        $extension = new ContactExtension();
        $card = ContactCard::of('Ada </script> & "Zoé"/');

        self::assertStringStartsWith("BEGIN:VCARD\r\n", $extension->vCard($card));
        self::assertSame('', $extension->vCard(null));
        self::assertSame('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Person","name":"Ada \u003C/script\u003E \u0026 \"Zoé\"/"}</script>', $extension->jsonLd($card));
        self::assertSame('', $extension->jsonLd(null));
    }

    public function testAPersonNameInEachFormat(): void
    {
        $extension = new ContactExtension();
        $name = PersonName::of('Charles', 'Gaulle', 'de', honorific: 'Gén.', credential: 'PhD', culture: 'fr');
        $card = ContactCard::of(structuredName: $name);

        self::assertSame(['Charles de Gaulle', 'Gén. Charles de Gaulle, PhD', 'Gaulle, Charles de'], [$extension->personName($name), $extension->personName($name, 'formal'), $extension->personName($card, 'sort')]);
        self::assertSame(['Ada', 'Engines', '', ''], [$extension->personName(ContactCard::of('Ada'), 'sort'), $extension->personName(ContactCard::of(organization: 'Engines')), $extension->personName(ContactCard::of(title: 'Countess')), $extension->personName(null, 'formal')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('"short" is no format of person_name(): display, formal or sort.');
        $extension->personName(null, 'short');
    }

    public function testWithAGraphTheCardIsANodeOfThePage(): void
    {
        $graph = new class implements NodeCollector {
            /** @var list<Node> */
            public array $nodes = [];

            public function add(Node ...$nodes): void
            {
                foreach ($nodes as $node) {
                    $this->nodes[] = $node;
                }
            }
        };
        $extension = new ContactExtension($graph);

        self::assertSame('', $extension->jsonLd(ContactCard::of('Ada')));
        self::assertSame('', $extension->jsonLd(null));
        self::assertSame([['@type' => 'Person', 'name' => 'Ada']], array_map(static fn (Node $node): array => $node->toArray(), $graph->nodes));
    }
}
