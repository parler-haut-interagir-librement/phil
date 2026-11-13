# Media, overlays and layout

A book can hold more than text and images: audio and video files its chapters play, captions for them, a narration
that reads the text aloud while the reading system highlights it, and pages laid out once and for all, as in a comic,
a picture book or a game sheet.

## Audio and video

`Asset::audio()` and `Asset::video()` add a file the chapters play with `<audio>` and `<video>`, at a path relative to
the package directory. The media type is read from the first bytes when it is not given:

| File | Media type | Core media type |
|---|---|---|
| MP3 (ID3 header or MPEG frame) | `audio/mpeg` | yes |
| AAC in MP4 (`ftyp` box) | `audio/mp4` | yes |
| Opus in Ogg | `audio/ogg; codecs=opus` | yes |
| Vorbis or another codec in Ogg | `audio/ogg` | no |
| FLAC | `audio/flac` | no |
| WAV | `audio/wav` | no |
| MP4 video | `video/mp4` | video is exempt |
| WebM | `video/webm` | video is exempt |
| Ogg video | `video/ogg` | video is exempt |

A media type given as argument may name codecs, such as `video/webm; codecs="vp9,opus"`.

```php
$book = $book->withAsset(
    Asset::audio('audio/theme.mp3', $mp3),
    Asset::video('video/trailer.mp4', $mp4),
    Asset::textTrack('video/trailer.en.vtt', $vtt),
)->withChapter(new Chapter(
    '<audio controls="" src="audio/theme.mp3">The theme, played on a fiddle.</audio>'
    .'<video controls="" src="video/trailer.mp4">'
    .'<track kind="captions" src="video/trailer.en.vtt" srclang="en" label="English"/>'
    .'</video>',
));
```

The chapters refer to these files by paths relative to themselves, as they do to images; an address the book does not
hold is dropped, and remote audio and video stay remote. `Asset::textTrack()` takes a WebVTT file, which starts with
`WEBVTT`, for captions, subtitles, descriptions or chapter titles.

### Fallbacks

Reading systems are only required to play the EPUB core media types. An audio file outside them needs a fallback: an
other audio asset, named by the `fallback` attribute of its manifest item, that the reading system plays instead. The
chain of fallbacks ends in a core media type, or the asset is refused.

```php
$mp3 = Asset::audio('audio/theme.mp3', $mp3Bytes);
$flac = Asset::audio('audio/theme.flac', $flacBytes, fallback: $mp3);

$book = $book->withAsset($flac);
```

A fallback is written with the asset that names it; adding it to the book as well writes it once. A video may have a
fallback too, another video, though EPUB requires none. A fallback is of the same kind as its asset and at another
path.

## Media overlays

A media overlay synchronises a chapter with a narration: each `Clip` names an element of the chapter by its
identifier and the part of an audio file that reads it, from its beginning to its end in milliseconds. The reading
system plays the clips in order and highlights each element as it is read.

```php
$book = $book
    ->withAsset(
        Asset::audio('audio/chapter-1.mp3', $mp3),
        Asset::stylesheet('css/book.css', '.reading { background: #ffef9f; }'),
    )
    ->withNarration(new Narration(['Ann Reader'], activeClass: 'reading'))
    ->withChapter(new Chapter(
        '<h1 id="title">The Storm</h1><p id="s1">The light went out at midnight.</p>',
        overlay: new MediaOverlay(
            new Clip('title', 'audio/chapter-1.mp3', 0, 1_800),
            new Clip('s1', 'audio/chapter-1.mp3', 1_800, 4_250),
        ),
    ));
```

The writer puts the SMIL document beside its chapter — `chapter-1.smil` for `chapter-1.xhtml` —, names it in the
`media-overlay` attribute of the chapter's manifest item, and declares in the package document the duration of each
overlay, their total, the narrators and the classes of `Narration`: `activeClass` is put on the element being read,
`playbackActiveClass` on the document while it plays. A book that declares these classes styles them, so it needs a
stylesheet. The package also gets the `auditory` access mode and the `synchronizedAudioText` feature.

The book is refused when a clip names an element its chapter does not hold, reads a file that is not an audio asset of
the book in a core media type, or begins after it ends; when an overlay has no clip; or when the path of a SMIL
document is taken by another file.

## Fixed layout

A reflowable book lets the reading system lay its text out to the screen. A fixed-layout book is laid out once: each
page has its size, its viewport, and the reading system scales it.

```php
$book = $book
    ->withRendition(Rendition::fixed(1200, 1600, Orientation::Portrait, Spread::Landscape))
    ->withChapter(
        new Chapter('<img src="pages/1.png" alt="Page 1: the hero sets out"/>', pageSpread: PageSpread::Right),
        new Chapter('<img src="pages/2.png" alt="Page 2: a storm"/>', pageSpread: PageSpread::Left),
        new Chapter('<h1>Notes</h1><p>…</p>', layout: Layout::Reflowable),
    );
```

`Rendition` declares `rendition:layout`, `rendition:orientation` and `rendition:spread` in the package document when
they differ from the defaults — reflowable, either orientation, spread as the reading system chooses. A pre-paginated
book needs a viewport, the size of its pages in CSS pixels; each pre-paginated document gets
`<meta name="viewport" content="width=…, height=…"/>` in its head.

A chapter may differ from the book: `layout: Layout::PrePaginated` with its `viewport` in a reflowable book, such as a
map or a character sheet in a novel, or `layout: Layout::Reflowable` in a fixed-layout book, such as its notes. Its
`viewport` replaces the book's for that page. `pageSpread` puts it on the left page, the right page, or across both,
centred. These choices are written as properties of the chapter's place in the spine. The cover document of a
fixed-layout book takes the book's viewport; its navigation document, when in the reading order, stays reflowable.

A pre-paginated chapter without viewport, its own or the book's, and a reflowable chapter given a viewport, are
refused.
