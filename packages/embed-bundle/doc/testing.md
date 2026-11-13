# Testing

## The bundle's tests

`tests/Unit` covers origins read by `phil/url`, keys, claims and their bounds, the frame protocol against the fixtures
the JavaScript reads too, embeds and their registry, the token codec — its format, its signature, the ordered keys, an
expired token, every forgery and malformed part —, tokens issued and read back into a context, the themes chosen, the
code a partner pastes and its escaping, the listener and the header on sub-requests and refused tokens, the Flex
recipe, the compiler pass, and the bundle's configuration, services and prepended settings — a secret never in a
message.

`tests/Functional` boots a kernel with FrameworkBundle, Twig and the bundle, then again with
`phil/browser-security-bundle`: the page of an embed by its route and by its attribute, framed by the token's origin
alone, refused framing for a forged token or one of another embed, untouched without a token; the policy of the site
changed rather than doubled, and `X-Frame-Options` dropped; the script under its stable address, readable across
origins, cached and revalidated; the code of an embed; the token command.

`tests/js` runs the protocol's module under Node's test runner against the shared fixtures.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/embed-bundle/tests
node --test tests/js     # from packages/embed-bundle
```

Every line of `src/` is held by the mutation bar of the monorepo: each mutant is killed.

## Fuzzing

The target `embed-token` reads texts nobody signed: the decoder refuses them all, and never throws anything but
`InvalidEmbedToken`. The target `embed-message` reads texts nobody wrote: the reference gives a message or refuses it,
and a message it gives reads back as itself.

```sh
composer fuzz -- embed-token embed-message --budget=60
```

## In a browser

`tests/Browser` drives Chrome with Panther across two origins, both served by PHP's built-in server: a partner's page on
`localhost:9188` holds the code of an embed and a forged one; the functional suite's application on `localhost:9189`
answers the embedded page and the script. The frame shows once ready at the height of its content and follows it as it
grows and shrinks; the theme the partner asks for reaches the frame, a theme the embed lacks does not; a message from
elsewhere is ignored; the forged embed is refused framing and keeps its link. It needs Stimulus from npm and the
chromedriver of the system, and runs apart, like Infection:

```sh
composer test:browser
```
