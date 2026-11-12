<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Twig;

use Override;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;

/**
 * The `importmap()` function of AssetMapper with the script nonce of the response on each tag it writes — the import
 * map, the module preloads, the polyfill and the entry points —, unless the template gives a `nonce` of its own. The
 * Stimulus controllers and Turbo load through it.
 */
final class NoncedImportMapRuntime extends ImportMapRuntime
{
    public function __construct(
        ImportMapRenderer $renderer,
        private readonly ContentSecurityPolicyContext $context,
    ) {
        parent::__construct($renderer);
    }

    /**
     * The runtime as the container builds it, under the service that declares the class of its parent.
     */
    public static function create(ImportMapRenderer $renderer, ContentSecurityPolicyContext $context): self
    {
        return new self($renderer, $context);
    }

    /**
     * @param string|array<array-key, mixed> $entryPoint
     * @param array<array-key, mixed>        $attributes
     */
    #[Override]
    public function importmap(string|array $entryPoint = 'app', array $attributes = []): string
    {
        return parent::importmap($entryPoint, $attributes + ['nonce' => $this->context->nonce(InlineKind::Script)]);
    }
}
