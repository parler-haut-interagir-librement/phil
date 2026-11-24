<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Translation;

/**
 * The messages of the check in English, by key.
 */
final class English
{
    public const string LOCALE = 'en';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'http-status.unsuccessful' => 'The page at {path} answers with status {status}: search engines drop a page that answers with an error.',
        'http-status.redirect' => 'The page at {path} answers with status {status}, a redirect: its canonical address should answer itself.',
        'page-size.truncated' => 'The page has {bytes} bytes, more than the check reads: the end of the page was not checked.',
        'document-title.missing' => 'The page has no title element: results pages show its address instead.',
        'document-title.multiple' => 'The page has {count} title elements: search engines read the first one only.',
        'document-title.empty' => 'The title of the page is empty: results pages show its address instead.',
        'document-title.short' => 'The title of the page has {length} characters: below {min}, it says too little to tell the page from others.',
        'document-title.long' => 'The title of the page has {length} characters: results pages cut it beyond {max}.',
        'meta-description.missing' => 'The page has no meta description: results pages write a summary of their own from its text.',
        'meta-description.multiple' => 'The page has {count} meta descriptions: search engines read the first one only.',
        'meta-description.empty' => 'The meta description of the page is empty: results pages write a summary of their own from its text.',
        'meta-description.short' => 'The meta description has {length} characters: below {min}, it says little of the page.',
        'meta-description.long' => 'The meta description has {length} characters: results pages cut it beyond {max}.',
        'document-language.missing' => 'The html element has no lang attribute: give it the language of the page, {locale}.',
        'document-language.region' => 'The page is published in {locale}, but its lang attribute says {lang}.',
        'document-language.mismatch' => 'The page is published in {locale}, but its lang attribute says {lang}: search engines show it to readers of the wrong language.',
        'document-language.header' => 'The Content-Language header says {header}, but the page is published in {locale}.',
        'single-h1.missing' => 'The page has no first-level heading: say what the page is about in an h1.',
        'single-h1.multiple' => 'The page has {count} first-level headings: keep one, and make the others h2.',
        'single-h1.empty' => 'A first-level heading of the page has no text.',
        'canonical.missing' => 'The page names no canonical address: add a canonical link to {expected}.',
        'canonical.variant-missing' => 'The page at {path} names no canonical address: add a canonical link to {expected}, as the page at its own address does.',
        'canonical.relative' => 'The canonical link "{href}" is relative: write it as an absolute address.',
        'canonical.invalid' => 'The canonical link "{href}" is not an address search engines can follow.',
        'canonical.conflicting' => 'The page at {path} names {count} different canonical addresses: search engines ignore them all.',
        'canonical.mismatch' => 'The canonical link names {found}, but the page was published at {expected}.',
        'canonical.variant-mismatch' => 'The page at {path} names {found} as canonical, but it is the page published at {expected}: both paths should name it.',
        'indexing.not-blocked' => 'The page at {path} is not indexable, but nothing keeps search engines from indexing it: add a noindex directive.',
        'indexing.not-blocked-in-channel' => 'The page at {path} is not indexable in the channel {channel}, but nothing keeps search engines from indexing it there: add a noindex directive.',
        'indexing.blocked' => 'The page at {path} is published as indexable, but a {directive} directive ({source}) keeps it out of the index.',
        'indexing.blocked-in-channel' => 'The page at {path} is indexable in the channel {channel}, but a {directive} directive ({source}) keeps it out of the index.',
        'indexing.blocked-for-agent' => 'The page at {path} is published as indexable, but a {directive} directive ({source}) keeps it out of the index of {agent}.',
        'hreflang.invalid-code' => '"{code}" is not a language for hreflang: write a BCP 47 language tag, or x-default.',
        'hreflang.relative' => 'The {code} translation is linked by a relative address, "{href}": write it as an absolute address.',
        'hreflang.invalid-url' => 'The {code} translation is linked to "{href}", which is not an address search engines can follow.',
        'hreflang.duplicate' => 'The language {code} leads to several addresses: list one translation per language.',
        'hreflang.missing-self' => 'The page lists its translations, but not itself: add an alternate link to {canonical} under its own language.',
        'hreflang.self-language' => 'The page lists itself under {codes}, but is published in {locale}.',
        'hreflang.not-reciprocal' => 'The {code} translation, {href}, does not list this page back: search engines ignore a translation that is not reciprocal.',
        'crawlable-links.no-href' => 'The link "{text}" has no href: search engines cannot follow it.',
        'crawlable-links.javascript' => 'The link "{text}" runs a script, {href}: search engines cannot follow it.',
        'link-text.generic' => 'The text of the link to {href}, "{text}", does not say where it leads: describe its destination.',
        'image-alt.missing' => 'The image {src} has no alt attribute: describe it, or give it alt="" when it is only decoration.',
        'broken-links.broken' => 'The link to {href} answers with status {status}: correct or remove it.',
        'broken-links.server-error' => 'The link to {href} answered with status {status}, an error of its server: check it again later.',
        'broken-links.redirect' => 'The link to {href} redirects, with status {status}: link to where it leads.',
        'structured-data.unreadable' => 'The structured data of the page cannot be read: {reason}',
        'structured-data.none' => 'The page has no structured data: search engines show no rich result for it.',
        'structured-data.issue' => 'Structured data: {message}',
        'share-tags.issue' => 'Share tags: {message}',
        'check.rule-failed' => 'The rule {rule} could not check the page ({error}): its points are missing from this report.',
        'report.title' => 'SEO check',
        'report.clean' => 'No point to correct.',
        'report.count' => '{count} point(s) to correct.',
        'severity.error' => 'Error',
        'severity.warning' => 'Warning',
        'severity.info' => 'Information',
    ];

    private function __construct()
    {
    }
}
