<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

/**
 * What a WebVTT text track of a video holds: captions say the dialogue and the sounds, subtitles translate the
 * dialogue, descriptions describe what is shown, chapters name the parts.
 */
enum TextTrackKind: string
{
    case Captions = 'captions';
    case Subtitles = 'subtitles';
    case Descriptions = 'descriptions';
    case Chapters = 'chapters';
}
