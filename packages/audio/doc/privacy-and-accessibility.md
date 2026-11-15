# Privacy and accessibility

## What leaves the server

Nothing: the library runs ffmpeg and ffprobe on the server, on local files, and calls no service. A transcoding service
behind `Contract\Transcoder` is an adapter of the application, whose own terms apply.

## What ffmpeg may reach

Each run is closed to the network and to the shell:

- a file is named `file:<path>`, and ffmpeg is told to open the `file` protocol only (`-protocol_whitelist file`): a
  playlist or a concatenation file that names an address cannot make the server fetch it;
- no standard input is read (`-nostdin`);
- the arguments are a list, each passed to the program as it is: a file name is never read as a command;
- the input, the output, the duration, the printed output and the time are bounded by `FfmpegLimits`.

The temporary samples of a waveform have a random name and are deleted whatever happens.

## The tags of a file

A recorder writes tags into a file: a title, an author, sometimes a place or the device. `read()` gives them to the
application, which decides what to show; a rendition drops them all, so that the files a site serves carry none of
what the upload carried.

## What a player owes its listeners

The library prepares what an accessible player needs; `phil/audio-bundle` draws it:

- a transcript for each track that has speech, in its language, so that a listener who does not hear it reads it
  (WCAG 1.2.1);
- chapters with their titles, a table of contents that a screen reader and the keyboard reach;
- a waveform reduced to a few hundred bars, drawn as decoration beside the browser's own controls, never in their
  place.
