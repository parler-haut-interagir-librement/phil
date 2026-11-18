# Changelog

All notable changes to `phil/audio-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- `phil/media-bundle` 0.2 is accepted beside 0.1.1.

## 0.1.0-alpha - 2026-11-15

### Added

- The configuration `phil_audio`: the tools, `null` or `ffmpeg`, the programs and limits of ffmpeg, the renditions by
  name — refused when no encoder takes them —, the waveform, the default listening policy, the storage and the
  subdirectory of the recipes of the kit.
- The jobs: `AudioJobs::schedule()` and the messages `TranscodeAudio` and `ComputeWaveform`, identifiers and instants
  only; their handlers, which delete their temporary files whatever happens and fail for good on a file gone, a
  rendition unknown or a file refused; no job without a tool or without a message bus.
- The ports `AudioFiles` and `RenditionStore`, and their adapters on phil/media-bundle: local copies with random names,
  created for the copy alone, readable by their owner only, streamed from the storage by `MediaLibrary::readOriginal()`
  of phil/media-bundle 0.1.1 or later (a conflict with older versions), deleted when the copy fails, an original missing
  a file gone and a failing storage or disk an error the job may retry; results under `<prefix>/<medium>/`, keys that
  never leave their folder.
- The player `PhilAudio:Player`: the browser's audio element, the files the listener may hear, a sentence before a
  purchase, the chapters and the transcripts as links to their instants, drawn with the recipe Button of the kit.
- The Stimulus controller `player` and its stylesheet: the waveform in SVG, seeking from a chapter, a cue or the
  waveform, the current chapter and cue marked.
- The texts of the player in English, French, Spanish, German, Italian and Portuguese.
- The Flex recipe `recipe/0.1`: the bundle and its configuration.
- The package skeleton, its documentation in `doc/`, `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
