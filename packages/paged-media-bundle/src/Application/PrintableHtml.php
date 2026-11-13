<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

/**
 * A page for a paged-media engine: its body, already safe to print, its stylesheets in the order of the cascade, its
 * language and its title.
 */
final readonly class PrintableHtml
{
    /**
     * @param string       $body        the HTML of the body, sanitized by whoever wrote it
     * @param list<string> $stylesheets the text of each stylesheet
     */
    public function __construct(
        public string $body,
        public array $stylesheets = [],
        public string $language = 'en',
        public ?string $title = null,
    ) {
    }

    /**
     * The whole document. A closing tag in a stylesheet is escaped for CSS, so that it cannot end its `style` element.
     */
    public function document(): string
    {
        $head = '<meta charset="utf-8">';

        if (null !== $this->title) {
            $head .= '<title>'.htmlspecialchars($this->title, \ENT_QUOTES).'</title>';
        }

        foreach ($this->stylesheets as $stylesheet) {
            $head .= '<style>'.str_replace('</', '<\/', $stylesheet).'</style>';
        }

        return '<!doctype html><html lang="'.htmlspecialchars($this->language, \ENT_QUOTES).'"><head>'.$head.'</head><body>'.$this->body.'</body></html>';
    }
}
