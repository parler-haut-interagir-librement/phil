<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCards\Text;

/**
 * What a card says: its title, maybe a subtitle, and the locale they are written in, which decides where their lines
 * may break.
 */
final readonly class CardContent
{
    public const int MAX_TITLE = 300;

    public const int MAX_SUBTITLE = 300;

    public string $title;

    public ?string $subtitle;

    public string $locale;

    public function __construct(string $title, ?string $subtitle = null, string $locale = 'en')
    {
        $this->title = Text::line($title, self::MAX_TITLE) ?? throw InvalidCardTemplate::because('A card needs a title.');
        $this->subtitle = Text::line($subtitle, self::MAX_SUBTITLE);
        $this->locale = ShareMetadata::locale($locale);
    }

    /**
     * @return array{title: string, subtitle: string|null, locale: string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'subtitle' => $this->subtitle, 'locale' => $this->locale];
    }
}
