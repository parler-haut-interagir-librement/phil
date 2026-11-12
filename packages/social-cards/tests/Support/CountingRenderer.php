<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Support;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Contract\CardRenderer;

/**
 * A renderer that draws nothing: its "PNG" names the title, and its renders are counted.
 */
final class CountingRenderer implements CardRenderer
{
    public int $renders = 0;

    public function __construct(
        private readonly string $version = 'test',
    ) {
    }

    public function version(): string
    {
        return $this->version;
    }

    public function render(CardTemplate $template, CardContent $content): string
    {
        ++$this->renders;

        return 'png:'.$content->title;
    }
}
