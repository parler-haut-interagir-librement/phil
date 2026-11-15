# Reference

Every public type of the package, with its role. A type marked internal is not part of the public API.

## Types

| Type | Kind | Role |
|---|---|---|
| `Phil\Lti\Contract\AccessTokens` | `interface` | The access tokens the platform granted. |
| `Phil\Lti\Contract\ContentItem` | `interface` | A content item of Deep Linking 2.0, picked in the tool and sent back to the platform. |
| `Phil\Lti\Contract\KeySetSource` | `interface` | Where the key set of the other side is read from, by its address. |
| `Phil\Lti\Contract\KeyStore` | `interface` | The keys of this side: the current one, which signs, and the key set it publishes — the current key with the keys of the earlier rotations still in their overlap, so that a token signed just before a rotation verifies. |
| `Phil\Lti\Contract\LineItemStore` | `interface` | The line items the platform keeps, each the tool's that created it or that it was created for. |
| `Phil\Lti\Contract\LoginStates` | `interface` | The login states of the tool. A state is taken once: the second launch with the same state finds nothing. |
| `Phil\Lti\Contract\Nonces` | `interface` | Values that may be used once: the nonce of an `id_token`, of a deep linking response, the `jti` of a client assertion. Each is remembered until it expires; a value seen again before then is a replay. |
| `Phil\Lti\Contract\PlatformRegistrations` | `interface` | The platforms registered with the tool, and their deployments. |
| `Phil\Lti\Contract\Roster` | `interface` | The contexts of the platform and their members, as the application keeps them: the platform's services read them for a tool, and only in the contexts where the tool is placed. |
| `Phil\Lti\Contract\ScoreStore` | `interface` | The latest score of each learner on each line item. |
| `Phil\Lti\Contract\ServiceClient` | `interface` | Sends requests to the services of the other side — its token endpoint, its grades, its roster —, which need a body and an `Authorization` header. |
| `Phil\Lti\Contract\ToolRegistrations` | `interface` | The tools registered with the platform, and their deployments. |
| `Phil\Lti\DeepLinking\ContentItems` | `final class` | Reads the content items claim of a deep linking response into typed items. An item of a type this package does not know is skipped, as the specification lets a platform do. |
| `Phil\Lti\DeepLinking\FileItem` | `final readonly class` | A file for the platform to download and keep, before its address expires. |
| `Phil\Lti\DeepLinking\HtmlItem` | `final readonly class` | A fragment of HTML for the platform to embed. The platform decides what of it to keep: a platform that reads one sanitises it before showing it. |
| `Phil\Lti\DeepLinking\Image` | `final readonly class` | An icon or a thumbnail of a content item. |
| `Phil\Lti\DeepLinking\ImageItem` | `final readonly class` | An image for the platform to show. |
| `Phil\Lti\DeepLinking\LineItemRequest` | `final readonly class` | The line item a resource link asks the platform to create with it, when the platform accepts line items. |
| `Phil\Lti\DeepLinking\LinkItem` | `final readonly class` | A plain link to a page, opened by the platform outside LTI. |
| `Phil\Lti\DeepLinking\ResourceLinkItem` | `final readonly class` | A resource link of the tool for the platform to place: launched later as a resource link launch, at its address or the tool's default, with its custom parameters and, when asked, a line item. |
| `Phil\Lti\Exception\AccessDenied` | `final class` | A call to a service of the platform without a valid access token, or with one that lacks the scope the call needs. |
| `Phil\Lti\Exception\InvalidData` | `final class` | A document of a service — a line item, a score, a result, a membership page, a content item — whose shape or values the specification does not allow. |
| `Phil\Lti\Exception\InvalidRequest` | `final class` | A login initiation, an authentication request or a launch whose parameters are missing, malformed or do not match what was registered or remembered. |
| `Phil\Lti\Exception\InvalidToken` | `final class` | A JSON Web Token, or one of its claims, that is malformed, unsigned by the expected key, expired, replayed or addressed to someone else. The message says which check failed; it never repeats the token. |
| `Phil\Lti\Exception\LtiException` | `interface` | Every exception of the package: catch it to handle any failure of a launch, a message or a service call. |
| `Phil\Lti\Exception\ServiceFailed` | `final class` | A call to a service of the other side — its key set, its token endpoint, its grades or its roster — that could not be made, or that answered with an error. |
| `Phil\Lti\Exception\TokenRequestRefused` | `final class` | A request to the token endpoint of the platform that is refused, with the error code of RFC 6749, section 5.2: `invalid_request`, `invalid_client`, `invalid_grant`, `unauthorized_client`, `unsupported_grant_type` or `invalid_scope`. |
| `Phil\Lti\Exception\UnknownRegistration` | `final class` | A platform, a tool or a deployment that is not registered. |
| `Phil\Lti\Http\FormPost` | `final readonly class` | A form the browser posts to the other side, as LTI sends an `id_token` or a deep linking response: the address and the fields, rendered by the application as a form that submits itself. |
| `Phil\Lti\Http\LinkHeader` | `final class` | Reads the `Link` header of RFC 8288, as the services page their lists: the address of a relation — `next`, `differences`, `first`, `last` —, resolved against the address of the request when it is relative. A link whose address is not one is passed over. |
| `Phil\Lti\Http\SafeHttpServiceClient` | `final readonly class` | Sends the requests of the services — the token endpoint, Assignment and Grade Services, Names and Role Provisioning Services — through `SafeHttpClient::send()` of `phil/safe-http`: HTTPS only, every host checked against private and reserved networks, no proxy of the environment, the headers kept on the origin of the address, the silence of the host, the whole duration and the bytes of the answer bounded. By default no redirect is followed and an answer is read up to 4 MiB; a client of other limits is given to the constructor. |
| `Phil\Lti\Http\ServiceRequest` | `final readonly class` | A request to a service of the other side. |
| `Phil\Lti\Http\ServiceResponse` | `final readonly class` | The answer of a service: its status, its headers by lowercase name, and its body. |
| `Phil\Lti\Http\UrlPath` | `final class`, internal | Adds a segment to the path of an address, keeping its query: the scores of a line item are at `{lineitem}/scores`, whatever query the line item's address carries. |
| `Phil\Lti\Http\UrlQuery` | `final class`, internal | Adds parameters to the query of an address, keeping those it has, without its fragment. |
| `Phil\Lti\Jwt\Base64Url` | `final class` | URL-safe base 64 without padding, as RFC 7515 writes the parts of a token; strict when it reads. |
| `Phil\Lti\Jwt\Jwt` | `final readonly class` | A compact JSON Web Signature read but not yet verified: its header, its claims, the bytes it signs and its signature. Only the form is checked here: three parts, each in URL-safe base 64, the first two JSON objects of bounded size and depth, an algorithm named in the header. |
| `Phil\Lti\Jwt\JwtSigner` | `final class` | Signs claims as a compact JSON Web Signature with `RS256` through `web-token/jwt-library`, `typ`, `alg` and the key identifier in the protected header. |
| `Phil\Lti\Jwt\JwtVerifier` | `final readonly class` | Verifies a token signed by the other side: its form is read within bounds by `Jwt::parse()` first; the algorithm is `RS256`, whatever the token says; the key is the one its `kid` names in the key set of the other side, read again once when the cached copy does not hold it; the signature verifies through `web-token/jwt-library`; and the times hold, by the clock and a leeway of a few seconds for clocks that drift: `exp` is in the future, `iat` is not in the future nor older than the longest age, `nbf`, when present, is past. |
| `Phil\Lti\Key\CachedKeySetSource` | `final readonly class` | Keeps the key sets another source reads in a cache, for an hour by default. A refresh reads a set again, at most once a minute per address by default, so that tokens naming unknown keys cannot make the side read the set at every request. |
| `Phil\Lti\Key\InMemoryKeyStore` | `final readonly class` | Keys held in memory: a current key and, published beside it, the public keys of earlier rotations. For tests, and for an application that reads its keys from its configuration. |
| `Phil\Lti\Key\KeySet` | `final readonly class` | A JSON Web Key Set of RSA signing keys, as a side publishes it. Reading one keeps the RSA keys for `RS256` and skips the others — keys for encryption, of another type or algorithm —, so that a set shared with other uses still serves. |
| `Phil\Lti\Key\RsaPrivateKey` | `final readonly class` | An RSA private key of 2048 to 8192 bits that signs with `RS256`, under a key identifier published with its public half in the key set of its side: a private JSON Web Key of `web-token/jwt-library`. A dump of the object shows its identifier and its public half, never the private members. |
| `Phil\Lti\Key\RsaPublicKey` | `final readonly class` | An RSA public key of 2048 to 8192 bits that verifies `RS256` signatures, the algorithm of LTI 1.3, with its key identifier: a JSON Web Key of `web-token/jwt-library`, read from a JSON Web Key or from PEM once its type, its size, its exponent, its algorithm and its use are checked, written back as a JSON Web Key for a key set. |
| `Phil\Lti\Key\SafeHttpKeySetSource` | `final readonly class` | Reads key sets through `phil/safe-http`: HTTPS only, private networks refused, redirects checked, time and bytes bounded. Each call reads the set again: wrap it in `CachedKeySetSource`. |
| `Phil\Lti\Message\AgsEndpoint` | `final readonly class` | The claim of the Assignment and Grade Services in a launch: the scopes the tool may ask for, the line items of the context and, when the resource link has one, its own line item. |
| `Phil\Lti\Message\Claim` | `final class` | The names of the claims of LTI 1.3 and of its services. |
| `Phil\Lti\Message\Context` | `final readonly class` | The context of a launch — a course, a section, a group — on the platform. |
| `Phil\Lti\Message\ContextRole` | `enum` | The principal roles of a person in a context — a course, a group —, by the vocabulary of LIS that LTI 1.3 uses. |
| `Phil\Lti\Message\DeepLinkingSettings` | `final readonly class` | What a deep linking request accepts back: where to return, the types of content items, the presentations, whether several items and line items are accepted, and the opaque data the tool must echo. |
| `Phil\Lti\Message\DocumentTarget` | `enum` | Where the platform shows the tool. |
| `Phil\Lti\Message\InstitutionRole` | `enum` | The roles of a person in the institution of the platform, by the vocabulary of LIS. |
| `Phil\Lti\Message\Launch` | `final readonly class` | A launch the tool verified: the platform that sent it, by its issuer and the client it gave the tool, and the message. `claims` keeps every claim of the token, those of other specifications included, for what this package does not type. |
| `Phil\Lti\Message\LaunchMessage` | `final readonly class` | The claims of LTI that a launch carries, typed: what the platform builds and signs, what the tool reads once the token verifies. A resource link launch names its resource link; a deep linking launch carries its settings. |
| `Phil\Lti\Message\LaunchPresentation` | `final readonly class` | How the platform presents the tool: where, at what size, in which language, and where to send the person back. |
| `Phil\Lti\Message\MessageType` | `enum` | The messages of LTI 1.3 Core and Deep Linking 2.0. |
| `Phil\Lti\Message\NrpsEndpoint` | `final readonly class` | The claim of the Names and Role Provisioning Service in a launch: where the members of the context are listed. |
| `Phil\Lti\Message\Person` | `final readonly class` | The person a launch is for: the subject, the stable identifier the platform gives them for this tool, and what the platform chose to share of their name, email and picture. The subject is null in an anonymous launch. |
| `Phil\Lti\Message\ResourceLink` | `final readonly class` | The resource link a launch opens: the placement of the tool in a context of the platform, stable across launches. |
| `Phil\Lti\Message\Roles` | `final readonly class` | The roles of the person a launch is for, as full URIs. A context role sent by its short name, `Instructor`, is read as the full URI of its vocabulary, as LTI 1.3 still allows; a role of another vocabulary is kept as given. |
| `Phil\Lti\Message\SystemRole` | `enum` | The roles of a person on the platform itself, by the vocabulary of LIS. |
| `Phil\Lti\Message\ToolPlatform` | `final readonly class` | The instance of the platform a launch comes from, as it describes itself. |
| `Phil\Lti\Platform\Ags\AgsService` | `final readonly class` | The Assignment and Grade Services of the platform, for a tool whose access token grants the scope of each operation, in a context where the tool is placed, on the line items that are its own. A score older than the learner's latest on the line item is not kept, as the specification asks; results scale the latest score to the line item's maximum. |
| `Phil\Lti\Platform\Ags\InMemoryLineItemStore` | `final class` | Line items held in one process, for tests. |
| `Phil\Lti\Platform\Ags\InMemoryScoreStore` | `final class` | Scores held in one process, for tests. |
| `Phil\Lti\Platform\Ags\StoredLineItem` | `final readonly class` | A line item the platform keeps: its identifier, the tool it belongs to, its context and its fields. Its address is made by the application from its identifier. |
| `Phil\Lti\Platform\AuthenticationRequest` | `final readonly class` | The OpenID Connect authentication request a tool sends the browser back with, to the platform's authorization endpoint: what LTI fixes — `scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none` — and what it carries — the client, the redirect address, the hints, the `state` and the `nonce`. |
| `Phil\Lti\Platform\DeepLinkingResult` | `final readonly class` | A deep linking response the platform verified: the tool and deployment it comes from, the items picked, the data the platform put in the request, and the messages for the person and for the logs. |
| `Phil\Lti\Platform\DeepLinkingReturn` | `final readonly class` | Reads the deep linking response a tool posts back to the platform, in its `JWT` field: `iss` a registered client, `aud` the platform, signed by the tool's key, `exp` and `iat` holding, the `nonce` used once, the deployment the tool's, the message type and version those of a response; then the content items. |
| `Phil\Lti\Platform\LaunchIssuer` | `final readonly class` | The second step of a launch on the platform: answers the tool's authentication request with the `id_token` — the message signed with the platform's key, `iss` the platform, `aud` and `azp` the client, `sub` the person, the request's `nonce`, five minutes of life — posted by the browser to the redirect address with the `state`. |
| `Phil\Lti\Platform\LaunchStarter` | `final readonly class` | The first step of a launch on the platform: the address of the tool's login initiation the browser is sent to, with the issuer, the hint of the person, the target of the launch, the platform's own hint, the client and the deployment. |
| `Phil\Lti\Platform\Nrps\InMemoryRoster` | `final class` | Contexts and members held in memory, for tests and demonstrations. |
| `Phil\Lti\Platform\Nrps\NrpsService` | `final readonly class` | The Names and Role Provisioning Service of the platform: a page of the members of a context for a tool granted the scope, in a context where it is placed. The page holds `limit` members at most, 100 by default and 1,000 at most; `nextOffset` is where the next page starts, null on the last. |
| `Phil\Lti\Platform\Token\AccessToken` | `final readonly class` | An access token the platform granted a tool: kept by the SHA-256 of its value, never the value itself, with the client, the scopes and the moment it expires. |
| `Phil\Lti\Platform\Token\InMemoryAccessTokens` | `final class` | Access tokens held in one process, for tests. |
| `Phil\Lti\Platform\Token\IssuedToken` | `final readonly class` | The answer of the token endpoint: the value of the token, given once, its lifetime and its scopes. |
| `Phil\Lti\Platform\Token\ServiceGuard` | `final readonly class` | Checks the bearer token of a call to a service of the platform, and the scope the call needs. |
| `Phil\Lti\Platform\Token\TokenEndpoint` | `final readonly class` | The token endpoint of the platform: the grant `client_credentials`, the tool authenticated by a JWT assertion signed with its key (RFC 7523) — `iss` and `sub` its client, `aud` the address of the endpoint, `exp` and `iat` holding, a `jti` used once —, the scopes granted those it asks for that are known and registered for it. The token is a random value given once and kept by its hash, for an hour by default. |
| `Phil\Lti\Registration\InMemoryPlatformRegistrations` | `final class` | Platforms registered in memory, from the configuration of the application or in tests. |
| `Phil\Lti\Registration\InMemoryToolRegistrations` | `final class` | Tools registered in memory, from the configuration of the application or in tests. |
| `Phil\Lti\Registration\PlatformRegistration` | `final readonly class` | A platform registered with the tool: its issuer, the client it gave the tool, and its endpoints — where the tool sends the person to authenticate, where it asks for access tokens, and where its keys are published. |
| `Phil\Lti\Registration\ToolRegistration` | `final readonly class` | A tool registered with the platform: the client the platform gave it, where its login initiation is, the addresses the platform may post a launch to, where its keys are published, and the scopes of the services it may ask for. |
| `Phil\Lti\Security\InMemoryLoginStates` | `final class` | Login states held in one process, for tests. |
| `Phil\Lti\Security\InMemoryNonces` | `final class` | Nonces remembered in one process, for tests. |
| `Phil\Lti\Security\LoginState` | `final readonly class` | What the tool remembers between the login initiation and the launch: the `state` it sent, the `nonce` the `id_token` must carry, the platform it asked, and the target of the launch. |
| `Phil\Lti\Security\Random` | `final class`, internal | Unpredictable values for states, nonces, token identifiers and access tokens: 32 random bytes in URL-safe base 64. |
| `Phil\Lti\Service\AccessTokenProvider` | `final readonly class` | The access tokens of the tool for the services of a platform: the grant `client_credentials`, the tool authenticated by a JWT signed with its key (RFC 7523) — `iss` and `sub` the client, `aud` the token endpoint, a fresh `jti`, five minutes of life —, the token kept in a cache per platform and set of scopes until shortly before it expires. |
| `Phil\Lti\Service\Ags\ActivityProgress` | `enum` | Where the learner is in the activity a score is about. |
| `Phil\Lti\Service\Ags\AgsClient` | `final readonly class` | The Assignment and Grade Services of a platform, called by the tool with the endpoint claim of a launch: the line items of the context, read, created, changed and deleted; the scores sent; the results read. Each call asks for the scope it needs, when the claim grants it. |
| `Phil\Lti\Service\Ags\GradingProgress` | `enum` | Where the grading of a score is: only a `FullyGraded` score is final for the platform's gradebook. |
| `Phil\Lti\Service\Ags\LineItem` | `final readonly class` | A column of the gradebook of a context: what it is graded out of, its label, and what it belongs to — a resource link of the platform, a resource of the tool, a tag. Its `id` is its address, given by the platform. |
| `Phil\Lti\Service\Ags\LineItemPage` | `final readonly class` | A page of the line items of a context, and the address of the next page when there is one. |
| `Phil\Lti\Service\Ags\MediaType` | `final class` | The media types of the Assignment and Grade Services. |
| `Phil\Lti\Service\Ags\Result` | `final readonly class` | The result of a learner on a line item, as the platform's gradebook holds it. |
| `Phil\Lti\Service\Ags\Score` | `final readonly class` | A score the tool sends for a learner on a line item: the score given out of a maximum, when there is one yet, where the learner and the grading are, and the moment the score was made, by which the platform keeps the latest. |
| `Phil\Lti\Service\Nrps\MediaType` | `final class` | The media type of the Names and Role Provisioning Service. |
| `Phil\Lti\Service\Nrps\Member` | `final readonly class` | A member of a context: the identifier the platform gives them for the tool — the `sub` of their launches —, their roles and status, and what the platform chose to share of their name, email and picture. |
| `Phil\Lti\Service\Nrps\MemberStatus` | `enum` | The status of a member of a context. |
| `Phil\Lti\Service\Nrps\MembershipPage` | `final readonly class` | A page of the members of a context, the address of the next page when there is one, and the address of the changes since this read, when the platform offers it. |
| `Phil\Lti\Service\Nrps\NrpsClient` | `final readonly class` | The Names and Role Provisioning Service of a platform, called by the tool with the claim of a launch: the members of the context, a page at a time, the next page found in the `Link` header. |
| `Phil\Lti\Service\Scope` | `final class` | The scopes of LTI Advantage that a tool asks the token endpoint for. |
| `Phil\Lti\Service\ServiceCall` | `final readonly class`, internal | A call of the tool to a service of a platform with an access token of the scope it needs, its JSON read. |
| `Phil\Lti\Support\Fields` | `final readonly class`, internal | Typed reads of the members of a JSON object — the claims of a token, a document of a service —, each bounded: a string of 4,096 bytes at most by default, a list of 256 entries at most. A member of the wrong type fails with `InvalidToken` in a token and `InvalidData` in a document, naming the member. |
| `Phil\Lti\Tool\DeepLinkingResponder` | `final readonly class` | The answer of the tool to a deep linking request: the content items picked, signed with the tool's key as an `LtiDeepLinkingResponse` for the platform, with the opaque data of the request echoed, posted by the browser to the return address of the request. The items must be of the types the request accepts, one at most unless it accepts several; an empty list says nothing was picked. |
| `Phil\Lti\Tool\LaunchValidator` | `final readonly class` | The second step of a launch on the tool: the `id_token` and the `state` the platform posts back. |
| `Phil\Lti\Tool\LoginInitiator` | `final readonly class` | The first step of a launch on the tool: answers the platform's login initiation with an OpenID Connect authentication request — `scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none` —, a fresh `state` and `nonce` remembered for the launch, for a few minutes. |
| `Phil\Lti\Tool\LoginRedirect` | `final readonly class` | Where the tool sends the browser after a login initiation — the authorization endpoint of the platform with the authentication request in its query —, and the state it remembered. |
| `Phil\Lti\Tool\LoginRequest` | `final readonly class` | The third-party initiated login a platform sends to the tool, by GET or POST: the issuer, the hint of the person, the target of the launch, and, when the platform gives them, its own hint, the client and the deployment. |

## Constants

| Constant | Value | Meaning |
|---|---|---|
| `Jwt::MAX_LENGTH` | `65536` | The longest token read, in bytes. |
| `Jwt::MAX_DEPTH` | `16` | The deepest nesting of the JSON of a part. |
| `JwtVerifier::DEFAULT_LEEWAY` | `5` | The seconds of leeway for clocks that drift. |
| `JwtVerifier::DEFAULT_MAX_AGE` | `600` | The oldest token accepted, in seconds after its `iat`. |
| `RsaPublicKey::MIN_BITS`, `MAX_BITS` | `2048`, `8192` | The bounds of a key. |
| `RsaPrivateKey::DEFAULT_BITS` | `2048` | The bits of a generated key. |
| `RsaPrivateKey::MAX_JSON_BYTES`, `MAX_JSON_DEPTH` | `65536`, `4` | The bounds of the JSON of a private key read by `fromJson()`. |
| `KeySet::MAX_KEYS`, `MAX_BYTES` | `32`, `262144` | The bounds of a key set read. |
| `CachedKeySetSource::DEFAULT_LIFETIME` | `3600` | The seconds a key set is kept. |
| `CachedKeySetSource::DEFAULT_REFRESH_INTERVAL` | `60` | The fewest seconds between two reads of one key set. |
| `LoginInitiator::DEFAULT_STATE_LIFETIME` | `600` | The seconds between a login and its launch. |
| `LaunchIssuer::LIFETIME`, `DeepLinkingResponder::LIFETIME` | `300` | The seconds an `id_token` or a deep linking response lives. |
| `TokenEndpoint::DEFAULT_LIFETIME` | `3600` | The seconds an access token lives. |
| `AccessTokenProvider::MARGIN` | `30` | The seconds a cached access token is dropped before it expires. |
| `AccessTokenProvider::ASSERTION_TYPE` | `urn:ietf:params:oauth:client-assertion-type:jwt-bearer` | The client assertion type of RFC 7523. |
| `LaunchMessage::MAX_CUSTOM` | `100` | The most custom parameters read. |
| `ContentItems::MAX_ITEMS` | `100` | The most content items read from a response. |
| `HtmlItem::MAX_BYTES` | `65536` | The largest HTML of a content item. |
| `NrpsClient::MAX_PAGES`, `MAX_MEMBERS_PER_PAGE` | `100`, `1000` | The bounds of a read of the members. |
| `AgsService::DEFAULT_LIMIT`, `MAX_LIMIT`; `NrpsService::DEFAULT_LIMIT`, `MAX_LIMIT` | `100`, `1000` | The size of a page the platform answers. |
| `SafeHttpServiceClient::DEFAULT_MAX_BYTES` | `4194304` | The largest answer of a service read by the default client. |
| `SafeHttpServiceClient::USER_AGENT` | `PhilLti/0.1` | The `User-Agent` of the default client. |
| `LinkHeader::MAX_LENGTH` | `8192` | The longest `Link` header read. |
| `Claim::LTI_VERSION` | `1.3.0` | The version every message carries. |

## Exceptions

| Exception | Thrown when |
|---|---|
| `LtiException` | The interface of every exception of the package. |
| `InvalidToken` | A token, or a claim, is malformed, unsigned by the expected key, expired, replayed or for someone else. |
| `InvalidRequest` | A login initiation, an authentication request or a launch is missing a parameter or does not match. |
| `InvalidData` | A document of a service — a line item, a score, a membership — is not what the specification allows. |
| `UnknownRegistration` | A platform, a tool or a deployment is not registered. |
| `ServiceFailed` | A key set or a service cannot be reached, or answers an error; `status()` gives the HTTP status. |
| `TokenRequestRefused` | The token endpoint refuses a request; `error` is the code of RFC 6749. |
| `AccessDenied` | A call to a service of the platform lacks a valid token, or its scope. |
