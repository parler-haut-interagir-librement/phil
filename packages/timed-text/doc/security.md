# Security

Caption and transcript files come from members, editors, speech-to-text services and other sites. The library reads
them as hostile.

## Bounded work

`Limits` caps the bytes of a document, its cues, the text of one cue and the problems an inspection lists; past a cap,
the reader throws `Exception\LimitExceeded` before it costs more. JSON is decoded with its nesting bounded to eight
levels and its big integers kept as strings, which are then refused as seconds. Every parser runs in time linear in
its input: no pattern backtracks over a whole line. The parsers are fuzzed — see [Testing](testing.md).

Invalid UTF-8 is replaced, and the byte order mark removed, before a file is read.

## HTML escaped by construction

Two methods write HTML: `CueText::toHtml()`, for a player's interactive transcript, and `Html\HtmlTranscriptWriter`.
Neither copies markup from the source:

- every run of text and every attribute value goes through `htmlspecialchars()`, with `ENT_QUOTES`, `ENT_HTML5` and
  `ENT_SUBSTITUTE`;
- `toHtml()` writes only `b`, `i`, `u`, `ruby`, `rt`, `span` and `br`; a class span keeps the classes that are plain
  tokens of letters, digits, `-` and `_`, a language span a language tag of letters, digits and `-`, a voice its name
  in `data-voice`; every other tag is dropped and the tags left open are closed;
- `HtmlTranscriptWriter` writes the plain text of each cue, never its markup.

A cue text from any source can be printed as these methods return it.

## Links of chapters

The `url` and `img` of a Podcasting 2.0 chapter are read through `phil/url`: an address that is not `http` or `https`,
carries credentials, or names an IP address or `localhost`, refuses the file. The library never fetches them.

## What stays with the application

- The size of an upload before it reaches the library, and the limits it gives the readers.
- The `STYLE` blocks of a WebVTT file are CSS from the file's author: an application that serves the file to browsers
  as it is keeps them, and one that styles its own player drops them with `StylePolicy::Drop`.
- The HTML fragments go inside the application's page and its Content Security Policy.
