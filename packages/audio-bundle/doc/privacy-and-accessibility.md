# Privacy and accessibility

## What the messages carry

A job's message is an identifier, the name of a rendition and two instants. The audio never travels in a message: the
worker fetches it from storage by its identifier. A transport — a database, a queue of another service — keeps nothing
of what was said, nor who said it.

## What leaves the server

Nothing: ffmpeg runs on the workers, closed to the network (see phil/audio). The player fetches the site's own files.

## The tags of a file

A rendition carries none of the tags of the upload: a recorder's name for the author, a place, a device stay in the
original, which the application keeps private.

## What the player owes its listeners

- The control is the browser's `<audio>`, named by the title: reached by the keyboard, read by screen readers, playable
  without JavaScript (WCAG 2.1.1, 4.1.2).
- Nothing plays before the listener asks (WCAG 1.4.2); only the duration is fetched beforehand.
- A transcript, in its own language, beside the audio (WCAG 1.2.1); each cue and chapter reached by the keyboard, and
  without JavaScript as a link to its instant.
- The waveform is decoration, hidden from screen readers; the current chapter and cue are marked with
  `aria-current`, not by colour alone.
- A listener who may not hear the track is told why, in a sentence, rather than shown a control that does nothing.
