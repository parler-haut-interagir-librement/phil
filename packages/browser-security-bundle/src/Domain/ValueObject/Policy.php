<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\ValueObject;

use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;

/**
 * A content security policy: directives in the order they were declared, each with its checked values, and the header
 * value written once when the policy is built.
 *
 * A source directive given no source is `'none'`, and `'none'` stands alone. A directive added to a policy that does
 * not declare it starts from `default-src` when it falls back on it, so that adding a CDN to `img-src` never opens
 * what `default-src` closed. The nonces and hashes of a request are added when the header is written, without building
 * another policy: {@see self::header()}.
 */
final readonly class Policy
{
    /** @var array<string, list<string>> */
    private array $directives;

    /** @var array<string, string> */
    private array $parts;

    private string $header;

    /**
     * @param array<array-key, list<string>|bool> $directives values by directive name; `true` declares a directive
     *                                                        without values, `false` leaves it out
     *
     * @throws InvalidPolicy when a directive is unknown or a value is not one its directive takes
     */
    public function __construct(array $directives = [])
    {
        $normalized = [];
        $parts = [];

        foreach ($directives as $name => $values) {
            $directive = $this->directive((string) $name);

            if (false === $values) {
                continue;
            }

            $values = $this->values($directive, true === $values ? [] : $values);
            $normalized[$directive->value] = $values;
            $parts[$directive->value] = implode(' ', [$directive->value, ...$values]);
        }

        $this->directives = $normalized;
        $this->parts = $parts;
        $this->header = implode('; ', $parts);
    }

    /**
     * The values of a directive, or `null` when the policy does not declare it.
     *
     * @return list<string>|null
     */
    public function sources(Directive $directive): ?array
    {
        return $this->directives[$directive->value] ?? null;
    }

    /**
     * @return array<string, list<string>> values by directive name, in declaration order
     */
    public function directives(): array
    {
        return $this->directives;
    }

    /**
     * The policy with values added to a directive: `'none'` gives way to them, and a directive the policy does not
     * declare starts from `default-src` when it falls back on it.
     *
     * @param list<string> $values
     *
     * @throws InvalidPolicy
     */
    public function withAdded(Directive|string $directive, array $values): self
    {
        $directive = $this->directive($directive);
        $current = $this->directives[$directive->value] ?? $this->fallback($directive) ?? [];
        $directives = $this->directives;
        $directives[$directive->value] = $this->values($directive, [...array_diff($current, [SourceSyntax::NONE]), ...$values]);

        return $this->with($directives);
    }

    /**
     * The policy with the values of a directive replaced, the directive declared at its place or last.
     *
     * @param list<string> $values
     *
     * @throws InvalidPolicy
     */
    public function withReplaced(Directive|string $directive, array $values): self
    {
        $directive = $this->directive($directive);
        $directives = $this->directives;
        $directives[$directive->value] = $this->values($directive, $values);

        return $this->with($directives);
    }

    /**
     * The policy without a directive.
     *
     * @throws InvalidPolicy when the directive is unknown
     */
    public function without(Directive|string $directive): self
    {
        $directives = $this->directives;
        unset($directives[$this->directive($directive)->value]);

        return $this->with($directives);
    }

    /**
     * This policy with the directives of another one replacing its own: a named policy that extends this one.
     */
    public function extendedBy(self $child): self
    {
        return $this->with(array_replace($this->directives, $child->directives));
    }

    /**
     * A copy with other directives, already checked: only the directive that changed is checked again, so that an
     * override costs a few microseconds.
     *
     * @param array<string, list<string>> $directives
     */
    private function with(array $directives): self
    {
        $parts = [];

        foreach ($directives as $name => $values) {
            $parts[$name] = implode(' ', [$name, ...$values]);
        }

        return clone ($this, ['directives' => $directives, 'parts' => $parts, 'header' => implode('; ', $parts)]);
    }

    /**
     * The header value, with the tokens of a request added and a suffix — the reporting directives — appended.
     *
     * A token goes after the values of its directive, or of `default-src` for a fetch directive the policy does not
     * declare; a directive that is `'none'`, or that nothing restricts, is left as it is, so that a nonce never opens
     * what the policy closed nor restricts what it left open.
     *
     * @param array<string, list<string>> $tokens tokens by directive name: nonces, hashes, `'strict-dynamic'`
     */
    public function header(array $tokens = [], string $suffix = ''): string
    {
        if ([] === $tokens) {
            $header = $this->header;
        } else {
            $parts = $this->parts;

            foreach ($tokens as $name => $added) {
                $base = $this->directives[$name] ?? $this->fallback(Directive::from($name));

                if (null !== $base && [SourceSyntax::NONE] !== $base) {
                    $parts[$name] = implode(' ', [$name, ...array_unique([...$base, ...$added])]);
                }
            }

            $header = implode('; ', $parts);
        }

        return '' === $suffix || '' === $header ? $header : $header.'; '.$suffix;
    }

    /**
     * @return list<string>|null
     */
    private function fallback(Directive $directive): ?array
    {
        return $directive->fallsBackOnDefault() ? $this->directives[Directive::DefaultSrc->value] ?? null : null;
    }

    private function directive(Directive|string $name): Directive
    {
        return $name instanceof Directive ? $name : Directive::tryFrom($name) ?? throw new InvalidPolicy(\sprintf('"%s" is not a directive of a content security policy.', $name));
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    private function values(Directive $directive, array $values): array
    {
        $checked = [];

        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new InvalidPolicy(\sprintf('A value of "%s" is not a string.', $directive->value));
            }

            SourceSyntax::assertValid($directive, $value);
            $checked[$value] = $value;
        }

        $checked = array_values($checked);

        if ([] === $checked && $directive->takesSources()) {
            return [SourceSyntax::NONE];
        }

        if (Directive::RequireTrustedTypesFor === $directive && [] === $checked) {
            return ["'script'"];
        }

        if (\count($checked) > 1 && \in_array(SourceSyntax::NONE, $checked, true) && $directive->takesSources()) {
            throw new InvalidPolicy(\sprintf("'none' stands alone in \"%s\".", $directive->value));
        }

        return $checked;
    }
}
