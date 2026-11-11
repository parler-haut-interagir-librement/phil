<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Check;

use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Removal;
use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;
use Phil\CssSanitizer\UrlKind;

/**
 * Checks the value of a declaration, and rewrites its URLs.
 *
 * A value may hold names, numbers, dimensions, percentages, strings, hashes, commas and the delims of arithmetic and
 * of shorthands (`/ + - *`), parentheses and brackets, and the functions the policy allows, their arguments checked
 * the same way. `url()` goes through the policy's rewriter, and so does each image of `image-set()`, whose options
 * are an image — a string or `url()` rewritten, or a function the policy allows — with at most one resolution and one
 * `type()`. `attr()` takes attribute names and types, and the type
 * `url` only as the first argument of `target-counter()`, `target-counters()` or `target-text()`, which point into the
 * document. Anything else — a colon, a semicolon, a `{}` block, an at-keyword, a bad string or URL — refuses the value.
 *
 * @internal
 */
final readonly class Values
{
    private const array TOKENS = [
        TokenType::Ident, TokenType::Number, TokenType::Percentage, TokenType::Dimension, TokenType::String,
        TokenType::Hash, TokenType::Whitespace, TokenType::Comma,
    ];

    private const array DELIMS = ['/', '+', '-', '*'];

    private const array TARGETS = ['target-counter', 'target-counters', 'target-text'];

    private const array IMAGE_SETS = ['image-set', '-webkit-image-set'];

    /** The units of a resolution in `image-set()`. */
    private const array RESOLUTIONS = ['x', 'dppx', 'dpi', 'dpcm'];

    /** The schemes whose URLs run code. */
    private const string DANGEROUS_SCHEME = '/^(?:javascript|vbscript|livescript|mocha):/';

    public function __construct(
        private Policy $policy,
        private Serializer $serializer = new Serializer(),
    ) {
    }

    /**
     * The value with its URLs rewritten, or why it is refused.
     *
     * @param list<ComponentValue> $values
     *
     * @return list<ComponentValue>|Removal
     */
    public function value(array $values, UrlKind $kind): array|Removal
    {
        $checked = [];

        foreach ($values as $value) {
            $result = $this->componentValue($value, $kind);

            if ($result instanceof Removal) {
                return $result;
            }

            $checked[] = $result;
        }

        return $checked;
    }

    /**
     * A URL rewritten by the policy's rewriter, as a URL token, or why it is refused. A fragment alone is kept as it
     * is.
     */
    public function url(string $url, UrlKind $kind, int $line): Token|Removal
    {
        if (str_starts_with($url, '#')) {
            return new Token(TokenType::Url, $url, $line);
        }

        if ($this->isDangerous($url)) {
            return new Removal(RemovalReason::DangerousUrl, $url, $line);
        }

        $rewritten = $this->policy->urlRewriter->rewrite($url, $kind);

        if (null === $rewritten) {
            return new Removal(RemovalReason::Url, $url, $line);
        }

        if ($this->isDangerous($rewritten)) {
            return new Removal(RemovalReason::DangerousUrl, $rewritten, $line);
        }

        return new Token(TokenType::Url, $rewritten, $line);
    }

    /** The URL of a `url()` function whose only argument is a string; null for any other function. */
    public static function urlOf(ComponentValue $value): ?string
    {
        if (!$value instanceof FunctionValue || 'url' !== $value->lowerName()) {
            return null;
        }

        $arguments = Parser::trim($value->arguments);

        return 1 === \count($arguments) && $arguments[0] instanceof Token && TokenType::String === $arguments[0]->type ? $arguments[0]->value : null;
    }

    private function componentValue(ComponentValue $value, UrlKind $kind): ComponentValue|Removal
    {
        if ($value instanceof Token) {
            return match (true) {
                TokenType::Url === $value->type => $this->url($value->value, $kind, $value->sourceLine),
                \in_array($value->type, self::TOKENS, true), TokenType::Delim === $value->type && \in_array($value->value, self::DELIMS, true) => $value,
                default => new Removal(RemovalReason::Value, $this->serializer->componentValues([$value]), $value->sourceLine),
            };
        }

        if ($value instanceof SimpleBlock) {
            if (TokenType::OpenCurly === $value->opening) {
                return new Removal(RemovalReason::Value, '{', $value->sourceLine);
            }

            $content = $this->value($value->value, $kind);

            return $content instanceof Removal ? $content : new SimpleBlock($value->opening, $content, $value->sourceLine);
        }

        return $this->function($value, $kind);
    }

    private function function(FunctionValue $function, UrlKind $kind): ComponentValue|Removal
    {
        $name = $function->lowerName();

        if ('url' === $name) {
            $url = self::urlOf($function);

            return null === $url
                ? new Removal(RemovalReason::Value, $this->serializer->componentValues([$function]), $function->sourceLine)
                : $this->url($url, $kind, $function->sourceLine);
        }

        if (\in_array($name, self::IMAGE_SETS, true)) {
            return $this->imageSet($function, $kind);
        }

        if ('var' === $name ? !$this->policy->customProperties : !isset($this->policy->functions[$name])) {
            return new Removal(RemovalReason::Function, $function->name.'()', $function->sourceLine);
        }

        if ('attr' === $name) {
            return $this->attr($function, false);
        }

        $arguments = Parser::trim($function->arguments);
        $first = $arguments[0] ?? null;

        if (\in_array($name, self::TARGETS, true) && $first instanceof FunctionValue && 'attr' === $first->lowerName()) {
            $attr = $this->attr($first, true);
            $rest = $this->value(\array_slice($arguments, 1), $kind);

            return match (true) {
                $attr instanceof Removal => $attr,
                $rest instanceof Removal => $rest,
                default => new FunctionValue($function->name, [$attr, ...$rest], $function->sourceLine),
            };
        }

        $checked = $this->value($arguments, $kind);

        return $checked instanceof Removal ? $checked : new FunctionValue($function->name, $checked, $function->sourceLine);
    }

    /**
     * `image-set()`: each option an image and its hints, its URLs rewritten; one option refused refuses the value.
     */
    private function imageSet(FunctionValue $function, UrlKind $kind): FunctionValue|Removal
    {
        $arguments = [];

        foreach (Parser::splitAtCommas($function->arguments) as $index => $option) {
            $checked = $this->imageSetOption($option, $kind, $function);

            if ($checked instanceof Removal) {
                return $checked;
            }

            if ($index > 0) {
                $arguments[] = new Token(TokenType::Comma, ',', $function->sourceLine);
                $arguments[] = new Token(TokenType::Whitespace, ' ', $function->sourceLine);
            }

            array_push($arguments, ...$checked);
        }

        return new FunctionValue($function->name, $arguments, $function->sourceLine);
    }

    /**
     * An option of `image-set()`: a string or `url()` the rewriter allows, or a function the policy allows, followed
     * by at most one resolution and at most one `type()` holding a string.
     *
     * @param list<ComponentValue> $option
     *
     * @return list<ComponentValue>|Removal
     */
    private function imageSetOption(array $option, UrlKind $kind, FunctionValue $function): array|Removal
    {
        $first = $option[0] ?? null;
        $hints = \array_slice($option, 1);
        $resolutions = array_filter($hints, static fn (ComponentValue $hint): bool => $hint instanceof Token
            && TokenType::Dimension === $hint->type && \in_array(strtolower($hint->unit), self::RESOLUTIONS, true));
        $types = array_filter($hints, static fn (ComponentValue $hint): bool => $hint instanceof FunctionValue
            && 'type' === $hint->lowerName() && self::isString(Parser::trim($hint->arguments)));
        $spaces = array_filter($hints, static fn (ComponentValue $hint): bool => $hint instanceof Token && TokenType::Whitespace === $hint->type);

        $isImage = $first instanceof FunctionValue || ($first instanceof Token && \in_array($first->type, [TokenType::String, TokenType::Url], true));

        if (!$isImage || \count($resolutions) > 1 || \count($types) > 1
            || \count($resolutions) + \count($types) + \count($spaces) !== \count($hints)) {
            return new Removal(RemovalReason::Value, $this->serializer->componentValues([$function]), $function->sourceLine);
        }

        $image = $first instanceof Token && TokenType::String === $first->type
            ? $this->url($first->value, $kind, $first->sourceLine)
            : $this->componentValue($first, $kind);

        return $image instanceof Removal ? $image : [$image, ...$hints];
    }

    /**
     * @param list<ComponentValue> $values
     */
    private static function isString(array $values): bool
    {
        return 1 === \count($values) && $values[0] instanceof Token && TokenType::String === $values[0]->type;
    }

    /**
     * `attr()`: an attribute name and type names, the type `url` only where the caller allows it.
     */
    private function attr(FunctionValue $attr, bool $url): FunctionValue|Removal
    {
        $arguments = Parser::trim($attr->arguments);
        $allowed = isset($this->policy->functions['attr']) && [] !== $arguments && array_all(
            $arguments,
            static fn (ComponentValue $value, int $index): bool => $value instanceof Token
                && (TokenType::Whitespace === $value->type || (TokenType::Ident === $value->type && (0 === $index || $url || !$value->isIdent('url')))),
        );

        return $allowed ? $attr : new Removal(RemovalReason::Function, $attr->name.'()', $attr->sourceLine);
    }

    private function isDangerous(string $url): bool
    {
        $visible = array_filter(str_split($url), static fn (string $c): bool => \ord($c[0]) > 0x20 && 0x7F !== \ord($c[0]));

        return 1 === preg_match(self::DANGEROUS_SCHEME, strtolower(implode('', $visible)));
    }
}
