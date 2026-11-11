<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Infrastructure\Twig;

use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * UJO in templates: `{{ page.body|ujo_html }}` or `{{ ujo_html(page.body) }}` renders a document or its JSON as HTML,
 * checked and purified; `{{ page.body|ujo_text }}` gives its plain text. `null` renders nothing; a document that is
 * not valid is an error, as in the rest of the application.
 */
final readonly class UjoExtension
{
    public function __construct(
        private Ujo $ujo,
    ) {
    }

    /**
     * @throws UjoException
     */
    #[AsTwigFilter(name: 'ujo_html', isSafe: ['html'])]
    #[AsTwigFunction(name: 'ujo_html', isSafe: ['html'])]
    public function html(Document|string|null $document): string
    {
        return null === $document ? '' : $this->ujo->toHtml($document);
    }

    /**
     * @throws UjoException
     */
    #[AsTwigFilter(name: 'ujo_text')]
    #[AsTwigFunction(name: 'ujo_text')]
    public function text(Document|string|null $document): string
    {
        return null === $document ? '' : $this->ujo->toText($document);
    }
}
