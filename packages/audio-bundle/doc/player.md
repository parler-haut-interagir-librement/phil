# The player

```twig
<twig:PhilAudio:Player :audio="track" :entitled="bought" id="episode-12" class="wide" :bars="160" />
```

| Prop | Default | Meaning |
|---|---|---|
| `audio` | required | The track, an `Audio` of phil/audio |
| `entitled` | `false` | Whether this listener bought the track, or may hear it whole for another reason |
| `id` | `phil-audio` | The identifier of the figure, from which the identifiers of its parts are made |
| `bars` | `120` | The bars of the waveform, one at least |

Other attributes, `class` among them, reach the figure.

## What it draws

- A `<figure>` whose `<figcaption>` is the title, marked "(excerpt)" when only the excerpt is heard.
- The browser's `<audio controls preload="metadata">`, named by the title: it plays without JavaScript, from the
  keyboard and for screen readers. Its sources are the files this listener may hear, from `Audio::playable()`: the
  whole files, the files of the excerpt, or none, in which case a sentence says to buy the track. A browser that plays
  none of them shows a link to the first file. Nothing plays before the listener asks; no attribute plays it on its own.
- The waveform, an empty `<svg aria-hidden="true">` the controller draws in.
- The chapters of the table of contents, in a `<nav>` named by its heading: each a `<time>` and a link, the recipe
  Button as a link, to the instant in the file — `episode.mp3#t=120.000`, a media fragment a browser opens without
  JavaScript.
- Each transcript with cues, in a `<details>` in its own language: each cue a link to its instant, its speaker, its
  text; and a link to download the file of the transcript for a listener who may hear the whole track.

When only the excerpt is heard, the chapters and cues are those it reaches, their instants counted from its start, as
in its file; before a purchase, there are none.

## What the controller adds

`phil--audio-bundle--player` adds to the audio element without replacing it:

- it draws the bars of the waveform as an SVG path, twice, the bars heard so far clipped to the time played; a click on
  the waveform seeks there — the keyboard has the audio element;
- a link of a chapter or a cue seeks the audio to its instant instead of opening the file, playing or paused as it was;
- as the audio plays, the current chapter and cue get `aria-current="true"`.

## The texts

The texts are in the translation domain `phil_audio`, in English, French, Spanish, German, Italian and Portuguese:
`player.excerpt`, `player.purchase_only`, `player.download`, `player.chapters`, `player.transcript` and
`player.download_transcript`.

## Overriding the template

An application overrides `@PhilAudio/components/Player.html.twig` under
`templates/bundles/PhilAudioBundle/components/Player.html.twig`, keeping the audio element as the control.
