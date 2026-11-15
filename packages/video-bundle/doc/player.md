# The player

```twig
<twig:PhilVideo:Player :video="video" id="talk-12" class="wide" />
```

| Prop | Default | Meaning |
|---|---|---|
| `video` | required | A `HostedVideo` or a `LinkedVideo` |
| `id` | `phil-video` | The identifier of the player, from which the identifiers of its parts are made |

Other attributes reach the root element.

## Every video

| Property | Meaning |
|---|---|
| `id` | Letters, digits, `_` and `-`, 64 at most: the name of its viewings |
| `title` | Its title, never blank |
| `width`, `height` | Its size in pixels, 1280 × 720 by default, so that its space is reserved before it loads |
| `poster` | The image shown before it plays: an image the site serves |
| `transcript`, `transcriptLanguage` | A `Track` of phil/timed-text, and its BCP 47 language |
| `chapters` | A `ChapterList` of phil/timed-text |

## A hosted video

`HostedVideo` adds its files, `VideoSource`s — an address and a `video/*` type, in the order the browser tries them; one
at least —, an HLS stream, and its `TextTrack`s: WebVTT files of captions, subtitles, descriptions or chapters, each with
its language, its label and whether it shows at once.

It is drawn with the recipe Video: the browser's video element with its controls, its poster, its tracks, nothing
fetched before the viewer plays it, and no autoplay. The recipe leaves out a file, a poster or a track of a host
`image_origin()` refuses.

The stream is for a service that serves HLS: where the browser plays HLS, the controller gives it to the video element;
elsewhere the files play. A hosted video always has a file, so that every browser plays it.

`TextTrackResponse::webVtt($track)` serves a `Track` from a controller of the application, with the type `text/vtt` and
`nosniff`.

## A linked video

`LinkedVideo::fromUrl($id, $title, $address, $poster)` reads an address of YouTube — `watch?v=`, `youtu.be/`,
`embed/`, `shorts/`, `live/` — or of Vimeo, its start time kept, through `CookielessVideo` of phil/url; another address
is refused with `InvalidVideo`.

It is drawn as a facade: the poster, a button the controller shows — "Play the video: …" —, a sentence that says playing
loads the platform's player, and a link to the platform for a viewer without JavaScript. The button puts the player in
the facade's place, from `embed()`: the address without cookies, playing at once since the viewer asked, in a frame with
the video's title, a strict referrer policy and a sandbox. The poster is the site's own image: an image of the platform
would reach it before the viewer asks.

## Chapters and transcript

The chapters of the table of contents, in a `<nav>` named by its heading, and the transcript, in a `<details>` in its
language, sit beside the video. For a hosted video, each chapter and cue is a link — the recipe Button — to its instant
in the first file, a media fragment a browser opens without JavaScript, which the controller turns into seeking; the
current chapter and cue get `aria-current="true"`. For a linked video, whose player is the platform's, they are text.

## The texts

The texts are in the translation domain `phil_video`, in English, French, Spanish, German, Italian and Portuguese:
`player.play`, `player.notice`, `player.watch`, `player.download`, `player.chapters` and `player.transcript`.
