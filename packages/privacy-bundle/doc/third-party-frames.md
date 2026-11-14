# Third-party frames

A video, a map or a post of another site is a frame that reaches that site, with the visitor's address, and lets it
write its own cookies. `privacy_embed()` holds it until its consent category is granted.

```twig
{{ privacy_embed('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'videos', {
    title: 'The talk of the conference',
    preview: asset('media/talk-still.webp'),
    width: 640,
    height: 360,
}) }}
```

Until the category is granted, the page shows, inside a `figure`:

- the **preview** the site serves itself — a still of the video, a map drawn ahead of time —, never an image of the
  third party, which would reach it before the consent. A preview must be a path of the site: an address elsewhere is
  refused;
- what loading the frame shares and with whom: the title, the provider, the category;
- a button that **grants the category** — the decision `grant`, a plain `POST` back to the page, which then shows every
  frame of that category;
- with JavaScript, a button that **loads this frame only**, without saving a choice;
- a link that opens the content on the provider's site.

Once granted, the frame is an `iframe` with `loading="lazy"`, `referrerpolicy="strict-origin-when-cross-origin"`, the
title as its accessible name and the permissions of `allow`.

| Option | Rule |
|---|---|
| `title` | Required: the frame's name for a screen reader, also its caption. |
| `preview` | A path of this site, such as `/media/still.webp`. |
| `width`, `height` | Pixels, from 1 to 10,000. |
| `allow` | The frame's permissions; by default `accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen`. |
| `provider` | The name shown in place of the host. |

The category must be a category of the consent policy, and the address an HTTPS address of a host name, read by
`Url` of `phil/url` as a browser reads it: no IP address, no `localhost`, no credentials, and at most 2,048 characters.
Anything else fails the rendering. A necessary category is always granted.

## The versions that write fewer trackers

`privacy_embed()` turns the address into the version of the service that writes less in the browser, when there is
one, with `CookielessVideo` of `phil/url`, whose documentation lists the addresses it knows:

| Service | Addresses | Frame |
|---|---|---|
| YouTube | `watch?v=`, `youtu.be/`, `embed/`, `shorts/`, `live/`, `v/`, on `youtube.com`, `youtu.be` and `youtube-nocookie.com` and their subdomains | `https://www.youtube-nocookie.com/embed/<id>`, with `start` from `t` or `start` (`90`, `90s`, `1h2m3s`) |
| Vimeo | `vimeo.com/<id>`, `vimeo.com/<id>/<hash>`, `player.vimeo.com/video/<id>` | `https://player.vimeo.com/video/<id>?dnt=1`, the hash of an unlisted video kept as `h` |

The YouTube player without cookies writes nothing until the video is played, then keeps its settings in the browser's
storage; Vimeo's `dnt=1` drops its session data. **Neither is free of the third party**: the frame still reaches it,
with the visitor's address, which is why the frame stays behind its category whatever the address. Declare what the
player writes in the cookie inventory ([cookie-inventory.md](cookie-inventory.md)).

A map has no such version. Serve a still of it as the preview, and let the person load the live map.

Any other address is kept as given, its host as the provider.

## With JavaScript

The Stimulus controller `phil--privacy-bundle--embed` shows the button that loads one frame, and replaces the preview
with the frame when the person presses it, or when the event `phil--privacy-bundle--consent:changed` grants the
category anywhere on the page — from the banner, for instance. The frame waits in a `<template>`, so that nothing is
fetched before.

Allow the hosts of your frames in the `frame-src` of your content security policy.

Override `@PhilPrivacy/embed/frame.html.twig` for your markup; its texts are in the `phil_privacy` domain.
