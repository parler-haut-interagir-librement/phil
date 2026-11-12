<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application\Exception;

use Phil\TranslationBundle\Domain\Exception\TranslationBundleException;
use RuntimeException;

/**
 * A segment, a document, a term or a comment the store does not hold.
 */
final class NotFound extends RuntimeException implements TranslationBundleException
{
    public static function segment(string $document, string $language, string $key): self
    {
        return new self(\sprintf('Document "%s" has no segment "%s" in "%s".', $document, $key, $language));
    }

    public static function document(string $document, string $language): self
    {
        return new self(\sprintf('Document "%s" has no segments in "%s": prepare it first.', $document, $language));
    }

    public static function term(string $term, string $sourceLanguage, string $targetLanguage): self
    {
        return new self(\sprintf('The glossary from "%s" to "%s" has no term "%s".', $sourceLanguage, $targetLanguage, $term));
    }

    public static function comment(int $id): self
    {
        return new self(\sprintf('There is no comment %d.', $id));
    }
}
