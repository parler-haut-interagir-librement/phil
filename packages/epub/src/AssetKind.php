<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * What a file of the book is for: a stylesheet linked from every content document, an image, a font, an audio or a
 * video file played by the content documents or by a media overlay, or a WebVTT text track of captions or subtitles.
 */
enum AssetKind: string
{
    case Stylesheet = 'stylesheet';
    case Image = 'image';
    case Font = 'font';
    case Audio = 'audio';
    case Video = 'video';
    case TextTrack = 'text track';
}
