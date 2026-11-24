# Extension points

The bundle is in its `0.x` releases: its configuration, ports, events and services may change in a minor release, said
in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Default | Replace it to |
|---|---|---|
| `Application\Contract\SamlAccounts` | none: a `SamlUser` in the session | find and create the accounts of the application; see [Accounts and roles](accounts.md) |
| `Application\Contract\EndpointUrls` | `RoutedEndpoints`, from the router | serve the routes under addresses the router does not write |

## Services

The services are registered under their class names:

- `Phil\SamlSp\Contract\ReplayGuard`: an alias of `Phil\SamlSp\Store\DbalReplayGuard` on the Doctrine DBAL
  connection, or of `Phil\SamlSp\Store\CacheReplayGuard` on `cache_pool`, as `replay_guard` chooses; an application
  that defines this service or alias itself keeps its own guard, which the bundle neither replaces nor checks;
- `Phil\SamlSp\Store\CachePendingRequests` on `cache_pool`: decorate or replace it (see the extension points of
  phil/saml-sp);
- `Phil\SamlSp\Metadata\MetadataLoader` and `Phil\SamlSp\Http\SafeHttpMetadataFetcher`: replace the fetcher to read
  metadata from a store of the application;
- `Phil\SamlSpBundle\Application\IdentityProviders`: the identity providers, their settings, their metadata and the
  service provider each one sees;
- `Phil\SamlSpBundle\Application\SamlLogin`, `SamlLogout` and `SamlUsers`: the sign-in, the logout and the users, for
  an application that builds its own pages around them.

## Events

See [Events and console](events.md).

## Bridging to another sign-in package

The bundle stands on its own: it signs people in with its authenticator and gives the application a `SamlIdentity`. A
package that already gathers several ways to sign in — OpenID Connect providers, social logins — can take SAML as one
more, without the bundle knowing it, in either of two ways:

- an adapter of that package's provider port that sends the person to `phil_saml_sp_login` with a `target` of its own,
  and reads the `SamlLoginSucceeded` event to hand the `SamlIdentity` over, mapped to its own identity type;
- or a `SamlAccounts` implementation that delegates `find()` and `create()` to that package's account linking, so that
  a person signed in through SAML lands on the same account as through any other provider.

Either way, the subject to link on is the key of the identity provider and `SamlIdentity::subject()`.
