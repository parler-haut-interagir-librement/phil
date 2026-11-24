<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Support;

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\RenderedPage;
use Phil\Url\Url;

/**
 * The pages of the tests: a page that passes every rule, and the pieces to write one that fails.
 */
final class Pages
{
    public const string URL = 'https://example.org/fr/news/opening';

    public const string HEAD = '<meta charset="utf-8">'
        .'<title>Ouverture de la boutique d’hiver</title>'
        .'<meta name="description" content="La boutique d’hiver ouvre ses portes samedi : horaires, adresse et nouveautés de la saison à découvrir sur place.">'
        .'<link rel="canonical" href="https://example.org/fr/news/opening">'
        .'<link rel="alternate" hreflang="fr" href="https://example.org/fr/news/opening">'
        .'<link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">'
        .'<link rel="alternate" hreflang="x-default" href="https://example.org/en/news/opening">'
        .'<meta name="robots" content="index, follow">';

    public const string BODY = '<h1>Ouverture de la boutique</h1>'
        .'<p>Venez nous voir : <a href="/fr/horaires">les horaires de la boutique</a>.</p>'
        .'<p><img src="/images/vitrine.jpg" alt="La vitrine décorée pour l’hiver"><img src="/images/filet.svg" alt=""></p>';

    private function __construct()
    {
    }

    public static function html(string $head = self::HEAD, string $body = self::BODY, ?string $lang = 'fr'): string
    {
        return '<!doctype html><html'.(null === $lang ? '' : ' lang="'.$lang.'"').'><head>'.$head.'</head><body>'.$body.'</body></html>';
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public static function rendered(string $html, string $url = self::URL, int $status = 200, array $headers = [], ?string $channel = null): RenderedPage
    {
        return new RenderedPage(Url::fromString($url), $html, $status, $headers, $channel);
    }

    /**
     * @param list<RenderedPage>  $others
     * @param array<string, bool> $channels
     */
    public static function checked(string $html, string $locale = 'fr', bool $indexable = true, array $others = [], array $channels = [], ?RenderedPage $primary = null): CheckedPage
    {
        return CheckedPage::of(Url::fromString(self::URL), $locale, $indexable, [$primary ?? self::rendered($html), ...$others], $channels);
    }
}
