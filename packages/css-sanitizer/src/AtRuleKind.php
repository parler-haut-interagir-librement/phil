<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

/**
 * The at-rules a policy may allow. All but `Import` are allowed by default.
 */
enum AtRuleKind: string
{
    /** `@media`, its queries limited to the policy's media types and to features that load nothing. */
    case Media = 'media';
    /** `@supports`, its conditions free of URLs. */
    case Supports = 'supports';
    /** `@page`, its page selectors and the at-rules of its sixteen margin boxes. */
    case Page = 'page';
    /** `@font-face`, its sources local fonts or URLs the rewriter allows. */
    case FontFace = 'font-face';
    /**
     * `@import`, at the start of a style sheet only, its URL a style sheet the rewriter allows, its conditions those of
     * `supports()` and of `@media`; not allowed by default.
     */
    case Import = 'import';
}
