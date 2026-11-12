<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Text;

/**
 * One translation kept in the memory: a source text in one language, its target in another, and where it came from —
 * a document, a file — when known. The fingerprint of the source finds exact matches; its plain text, scored, the
 * close ones.
 */
final readonly class MemoryUnit
{
    public Fingerprint $fingerprint;

    public string $plain;

    /**
     * @throws InvalidSegment when the source or the target has no words, or a text is not valid UTF-8
     */
    public function __construct(
        public LanguageTag $sourceLanguage,
        public LanguageTag $targetLanguage,
        public string $source,
        public string $target,
        public ?string $origin = null,
    ) {
        if (!mb_check_encoding([$source, $target, $origin ?? ''], 'UTF-8')) {
            throw InvalidSegment::because('The texts of a translation unit must be valid UTF-8.');
        }

        if (!Text::hasWords($source) || !Text::hasWords($target)) {
            throw InvalidSegment::because('A translation unit pairs a source and a target that both hold words.');
        }

        $this->fingerprint = Fingerprint::of($source);
        $this->plain = Text::plain($source);
    }

    /**
     * The length of the plain source in characters, which bounds the score it can reach against another text.
     */
    public function length(): int
    {
        return mb_strlen($this->plain);
    }
}
