<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Check;

use Phil\CssSanitizer\Removal;
use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\Declaration;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;
use Phil\CssSanitizer\UrlKind;

/**
 * Checks the `src` descriptor of `@font-face`: each source a local font, or a URL the rewriter allows followed by its
 * `format()` and `tech()`; the sources refused are removed one by one.
 *
 * @internal
 */
final readonly class FontSources
{
    public function __construct(
        private Values $values,
        private Serializer $serializer,
    ) {
    }

    /**
     * The descriptor with the sources kept, or null when none is, and the removal of each source refused.
     *
     * @return array{?Declaration, list<Removal>}
     */
    public function check(Declaration $src): array
    {
        $sources = [];
        $removals = [];

        foreach (Parser::splitAtCommas($src->value) as $source) {
            $checked = $this->source($source, $src->line);

            if ($checked instanceof Removal) {
                $removals[] = $checked;
            } else {
                $sources[] = $checked;
            }
        }

        if ([] === $sources) {
            return [null, $removals];
        }

        $value = [];

        foreach ($sources as $index => $source) {
            if ($index > 0) {
                $value[] = new Token(TokenType::Comma, ',', $src->line);
                $value[] = new Token(TokenType::Whitespace, ' ', $src->line);
            }

            array_push($value, ...$source);
        }

        return [new Declaration('src', $value, false, $src->line), $removals];
    }

    /**
     * @param list<ComponentValue> $source
     *
     * @return list<ComponentValue>|Removal
     */
    private function source(array $source, int $line): array|Removal
    {
        $first = $source[0] ?? null;
        $rest = \array_slice($source, 1);

        if ($first instanceof FunctionValue && 'local' === $first->lowerName() && self::hasOnly($first->arguments, [TokenType::String, TokenType::Ident, TokenType::Whitespace]) && [] === $rest) {
            return $source;
        }

        $url = $first instanceof Token && TokenType::Url === $first->type ? $first->value : (null === $first ? null : Values::urlOf($first));
        $hints = array_all($rest, static fn (ComponentValue $value): bool => ($value instanceof Token && TokenType::Whitespace === $value->type)
            || ($value instanceof FunctionValue && \in_array($value->lowerName(), ['format', 'tech'], true) && self::hasOnly($value->arguments, [TokenType::String, TokenType::Ident, TokenType::Comma, TokenType::Whitespace])));

        if (null === $url || !$hints) {
            return new Removal(RemovalReason::FontSource, $this->serializer->componentValues($source), $line);
        }

        $token = $this->values->url($url, UrlKind::Font, $line);

        return $token instanceof Removal ? $token : [$token, ...$rest];
    }

    /**
     * @param list<ComponentValue> $values
     * @param list<TokenType>      $types
     */
    private static function hasOnly(array $values, array $types): bool
    {
        return [] !== Parser::trim($values) && array_all($values, static fn (ComponentValue $value): bool => $value instanceof Token && \in_array($value->type, $types, true));
    }
}
