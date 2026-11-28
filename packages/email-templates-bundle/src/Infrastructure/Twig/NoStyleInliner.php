<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;

/**
 * Leaves the document as it is: the inliner when `twig/cssinliner-extra` is not installed. The layout and the
 * components already write their styles inline; only the rules of a `<style>` element of an overridden layout stay
 * there.
 */
final readonly class NoStyleInliner implements StyleInliner
{
    #[Override]
    public function inline(string $html): string
    {
        return $html;
    }
}
