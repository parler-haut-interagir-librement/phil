<!-- phil-dev source-sha256=372a12b4e8dcd438fd4969846dec9cbf1611eb46cb5c050239357c7e6beb235b -->
# Référence

| Classe | Rôle |
|---|---|
| `PhilOpenBadgesBundle` | La configuration, vérifiée à la construction du conteneur, et le câblage. |
| `Application\Securing` | Sécurise les attestations de l'émetteur selon la configuration. |
| `Application\SecuredCredential` | Une attestation sécurisée : son contenu et son type de média. |
| `Application\Contract\StatusLists` | Le port des listes d'état de l'application. |
| `Application\StatusListContent` | Les index activés et l'objet d'une liste. |
| `Infrastructure\Routing\StatusListUrls` | L'URL d'une liste, l'entrée d'une attestation révocable, la liste d'une entrée, lues par `phil/url`. |
| `Infrastructure\Http\IssuerController` | Le profil et le JSON Web Key Set. |
| `Infrastructure\Http\StatusListController` | Une liste d'état sous forme d'attestation sécurisée. |
| `Infrastructure\Http\VerificationController` | La page de vérification. |
| `Infrastructure\Http\CredentialCheck`, `Infrastructure\Http\CheckResult` | Ce que la page vérifie, et ce qu'elle affiche. |
| `Infrastructure\Verification\RemoteVerificationMethods` | Les clés d'autres émetteurs, lues dans leurs profils ou leurs documents DID par `phil/safe-http` et mises en cache. |
| `Infrastructure\Http\VerificationRateLimit` | La règle `_rate_limit` donnée à une vérification de la page quand un limiteur est configuré. |
| `Infrastructure\DependencyInjection\VerificationRateLimitPass` | Arrête la construction quand ce limiteur n'est pas déclaré. |
| `Infrastructure\DependencyInjection\Contexts` | Interne : les contextes JSON-LD lus dans les fichiers de l'application. |
| `Infrastructure\DependencyInjection\OpenBadgesServices`, `Infrastructure\DependencyInjection\Keys` | Interne : les services et les fabriques des services de la clé. |

| Route | Chemin | Fichier |
|---|---|---|
| `phil_open_badges_issuer` | `/issuer` | `config/routes.php` |
| `phil_open_badges_issuer_keys` | `/issuer/keys` | `config/routes.php` |
| `phil_open_badges_status_list` | `/status/{list}` | `config/routes.php` |
| `phil_open_badges_verify` | `/verify` | `config/verification_routes.php` |
