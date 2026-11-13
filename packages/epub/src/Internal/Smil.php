<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\Asset;
use Phil\Epub\AssetKind;
use Phil\Epub\Clip;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\MediaOverlay;

/**
 * The SMIL document of a media overlay, beside its chapter: one `par` per clip, the element of the chapter it
 * highlights and the part of the audio file that reads it, and the clock values EPUB writes its durations in.
 *
 * @internal
 */
final class Smil
{
    /**
     * @param string $path    the path of the SMIL document in the package
     * @param string $chapter the path of its chapter in the package
     */
    public static function write(MediaOverlay $overlay, string $path, string $chapter): string
    {
        $text = Paths::relative($path, $chapter);
        $pars = '';

        foreach ($overlay->clips as $index => $clip) {
            $pars .= '      <par id="par-'.($index + 1).'">'
                .'<text src="'.XhtmlSerialization::attribute($text.'#'.$clip->fragment).'"/>'
                .'<audio src="'.XhtmlSerialization::attribute(Paths::relative($path, $clip->audio)).'" clipBegin="'.self::clock($clip->begin).'" clipEnd="'.self::clock($clip->end).'"/>'
                .'</par>'."\n";
        }

        return Xml::DECLARATION
            .'<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops" version="3.0">'."\n"
            .'  <body>'."\n"
            .'    <seq id="seq-1" epub:textref="'.XhtmlSerialization::attribute($text).'">'."\n"
            .$pars
            .'    </seq>'."\n"
            .'  </body>'."\n"
            .'</smil>'."\n";
    }

    /**
     * A duration in milliseconds as a full clock value, `0:01:05.250`.
     */
    public static function clock(int $milliseconds): string
    {
        return \sprintf('%d:%02d:%02d.%03d', intdiv($milliseconds, 3_600_000), intdiv($milliseconds, 60_000) % 60, intdiv($milliseconds, 1000) % 60, $milliseconds % 1000);
    }

    /**
     * The audio files a media overlay reads.
     *
     * @return list<string>
     */
    public static function audio(MediaOverlay $overlay): array
    {
        return array_values(array_unique(array_map(static fn (Clip $clip): string => $clip->audio, $overlay->clips)));
    }

    /**
     * Each clip of a media overlay highlights an element of its chapter and reads an audio file of the book in an EPUB
     * core media type.
     *
     * @param array<string, Asset> $assets
     *
     * @throws InvalidBook
     */
    public static function check(MediaOverlay $overlay, string $path, Converted $content, array $assets): void
    {
        foreach ($overlay->clips as $clip) {
            if (!\in_array($clip->fragment, $content->ids, true)) {
                throw InvalidBook::because(\sprintf('The media overlay of "%s" highlights "#%s", which the chapter does not hold.', $path, $clip->fragment));
            }
        }

        foreach (self::audio($overlay) as $audio) {
            $asset = $assets[$audio] ?? null;

            if (!$asset instanceof Asset || AssetKind::Audio !== $asset->kind || !$asset->isCore()) {
                throw InvalidBook::because(\sprintf('The media overlay of "%s" reads "%s", which is not an audio file of the book in MP3, AAC or Opus.', $path, $audio));
            }
        }
    }
}
