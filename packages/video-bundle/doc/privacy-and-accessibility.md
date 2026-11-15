# Privacy and accessibility

## What reaches a platform

Nothing, until the viewer presses the button of a linked video. The facade is the site's image and the site's text; the
platform's player then loads from the address that writes no cookies, which still reaches the platform with the
viewer's address — the sentence under the video says so before the press. The frame has a strict referrer policy and a
sandbox. A viewer without JavaScript follows a link to the platform, knowingly.

A hosted video comes from the site, or from hosts the site lists for `image_origin()`.

## What is counted

Only with the viewer's consent, asked of the application's consent manager when the page is drawn and again when a
viewing arrives; with tracking off, nothing. What is kept is an aggregate heat map: a hundred counts per video, and the
number of viewings. No viewing is kept, no viewer named; `VideoProgressed` says what was watched, not by whom.

## What the player owes its viewers

- The controls are the browser's, reached by the keyboard; nothing plays before the viewer asks (WCAG 1.4.2, 2.2.2).
- Captions first among the tracks, and the default one shown at once (WCAG 1.2.2); descriptions for what is only shown
  (WCAG 1.2.5).
- A transcript beside the video, in its language (WCAG 1.2.8), and the chapters as a table of contents, both reached by
  the keyboard and, for a hosted video, links to their instants without JavaScript.
- The button of a facade says what it plays and what it loads; the frame it opens has the video's title.
- The current chapter and cue are marked with `aria-current`, not by colour alone.
