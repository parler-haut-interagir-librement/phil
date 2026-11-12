<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Benchmark;

use Generator;
use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\CardImage;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Tests\Support\Images;

/**
 * A card is drawn once per title, out of the request when Messenger is there, during it otherwise: a short title, a
 * long one fitted by halving its size, and one cut at its smallest size, plain or over a background image with a logo.
 *
 * @BeforeMethods("setUp")
 */
final class CardRendererBench
{
    private const array TITLES = [
        'short' => 'Release notes',
        'long' => 'A rather long title for a share card, which the fitter shrinks until it holds in three lines at most',
        'cut' => 'A title far too long for any card: it goes on and on, past the three lines of the template, past the smallest size it may be drawn at, so that the fitter cuts it with an ellipsis',
    ];

    private readonly GdCardRenderer $renderer;

    private readonly CardFont $font;

    private CardTemplate $template;

    private CardContent $content;

    public function __construct()
    {
        $this->renderer = new GdCardRenderer();
        $this->font = CardFont::fromFile(__DIR__.'/../Fixtures/card-font.ttf');
        $this->template = new CardTemplate($this->font);
        $this->content = new CardContent(self::TITLES['short']);
    }

    /**
     * @param array{title: string, images: bool} $params
     */
    public function setUp(array $params): void
    {
        $this->template = $params['images']
            ? new CardTemplate($this->font, accent: new Color(225, 29, 72), backgroundImage: CardImage::fromBytes(Images::png(1600, 900)), logo: CardImage::fromBytes(Images::png(400, 120, 0xFF0000, true)))
            : new CardTemplate($this->font);
        $this->content = new CardContent(self::TITLES[$params['title']], 'The subtitle of the card', 'en');
    }

    /**
     * @ParamProviders("cards")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchRender(): void
    {
        $this->renderer->render($this->template, $this->content);
    }

    /**
     * @return Generator<string, array{title: string, images: bool}>
     */
    public function cards(): Generator
    {
        foreach (array_keys(self::TITLES) as $title) {
            yield $title => ['title' => $title, 'images' => false];
            yield $title.' with images' => ['title' => $title, 'images' => true];
        }
    }
}
