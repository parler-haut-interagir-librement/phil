<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Enum;

/**
 * What a nonce or a hash allows: the `<script>` or the `<style>` elements of a page.
 */
enum InlineKind: string
{
    case Script = 'script';
    case Style = 'style';

    /**
     * The directive that governs the elements of this kind.
     */
    public function directive(): Directive
    {
        return self::Script === $this ? Directive::ScriptSrc : Directive::StyleSrc;
    }

    /**
     * The directive that governs only the elements of this kind, and takes precedence over `directive()` when a policy
     * declares it.
     */
    public function elementDirective(): Directive
    {
        return self::Script === $this ? Directive::ScriptSrcElem : Directive::StyleSrcElem;
    }
}
