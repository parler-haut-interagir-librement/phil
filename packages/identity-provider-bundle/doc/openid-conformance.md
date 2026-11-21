# OpenID conformance

The OpenID Foundation publishes a conformance suite that plays a client against a provider and checks every behaviour
of the specifications. `tests/Conformance` runs it in Docker against the provider of this bundle, from nothing, with one
command, and fails on any failure and on any warning or skip it does not explain.

## The profiles

| Plan of the suite | Profile | Played |
|---|---|---|
| `oidcc-basic-certification-test-plan`, discovery, static clients | Basic OP: the authorization code flow, userinfo, `prompt`, `max_age`, code reuse, refresh tokens, and PKCE (`oidcc-ensure-request-with-valid-pkce-succeeds`) | Yes |
| `oidcc-config-certification-test-plan` | Config OP: the discovery document and the key set | Yes |
| `oidcc-rp-initiated-logout-certification-test-plan`, `code`, static clients | RP-Initiated Logout OP | Yes |
| `oidcc-dynamic-certification-test-plan` | Dynamic OP | No: it registers its clients by dynamic client registration, which the provider does not offer, and plays the hybrid flow (`code id_token`), which it refuses. |
| Implicit, hybrid, form post, front- and back-channel logout, third-party initiated login | — | No: the provider answers the code flow in the query response mode only, and offers neither those logout channels nor that login. |

The suite has no plan of its own for PKCE at a provider outside the FAPI profiles: the Basic plan plays a request with
a valid S256 challenge, and the bundle's unit and functional tests cover the refusals — a missing, `plain` or malformed
challenge, a wrong verifier, a verifier for a code issued without a challenge.

## Running it

```sh
packages/identity-provider-bundle/tests/Conformance/run.sh        # from the monorepo
packages/identity-provider-bundle/tests/Conformance/run.sh down   # stops and removes the containers
```

Docker, `git` and `curl` are needed, and port 8443 of the host must be free. `run.sh`:

1. fetches the scripts that drive the suite from its repository, at the release of its images
   (`PHIL_CONFORMANCE_SUITE_VERSION`, `release-v5.3.4` by default), into `var/conformance/suite`;
2. starts `compose.yaml`: MongoDB, the suite's server and nginx from their published images, and the provider — the
   application of `App/ConformanceKernel.php`, served by FrankenPHP with a certificate of its own at
   `https://op.phil.test` —, the monorepo mounted read-only;
3. runs `bin/setup.php` in the provider: fresh tables on SQLite, a first signing key, a test account and two
   confidential clients registered with `--pkce-optional`, as the Basic profile plays clients without PKCE, all with
   secrets generated for this run — the application sets `oidc.require_nonce_without_pkce: false`, since
   `oidcc-ensure-request-without-nonce-succeeds-for-code-flow` plays such a client without a `nonce`; it writes the configuration of the plans, with the steps the suite's browser
   follows on the sign-in, re-authentication, consent and sign-out pages;
4. plays the three plans and exports each one's log to `var/conformance/results`, one zip per plan.

The suite's own pages stay at `https://localhost.emobix.co.uk:8443` while the containers run, to look at each module.
`expected-warnings.json` and `expected-skips.json` explain the warnings and skips below; anything else fails the run.

Modules that end in `REVIEW` passed every check and wait for a person to look at the screenshot the browser took of a
page the provider showed: the re-authentication page of `prompt=login` and `max_age=1`, the page of a refused request,
the confirmation or the end of a sign-out. They are reviewed by hand before a certification.

## Results

Run on 2026-11-15 with the suite `release-v5.3.4`, PHP 8.5, Symfony 8.2, `web-token/jwt-library` 4.2, RS256 keys.
No module failed.

### Basic OP

| Test module | Result | Successes | Failures | Warnings |
|---|---|---|---|---|
| `oidcc-server` | PASSED | 53 | 0 | 0 |
| `oidcc-response-type-missing` | PASSED | 31 | 0 | 0 |
| `oidcc-userinfo-get` | PASSED | 52 | 0 | 0 |
| `oidcc-userinfo-post-header` | PASSED | 53 | 0 | 0 |
| `oidcc-userinfo-post-body` | PASSED | 51 | 0 | 0 |
| `oidcc-ensure-request-without-nonce-succeeds-for-code-flow` | PASSED | 27 | 0 | 0 |
| `oidcc-scope-profile` | WARNING | 53 | 0 | 1 |
| `oidcc-scope-email` | PASSED | 57 | 0 | 0 |
| `oidcc-scope-address` | SKIPPED | 15 | 0 | 0 |
| `oidcc-scope-phone` | PASSED | 54 | 0 | 0 |
| `oidcc-scope-all` | SKIPPED | 15 | 0 | 0 |
| `oidcc-alternate-happy-flow` | PASSED | 57 | 0 | 0 |
| `oidcc-display-page` | PASSED | 46 | 0 | 0 |
| `oidcc-display-popup` | PASSED | 46 | 0 | 0 |
| `oidcc-prompt-login` | REVIEW | 80 | 0 | 0 |
| `oidcc-prompt-none-not-logged-in` | PASSED | 33 | 0 | 0 |
| `oidcc-prompt-none-logged-in` | PASSED | 79 | 0 | 0 |
| `oidcc-max-age-1` | REVIEW | 82 | 0 | 0 |
| `oidcc-max-age-10000` | PASSED | 82 | 0 | 0 |
| `oidcc-ensure-request-with-unknown-parameter-succeeds` | PASSED | 46 | 0 | 0 |
| `oidcc-id-token-hint` | PASSED | 80 | 0 | 0 |
| `oidcc-login-hint` | PASSED | 47 | 0 | 0 |
| `oidcc-ui-locales` | PASSED | 47 | 0 | 0 |
| `oidcc-claims-locales` | PASSED | 47 | 0 | 0 |
| `oidcc-ensure-request-with-acr-values-succeeds` | WARNING | 47 | 0 | 1 |
| `oidcc-codereuse` | PASSED | 54 | 0 | 0 |
| `oidcc-codereuse-30seconds` | PASSED | 58 | 0 | 0 |
| `oidcc-ensure-registered-redirect-uri` | REVIEW | 22 | 0 | 0 |
| `oidcc-ensure-post-request-succeeds` | PASSED | 46 | 0 | 0 |
| `oidcc-server-client-secret-post` | PASSED | 45 | 0 | 0 |
| `oidcc-unsigned-request-object-supported-correctly-or-rejected-as-unsupported` | SKIPPED | 25 | 0 | 0 |
| `oidcc-claims-essential` | WARNING | 55 | 0 | 1 |
| `oidcc-ensure-request-object-with-redirect-uri` | REVIEW | 24 | 0 | 0 |
| `oidcc-refresh-token` | PASSED | 138 | 0 | 0 |
| `oidcc-ensure-request-with-valid-pkce-succeeds` | PASSED | 47 | 0 | 0 |

The three warnings, all on a `SHOULD` or on what the accounts hold:

- `oidcc-scope-profile`: an account has a name, not the other claims of the `profile` scope (`given_name`,
  `family_name`, `picture`…);
- `oidcc-ensure-request-with-acr-values-succeeds`: the ID token states the methods proven in `amr` and names no
  authentication context class in `acr`;
- `oidcc-claims-essential`: the `claims` parameter is not supported (`claims_parameter_supported: false`), so `name` is
  returned for the `profile` scope only.

The three skips: the provider knows no `address` scope (`oidcc-scope-address`, `oidcc-scope-all`), and refuses request
objects with `request_not_supported`, which the suite accepts (`oidcc-unsigned-request-object-…`).

### Config OP

| Test module | Result | Successes | Failures | Warnings |
|---|---|---|---|---|
| `oidcc-discovery-endpoint-verification` | PASSED | 34 | 0 | 0 |

### RP-Initiated Logout OP

| Test module | Result | Successes | Failures | Warnings |
|---|---|---|---|---|
| `oidcc-rp-initiated-logout-discovery-endpoint-verification` | PASSED | 8 | 0 | 0 |
| `oidcc-rp-initiated-logout` | PASSED | 68 | 0 | 0 |
| `oidcc-rp-initiated-logout-bad-post-logout-redirect-uri` | REVIEW | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-modified-id-token-hint` | REVIEW | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-id-token-hint` | REVIEW | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-params` | REVIEW | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-post-logout-redirect-uri` | REVIEW | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-no-state` | PASSED | 69 | 0 | 0 |
| `oidcc-rp-initiated-logout-only-state` | REVIEW | 51 | 0 | 0 |
| `oidcc-rp-initiated-logout-query-added-to-post-logout-redirect-uri` | REVIEW | 50 | 0 | 0 |
| `oidcc-rp-initiated-logout-bad-id-token-hint` | REVIEW | 53 | 0 | 0 |

## Certification

Certification by the OpenID Foundation is a separate step: it needs the provider at a public address, the logs
exported by the suite's hosted instance, and the screenshots of the `REVIEW` modules. It comes before the provider is
used in production.
