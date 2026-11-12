<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Enum;

/**
 * A directive of Content Security Policy Level 3 that a policy may declare. The reporting directives, `report-uri` and
 * `report-to`, are not among them: the bundle writes them from the reporting configuration.
 */
enum Directive: string
{
    case DefaultSrc = 'default-src';
    case ScriptSrc = 'script-src';
    case ScriptSrcElem = 'script-src-elem';
    case ScriptSrcAttr = 'script-src-attr';
    case StyleSrc = 'style-src';
    case StyleSrcElem = 'style-src-elem';
    case StyleSrcAttr = 'style-src-attr';
    case ImgSrc = 'img-src';
    case FontSrc = 'font-src';
    case ConnectSrc = 'connect-src';
    case MediaSrc = 'media-src';
    case ObjectSrc = 'object-src';
    case FrameSrc = 'frame-src';
    case ChildSrc = 'child-src';
    case WorkerSrc = 'worker-src';
    case ManifestSrc = 'manifest-src';
    case FrameAncestors = 'frame-ancestors';
    case FormAction = 'form-action';
    case BaseUri = 'base-uri';
    case Sandbox = 'sandbox';
    case UpgradeInsecureRequests = 'upgrade-insecure-requests';
    case RequireTrustedTypesFor = 'require-trusted-types-for';
    case TrustedTypes = 'trusted-types';

    /**
     * Whether the directive takes a source list (`'self' https://cdn.example.com`); the others take tokens of their
     * own, or nothing.
     */
    public function takesSources(): bool
    {
        return !\in_array($this, [self::Sandbox, self::UpgradeInsecureRequests, self::RequireTrustedTypesFor, self::TrustedTypes], true);
    }

    /**
     * Whether the directive falls back on `default-src` when a policy does not declare it: the fetch directives do,
     * the document and navigation directives do not.
     */
    public function fallsBackOnDefault(): bool
    {
        return $this->takesSources() && !\in_array($this, [self::DefaultSrc, self::FrameAncestors, self::FormAction, self::BaseUri], true);
    }

    /**
     * Whether a source of the list may be a nonce, a hash or an `unsafe-*` keyword: never in `frame-ancestors`,
     * `form-action` nor `base-uri`, whose sources are locations only.
     */
    public function takesScriptKeywords(): bool
    {
        return $this->fallsBackOnDefault() || self::DefaultSrc === $this;
    }
}
