<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\Manifest;

/**
 * How the installed site is shown: the four modes of `display`, from the whole screen to a browser tab, and the modes
 * only `display_override` may ask for first.
 */
enum DisplayMode: string
{
    case Fullscreen = 'fullscreen';
    case Standalone = 'standalone';
    case MinimalUi = 'minimal-ui';
    case Browser = 'browser';
    case WindowControlsOverlay = 'window-controls-overlay';
    case Tabbed = 'tabbed';

    /**
     * Whether the mode may be the manifest's `display`: every browser falls back along these four.
     */
    public function isFallback(): bool
    {
        return self::WindowControlsOverlay !== $this && self::Tabbed !== $this;
    }
}
