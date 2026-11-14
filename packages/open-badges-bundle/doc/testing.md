# Testing

## The bundle's tests

`tests/Unit` tests the wiring on the `ContainerBuilder` — every service and argument, the configuration passed on, the
checks that fail the build, the page left out without Twig —, the key factories, the URLs of the status lists, the
securing in both forms, and each controller and the credential check on a fixed key, a frozen clock and lists in
memory, the context files, the limit on the checks of the page with the pass that checks its limiter, and the keys of
other issuers read from profiles played by `MockHttpClient` with pinned addresses — tokens and documents verified,
each refusal and its caching, a private network never reached, the keys an issuer asserts.
`tests/Functional` boots a small application with FrameworkBundle and TwigBundle and requests the profile, the keys,
the status lists and the verification page, with tokens, documents and baked images; its environment `limited` adds
`phil/rate-limit-bundle` and answers the third check of a minute `429`, and reads other issuers, refusing one it does
not trust.

The test kernel names its environments in `getAllowedEnvs()`, so that every build writes the same
`config/reference.php`: the debug container of each environment stays fresh, and a test boots a compiled container
rather than building it again.

From the monorepo root:

```sh
vendor/bin/phpunit packages/open-badges-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

The fuzz target `issuer-profile` (`tests/Fuzz/issuer-profile.php`) feeds the reader of other issuers' profiles with
bodies nobody wrote: a key is found, an Ed25519 key, or refused with `InvalidCredential`, and nothing else escapes. Its
seeds are in `tests/Fuzz/corpus/issuer-profile`.

```sh
composer fuzz -- issuer-profile
```

## Testing an application that issues badges

Give the test environment a fixed seed — `secret: "\x01..."` of 32 bytes, or an environment variable of the test
`.env` — and a frozen clock: the issued credentials are then the same bytes on every run. Verify them with the
`CredentialVerifier` service, which knows the issuer's key, and set the router's `default_uri` so that the URLs of the
status lists in credentials match the site the tests request.
