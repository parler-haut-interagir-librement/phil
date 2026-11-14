# Video

A video with the controls of the browser, which never plays on its own: nothing fetched before the reader asks, its
poster an image, its text tracks, and in a figure its caption, credit, license and transcript.

```twig {"preview":true}
<twig:Video
    src="/videos/talk.webm"
    poster="/images/talk.jpg"
    :width="1280"
    :height="720"
    :tracks="[{src: '/videos/talk.en.vtt', srclang: 'en', label: 'English', default: true}]"
/>
```

## Installation

::: installation

## Usage

```twig
<twig:Video
    :sources="[
        {src: asset('videos/talk.webm'), type: 'video/webm'},
        {src: asset('videos/talk.mp4'), type: 'video/mp4'},
    ]"
    poster="{{ asset('images/talk.jpg') }}"
    :width="1280"
    :height="720"
    :tracks="[
        {src: asset('videos/talk.en.vtt'), srclang: 'en', label: 'English', default: true},
        {src: asset('videos/talk.fr.vtt'), srclang: 'fr', label: 'Français'},
    ]"
    caption="{{ 'talk.caption'|trans }}"
    transcript="{{ 'talk.transcript'|trans }}"
    transcriptLabel="{{ 'video.transcript'|trans }}"
    fallbackLabel="{{ 'video.download'|trans }}"
/>
```

`width` and `height` are required, so that the space of the video is reserved at its ratio before anything loads.
`src` is a single source; `sources` lists others, each `{src, type}`, after it, in the order the browser tries them. A
`data:` source, a source of another type than `video/*` and a source of a host `image_origin()` refuses are left out;
without a source left, nothing is drawn. A browser that plays no video shows a link to the first source, in the words
of `fallbackLabel`.

`preload` is `none` by default: nothing is fetched before the reader plays the video, which spares the data of those
who never do; `metadata` fetches its duration and size. Any other value is `none`. There is no prop for autoplay, and
an `autoplay` attribute is left out: the video plays when its reader asks. `muted` and `loop` are offered for a short
silent clip. `label` names the video for screen readers when no caption does. `id` and `class` reach the `video`.

## The poster

`poster` is one address, as the `poster` attribute of HTML takes it: a `video` has no `picture` of its own, and a
picture laid over it would need a script to give way to the video. Give it the fallback of the picture, the `src` that
`image_sources()` finds among the assets, an address of the page or of a listed host, or an inline `data:image/` one;
any other address is left out, as for `Image`.

## Text tracks and transcript

`tracks` lists WebVTT files, each `{src, srclang, label, kind, default}`: `kind` is `captions` by default, which also
say the sounds, or `subtitles`, `descriptions` or `chapters`; the first track marked `default` shows at once. A track
without a language tag, without a label, of another kind, inline or of a refused host is left out. `transcript` writes,
after the video, a native disclosure of what is said and shown, which the video is described by through
`aria-describedby`; `transcriptLabel` is the words of its summary, `Transcript` by default. `caption`, `credit`,
`license`, `licenseHref`, `creation` and `creationLabel` follow, as for `Image`.

## Privacy

A video of another host is refused unless `phil_ux_kit.image.hosts` lists its host, as an image is, so that no page
calls a third party behind the reader's back. A `video` takes no `referrerpolicy`: a video of a listed host is fetched
under the referrer policy of the page.

## Accessibility

The controls are the browser's, reached by the keyboard. Nothing moves before the reader asks (WCAG 2.2.2). Captions
serve those who do not hear the sound (WCAG 1.2.2), the transcript those who neither see nor hear it (WCAG 1.2.8); its
text is escaped.
