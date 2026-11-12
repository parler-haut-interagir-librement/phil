<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Twig;

use InvalidArgumentException;
use Phil\Contact\ContactCard;
use Phil\Contact\PersonName;
use Phil\Contact\SchemaOrg;
use Phil\Contact\VCard;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Node;
use Twig\Attribute\AsTwigFunction;

/**
 * Cards in templates: as vCard, as schema.org JSON-LD — a node of the page's graph when phil/structured-data
 * gathers one, its own script element otherwise —, and person names as their culture writes and sorts them.
 */
final readonly class ContactExtension
{
    public function __construct(
        private ?NodeCollector $graph = null,
    ) {
    }

    #[AsTwigFunction(name: 'contact_vcard')]
    public function vCard(?ContactCard $card): string
    {
        return $card instanceof ContactCard ? VCard::of($card) : '';
    }

    /**
     * A person name, or the name of a card, in a format: `display`, as it is written every day; `formal`, with its
     * honorific and credential; `sort`, the key a list sorts it by. A card without a structured name gives its name in
     * every format; no name gives an empty text.
     *
     * ```twig
     * {{ person_name(card) }} · {{ person_name(card.structuredName, 'formal') }}
     * ```
     */
    #[AsTwigFunction(name: 'person_name')]
    public function personName(PersonName|ContactCard|null $name, string $format = 'display'): string
    {
        $person = $name instanceof ContactCard ? $name->structuredName : $name;

        if (!\in_array($format, ['display', 'formal', 'sort'], true)) {
            throw new InvalidArgumentException(\sprintf('"%s" is no format of person_name(): display, formal or sort.', $format));
        }

        if (!$person instanceof PersonName) {
            return $name instanceof ContactCard ? $name->displayName() ?? '' : '';
        }

        return match ($format) {
            'display' => $person->display(),
            'formal' => $person->formal(),
            'sort' => $person->sortKey(),
        };
    }

    /**
     * The card's node added to the graph of the page, which prints it with the others, and nothing printed here; without
     * a graph, `<script type="application/ld+json">…</script>`, its JSON unable to close the element. Empty for no card.
     */
    #[AsTwigFunction(name: 'contact_json_ld', isSafe: ['html'])]
    public function jsonLd(?ContactCard $card): string
    {
        if (!$card instanceof ContactCard) {
            return '';
        }

        if ($this->graph instanceof NodeCollector) {
            $this->graph->add(Node::fromArray(SchemaOrg::of($card)));

            return '';
        }

        return '<script type="application/ld+json">'.json_encode(SchemaOrg::of($card), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP).'</script>';
    }
}
