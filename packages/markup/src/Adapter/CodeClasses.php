<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Override;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * The classes that survive purification: `language-…` on `code`, which names the language of a code block for a
 * highlighter in the browser, and `hl-…` on `span`, which a highlighter on the server writes. Every other class is
 * dropped, and an attribute left empty is removed.
 */
final readonly class CodeClasses implements AttributeSanitizerInterface
{
    #[Override]
    public function getSupportedElements(): array
    {
        return ['code', 'span'];
    }

    #[Override]
    public function getSupportedAttributes(): array
    {
        return ['class'];
    }

    #[Override]
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $pattern = 'code' === $element ? '/^language-[A-Za-z0-9_+#.-]{1,32}$/D' : '/^hl-[a-z-]{1,32}$/D';
        $classes = explode(' ', str_replace(["\t", "\n", "\r", "\f"], ' ', $value));
        $kept = implode(' ', array_filter($classes, static fn (string $class): bool => 1 === preg_match($pattern, $class)));

        return '' === $kept ? null : $kept;
    }
}
