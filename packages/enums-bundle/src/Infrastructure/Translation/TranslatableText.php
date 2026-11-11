<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Translation;

use Override;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A key in a domain, translated when it is rendered: what a case's description is handed to Twig's `|trans` or to
 * a form as.
 */
final readonly class TranslatableText implements TranslatableInterface
{
    public function __construct(
        public string $key,
        public string $domain,
    ) {
    }

    #[Override]
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->key, [], $this->domain, $locale);
    }
}
