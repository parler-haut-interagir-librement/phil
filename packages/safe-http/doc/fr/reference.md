<!-- phil-dev source-sha256=c2bcef941b66f0d6d691f5b091534b2cbee400969a4d8e0fcc187f386c2cd4f4 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\SafeHttp\SafeHttpClient` | `final readonly class`, réinitialisable | Lit une adresse dans ses limites, derrière la garde |
| `Phil\SafeHttp\Limits` | `final readonly class` | Le silence, la durée totale, les octets, les redirections et le `User-Agent` d'une lecture |
| `Phil\SafeHttp\ReadRule` | `final readonly class` | L'en-tête `Accept`, les types de média lus, la marque d'arrêt |
| `Phil\SafeHttp\SafeResponse` | `final readonly class` | La réponse : adresse, redirections, statut, en-têtes, corps |
| `Phil\SafeHttp\AddressGuard` | `final class` | La vérification d'une première adresse et d'une redirection |
| `Phil\SafeHttp\Exception\SafeHttpException` | interface | Toute exception du paquet |
| `Phil\SafeHttp\Exception\RefusedAddress` | `final class` | Une adresse, une redirection ou un hôte refusés |
| `Phil\SafeHttp\Exception\TooManyRedirects` | `final class` | Une chaîne de redirections trop longue |
| `Phil\SafeHttp\Exception\FetchFailed` | `final class` | Aucune réponse dans les limites |
| `Phil\SafeHttp\Exception\InvalidLimits` | `final class` | Des limites ou un en-tête hors bornes |
