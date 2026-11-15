<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\TimedText\ChapterList;
use Phil\TimedText\Track;
use Phil\Url\CookielessVideo;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Phil\VideoBundle\Application\Exception\InvalidVideo;

/**
 * A video of YouTube or Vimeo, shown behind a facade: an image the site serves and a button. Nothing of the platform is
 * fetched before the viewer presses it; the player then loads from the platform's address that writes no cookies,
 * `youtube-nocookie.com` or Vimeo with "do not track".
 */
final readonly class LinkedVideo extends Video
{
    /**
     * @param Url $watch the address of the video on its platform, for a viewer without JavaScript
     */
    public function __construct(
        string $id,
        string $title,
        public CookielessVideo $player,
        public Url $watch,
        int $width = 1280,
        int $height = 720,
        ?Url $poster = null,
        ?Track $transcript = null,
        ?string $transcriptLanguage = null,
        ?ChapterList $chapters = null,
    ) {
        parent::__construct($id, $title, $width, $height, $poster, $transcript, $transcriptLanguage, $chapters);
    }

    /**
     * The video at an address of YouTube or Vimeo, as an author pastes it.
     *
     * @throws InvalidVideo when the address is no video of these platforms
     */
    public static function fromUrl(string $id, string $title, string $address, ?Url $poster = null): self
    {
        try {
            $watch = Url::fromString($address);
            $player = CookielessVideo::fromUrl($watch);
        } catch (InvalidUrl $invalidUrl) {
            throw new InvalidVideo($invalidUrl->getMessage(), 0, $invalidUrl);
        }

        if (!$player instanceof CookielessVideo) {
            throw new InvalidVideo('The address is no video of YouTube or Vimeo.');
        }

        return new self($id, $title, $player, $watch, poster: $poster);
    }

    /**
     * The name of the platform, as the viewer is told it.
     */
    public function platform(): string
    {
        return $this->player->type->label();
    }

    /**
     * The address the facade loads once pressed: the player without cookies, playing at once, since the viewer asked.
     */
    public function embed(): string
    {
        $url = $this->player->url->toString();

        return $url.(str_contains($url, '?') ? '&' : '?').'autoplay=1';
    }
}
