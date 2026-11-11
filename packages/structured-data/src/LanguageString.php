<?php

declare(strict_types=1);

namespace Phil\StructuredData;

/**
 * A text in a given language, written `{"@value": "…", "@language": "…"}`: the name of a page in French on a site
 * whose graph is in English, or one name in several languages as a list.
 */
final readonly class LanguageString
{
    public string $language;

    public function __construct(public string $value, string $language)
    {
        $this->language = LanguageTag::normalize($language);
    }

    /**
     * @return array{'@value': string, '@language': string}
     */
    public function toArray(): array
    {
        return ['@value' => $this->value, '@language' => $this->language];
    }
}
