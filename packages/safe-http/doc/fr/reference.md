<!-- phil-dev source-sha256=2a62c9efc988da8dce8ffe23a2606c3c371fc52522108f45644b93d0a5b17507 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\SafeHttp\SafeHttpClient` | `final readonly class`, réinitialisable | Lit une adresse dans ses limites, derrière la garde : `get()`, `head()`, et `send()` d'une méthode quelconque avec des en-têtes et un corps |
| `Phil\SafeHttp\Limits` | `final readonly class` | Le silence, la durée totale, les octets, les redirections, le `User-Agent` et les en-têtes d'une lecture |
| `Phil\SafeHttp\ReadRule` | `final readonly class` | L'en-tête `Accept`, les types de média lus, la marque d'arrêt |
| `Phil\SafeHttp\SafeResponse` | `final readonly class` | La réponse : adresse, redirections, statut, en-têtes, corps |
| `Phil\SafeHttp\AddressGuard` | `final class` | La vérification d'une première adresse et d'une redirection |
| `Phil\SafeHttp\Exception\SafeHttpException` | interface | Toute exception du paquet |
| `Phil\SafeHttp\Exception\RefusedAddress` | `final class` | Une adresse, une redirection, une redirection de HTTPS vers HTTP ou un hôte refusés |
| `Phil\SafeHttp\Exception\TooManyRedirects` | `final class` | Une chaîne de redirections trop longue |
| `Phil\SafeHttp\Exception\FetchFailed` | `final class` | Aucune réponse dans les limites, ou des en-têtes qui les dépassent |
| `Phil\SafeHttp\Exception\RefusedRedirect` | `final class` | Une redirection répondue à une requête qui n'en suit aucune |
| `Phil\SafeHttp\Exception\InvalidRequest` | `final class` | Une méthode, un en-tête ou un corps que `send()` refuse |
| `Phil\SafeHttp\Exception\InvalidLimits` | `final class` | Des limites ou un en-tête hors bornes |
