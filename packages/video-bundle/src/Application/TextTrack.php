<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\Url\Url;
use Phil\VideoBundle\Application\Exception\InvalidVideo;

/**
 * A WebVTT file of a video: what it holds, its language, the label a viewer picks it by, its address, and whether it
 * shows at once.
 */
final readonly class TextTrack
{
    /**
     * @param string $language a BCP 47 language tag, `fr-CA`
     *
     * @throws InvalidVideo when the language is no tag or the label is empty
     */
    public function __construct(
        public Url $src,
        public string $language,
        public string $label,
        public TextTrackKind $kind = TextTrackKind::Captions,
        public bool $default = false,
    ) {
        if (1 !== preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $language)) {
            throw new InvalidVideo(\sprintf('"%s" is no language tag.', $language));
        }

        if ('' === trim($label)) {
            throw new InvalidVideo('A text track has a label.');
        }
    }
}
