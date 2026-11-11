<?php

declare(strict_types=1);

namespace Phil\Typography;

use Dom\HTMLDocument;
use Dom\Node;
use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Fixer\Fixer;
use Phil\Typography\Html\Blocks;
use Phil\Typography\Html\Serializer;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Text\Run;

/**
 * Typographic fixes applied to HTML or to plain text. The HTML is parsed as HTML5 by `Dom\HTMLDocument` and only its
 * text is rewritten, block by block, by each fixer in turn: elements, attributes and comments are written back as they
 * were — `<` and `>` in attribute values escaped, as the current HTML standard writes them —, and the text of a
 * protected element — `pre`, `code`, `kbd`, `script`, `style` and `textarea` by default — is never changed.
 */
final readonly class Typographer
{
    /**
     * The elements whose text is left as written by default.
     */
    public const array PROTECTED_TAGS = ['pre', 'code', 'kbd', 'script', 'style', 'textarea'];

    /**
     * @param list<Fixer>  $fixers    the fixers, in the order they run
     * @param list<string> $protected the names of the elements whose text is never changed
     */
    public function __construct(
        private array $fixers,
        private array $protected = self::PROTECTED_TAGS,
    ) {
    }

    /**
     * The typographer recommended for a locale (see `Preset`).
     *
     * @param list<string> $protected
     *
     * @throws InvalidLocale
     */
    public static function for(Locale|string $locale, bool $hyphenation = true, ?Dictionary $dictionary = null, array $protected = self::PROTECTED_TAGS): self
    {
        return new self(Preset::fixers($locale instanceof Locale ? $locale : Locale::parse($locale), $hyphenation, $dictionary), $protected);
    }

    /**
     * A fragment of HTML, as found in the body of a page, fixed.
     */
    public function fix(string $html): string
    {
        $document = HTMLDocument::createEmpty();
        $body = $document->createElement('body');
        $body->innerHTML = $html;
        $this->fixTree($body);

        return Serializer::inner($body);
    }

    /**
     * A whole HTML document fixed, its title included.
     */
    public function fixDocument(string $html): string
    {
        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR);
        $this->fixTree($document);

        return Serializer::document($document);
    }

    /**
     * Plain text fixed as one block; bytes that are not UTF-8 are first replaced by question marks.
     */
    public function fixText(string $text): string
    {
        return $this->fixRun(Run::of(mb_scrub($text, 'UTF-8')))->texts[0];
    }

    /**
     * The run given to each fixer in turn.
     */
    public function fixRun(Run $run): Run
    {
        foreach ($this->fixers as $fixer) {
            $run = $fixer->fix($run);
        }

        return $run;
    }

    private function fixTree(Node $root): void
    {
        Blocks::fix($root, $this->protected, $this->fixRun(...));
    }
}
