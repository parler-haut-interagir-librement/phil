<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\Twig;

use Phil\StructuredData\JsonLd;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider;
use Phil\StructuredDataBundle\Application\PageStructuredData;
use Twig\Attribute\AsTwigFunction;

/**
 * The graph of the page in templates: nodes added as the page is rendered, and the one script element that prints
 * them all, rendered once the content has added its nodes — at the end of the body.
 */
final readonly class StructuredDataExtension
{
    /**
     * @param iterable<ScriptNonceProvider> $nonces
     */
    public function __construct(
        private PageStructuredData $page,
        private iterable $nonces = [],
    ) {
    }

    /**
     * `<script type="application/ld+json">…</script>` with every node of the page, and the nonce given, else the one of
     * a `ScriptNonceProvider`; empty when the page has no node, or shows a record withdrawn from search engines.
     */
    #[AsTwigFunction(name: 'structured_data', isSafe: ['html'])]
    public function render(?string $nonce = null): string
    {
        if (!$this->page->isPrintable()) {
            return '';
        }

        $graph = $this->page->graph();

        return $graph->isEmpty() ? '' : JsonLd::script($graph, $nonce ?? $this->nonce());
    }

    /**
     * Adds nodes to the page: `Node` values, or schema.org data as other packages give it. Prints nothing.
     *
     * @param Node|array<array-key, mixed> ...$nodes
     */
    #[AsTwigFunction(name: 'structured_data_add')]
    public function add(Node|array ...$nodes): string
    {
        foreach ($nodes as $node) {
            $this->page->add($node instanceof Node ? $node : Node::fromArray($node));
        }

        return '';
    }

    /**
     * Names the records the page shows, whose owner may have withdrawn them from search engines. Prints nothing.
     */
    #[AsTwigFunction(name: 'structured_data_about')]
    public function about(object ...$records): string
    {
        $this->page->about(...array_values($records));

        return '';
    }

    private function nonce(): ?string
    {
        foreach ($this->nonces as $provider) {
            $nonce = $provider->scriptNonce();

            if (null !== $nonce) {
                return $nonce;
            }
        }

        return null;
    }
}
