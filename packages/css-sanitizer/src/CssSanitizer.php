<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

use Phil\CssSanitizer\Check\Sanitization;
use Phil\CssSanitizer\Exception\NestingTooDeep;
use Phil\CssSanitizer\Syntax\Parser;

/**
 * Sanitizes the CSS people write: parses it as CSS Syntax Level 3 does, keeps what the policy allows, rewrites its URLs,
 * and writes it back.
 *
 * Nothing in the output can load a resource the rewriter did not allow, run code, or reach outside the rules the policy
 * names. What is removed is listed, with its reason and line, so that a form can tell its author.
 */
final readonly class CssSanitizer
{
    public function __construct(
        private Policy $policy = new Policy(),
        private Serializer $serializer = new Serializer(),
    ) {
    }

    /** The same sanitizer under another policy. */
    public function withPolicy(Policy $policy): self
    {
        return new self($policy, $this->serializer);
    }

    /** A style sheet: the content of a `<style>` element or of a `.css` file. */
    public function sanitize(string $css): Sanitized
    {
        if (\strlen($css) > $this->policy->maxLength) {
            return $this->refused(RemovalReason::TooLong, $css);
        }

        $parser = new Parser($this->policy->maxDepth);
        $sanitization = new Sanitization($this->policy, $parser, $this->serializer);

        try {
            $sheet = $sanitization->sheet($parser->parseStylesheet($css)->rules);
        } catch (NestingTooDeep) {
            return $this->refused(RemovalReason::TooDeep, $css);
        }

        return new Sanitized($this->serializer->styleSheet($sheet), $sanitization->removals(), $sheet, omittedRemovals: $sanitization->omittedRemovals());
    }

    /** The declarations of a `style` attribute, without braces. */
    public function sanitizeDeclarations(string $css): Sanitized
    {
        if (\strlen($css) > $this->policy->maxLength) {
            return $this->refused(RemovalReason::TooLong, $css);
        }

        $parser = new Parser($this->policy->maxDepth);
        $sanitization = new Sanitization($this->policy, $parser, $this->serializer);

        try {
            $declarations = $sanitization->styleDeclarations($parser->parseDeclarationList($css));
        } catch (NestingTooDeep) {
            return $this->refused(RemovalReason::TooDeep, $css);
        }

        return new Sanitized($this->serializer->declarations($declarations), $sanitization->removals(), declarations: $declarations, omittedRemovals: $sanitization->omittedRemovals());
    }

    private function refused(RemovalReason $reason, string $css): Sanitized
    {
        return new Sanitized('', [new Removal($reason, \sprintf('%d bytes', \strlen($css)))]);
    }
}
