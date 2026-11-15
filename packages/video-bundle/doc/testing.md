# Testing

## The package's tests

```sh
vendor/bin/phpunit packages/video-bundle/tests
```

The unit suite runs without a kernel: the videos and their refusals, the players of YouTube and Vimeo without cookies,
the viewings and the heat maps, the tracker with and without consent, the stores, every answer of the controller, the
WebVTT served, and the configuration.

The functional suite boots a small application — FrameworkBundle, TwigBundle with its extras, TwigComponentBundle with
the recipes Video, Image and Button copied where `ux:install` would put them, the bundle of phil/ux-kit, and the bundle
with tracking on and a consent the tests switch — and draws a hosted video and a linked one, in English and in French,
then posts a viewing to the route. It is skipped where the event dispatcher is installed for development only.

The package is held to 100 % covered mutation score by Infection.

## Fuzzing

| Target | What it fuzzes |
|---|---|
| `linked-url` | `LinkedVideo::fromUrl()`: a video of YouTube or Vimeo without cookies, or `InvalidVideo` |
| `progress` | `ProgressController` on any body: `204`, `400` or `413` |

```sh
composer fuzz -- linked-url progress
```

## The controller

```sh
node --import ./tests/js/register.mjs --test tests/js
```

The controller runs under Node's test runner, Stimulus resolved to a stub: the parts of an instant, the viewing sent
only with consent and only once, the page hidden, the HLS stream where it plays, seeking, and the facade loaded only
when pressed.
