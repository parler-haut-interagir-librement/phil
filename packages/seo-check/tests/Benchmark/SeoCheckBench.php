<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Benchmark;

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\SeoChecker;
use Phil\Url\Url;

/**
 * A page is checked each time it is published, while its author waits for the report: a long article of some 600
 * links, 100 images, 300 headings and the translations of ten languages, at its address and at a second path, is read
 * once and checked by every default rule. The time must stay in proportion to the size of the page.
 *
 * @BeforeMethods("setUp")
 */
final class SeoCheckBench
{
    private readonly SeoChecker $checker;

    private CheckedPage $page;

    public function __construct()
    {
        $this->checker = new SeoChecker();
        $url = Url::fromString('https://example.org/fr/news/opening');
        $this->page = CheckedPage::of($url, 'fr', true, [new RenderedPage($url, '')]);
    }

    public function setUp(): void
    {
        $url = Url::fromString('https://example.org/fr/news/opening');
        $head = '<title>Ouverture de la boutique d’hiver</title><meta name="description" content="La boutique d’hiver ouvre samedi : horaires, adresse et nouveautés.">'
            .'<link rel="canonical" href="https://example.org/fr/news/opening"><meta name="robots" content="index, follow">';

        foreach (['fr', 'en', 'es', 'de', 'it', 'pt', 'nl', 'pl', 'ja', 'ko'] as $language) {
            $head .= '<link rel="alternate" hreflang="'.$language.'" href="https://example.org/'.('fr' === $language ? 'fr/news/opening' : $language.'/').'">';
        }

        $body = '<h1>Ouverture</h1>';

        for ($section = 1; $section <= 100; ++$section) {
            $body .= '<h2>Partie '.$section.'</h2><h3>Détails</h3><h3>Suite</h3><p>'.str_repeat('Des mots de l’article, ', 20).'</p>'
                .'<p><a href="/fr/notes/'.$section.'">La note '.$section.'</a>, <a href="https://partner.example.com/'.$section.'">le partenaire</a>, <a href="#s'.$section.'">ici</a>, <a href="/fr/'.$section.'">plus</a>, <a href="/fr/b/'.$section.'">la page '.$section.'</a>, <a href="/fr/c/'.$section.'">le guide</a></p>'
                .'<img src="/images/'.$section.'.jpg" alt="Image '.$section.'">';
        }

        $html = '<!doctype html><html lang="fr"><head>'.$head.'</head><body>'.$body.'</body></html>';
        $this->page = CheckedPage::of($url, 'fr', true, [
            new RenderedPage($url, $html),
            new RenderedPage(Url::fromString('https://example.org/fr/'), $html),
        ]);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchCheck(): void
    {
        $this->checker->check($this->page);
    }
}
