# Testing

## The package's tests

```sh
vendor/bin/phpunit packages/audio-bundle/tests
```

The unit suite runs without a kernel: the configuration and the services the extension registers, the passes, the jobs
with tools and storage in memory — ffmpeg replaced by a transcoder that writes its source in capitals —, and the
adapters of phil/media-bundle on its library in memory.

The functional suite boots a small application — FrameworkBundle with Messenger and the translator, TwigBundle,
TwigComponentBundle with the recipe Button copied where `ux:install` would put it, and the bundle — and draws the player
for a whole track, an excerpt, a track before a purchase, and a bare track, in English and in French. It is skipped
where the event dispatcher is installed for development only, as when the package is installed on its own.

The package is held to 100 % covered mutation score by Infection.

## The controller

```sh
node --import ./tests/js/register.mjs --test tests/js
```

The controller runs under Node's test runner, Stimulus resolved to a stub: the path of the bars, the current chapter
and cue, seeking from a link, a link without an instant followed, a click on the waveform.

## With a real ffmpeg

The suites need no ffmpeg. To run the jobs for real, set `tools: ffmpeg` in an application with a worker and upload a
file: the renditions and `peaks.json` appear under the prefix of the storage.
