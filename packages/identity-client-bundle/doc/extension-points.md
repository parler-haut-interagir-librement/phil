# Extension points

What an application implements, configures, replaces or relies on.

## Ports

Each is an alias the application points at a service of its own.

| Port | Shipped | What the application's own does |
|---|---|---|
| `UserProvisioner` | `StatelessUserProvisioner` | Creates and finds the accounts; see [Provisioning](provisioning.md) |
| `ReturnToPolicy` | `DeclaredOriginsReturnToPolicy` | Another rule for where a sign-in lands; returns null to discard |
| `ReplayStore` | `CacheReplayStore` | Keeps accepted `jti` elsewhere; `claim()` stays atomic across servers |
| `RefreshOutcomes` | `EncryptedRefreshOutcomes` | Shares refresh results elsewhere; the outcome holds tokens, so it must not be stored in clear |

## Events

Dispatched on the application's event dispatcher. They carry identifiers only: subject, provider session id, level names.

| Event | When |
|---|---|
| `UserSignedIn` | A sign-in completed |
| `ProviderSessionEnded` | A logout token was accepted |
| `LocalSessionEnded` | A local session was invalidated, with its `LocalSessionEndReason` |
| `ElevationRequested` | The browser was sent back for a level |
| `ElevationRefused` | The provider did not reach it |
| `TokensRefreshed` | The provider rotated the tokens |
| `RefreshTokenRejected` | The provider refused the refresh token |
| `ReturnToDiscarded` | A `return_to` was refused |

Symfony's `OidcAuthorizationRequestEvent` remains available to compute other parameters per request.

## Configuration

| Option | What it extends |
|---|---|
| `claims` | What reaches the provisioner |
| `authorization_params` | Extra parameters of each authorization request |
| `elevation.levels` | The assurance scale |
| `return_to.origins` | Where a sign-in may land |
| `paths` | Every route and landing page |

## Services

| Service | How an application relies on it |
|---|---|
| `security.authenticator.oidc_login.token_refresher.<firewall>` | Symfony's refresher, to force a renewal |
| `OIDC_ASSURANCE:<level>` | The attribute of `AssuranceVoter`, in `#[IsGranted]` or `is_granted()` |
