<!-- phil-dev source-sha256=7dc0e176664953e741d5b2ad5300a8d58dc3b37ed122a1828b49c9d1159916a4 -->
# Configuration

Chaque option de `phil_identity_provider_google`, avec sa valeur par défaut.

```yaml
phil_identity_provider_google:
    enabled: true
    client_id: ~                    # required
    client_secret: ~                # required
    hosted_domains: []
    profile: false
    prompt: select_account
    clock_skew: 60
    attempt_lifetime: 600
    cache_pool: cache.app
    http_client: ~
    clock: clock
    risc:
        enabled: false
        audiences: []
        max_age: 604800
        hold_hijacked: true
        holds_pool: ~
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle. |
| `client_id` | obligatoire | L'identifiant client de l'application chez Google, tel que `1234-abc.apps.googleusercontent.com`. |
| `client_secret` | obligatoire | Son secret client, lu dans les secrets de Symfony : `'%env(GOOGLE_CLIENT_SECRET)%'`. Il est envoyé au point de terminaison des jetons de Google par HTTP Basic, et nulle part ailleurs. |
| `hosted_domains` | `[]` | Les domaines Google Workspace acceptés, en minuscules. Vide, tout compte Google se connecte, comptes grand public compris. Avec des domaines, un compte dont la revendication `hd` n'est pas l'un d'eux est refusé ; le paramètre `hd` de la requête d'autorisation est le domaine, ou `*` pour plusieurs, ce qui ne fait que restreindre le sélecteur de comptes de Google. |
| `profile` | `false` | Si le nom de la personne est demandé, avec la portée `profile`, et remis au bundle. Par défaut, seule l'adresse est demandée. |
| `prompt` | `select_account` | Le `prompt` envoyé à Google : `none`, `consent`, `select_account`, ou `default` pour n'en envoyer aucun. |
| `clock_skew` | `60` | Les secondes dont l'horloge de Google peut différer de celle de l'application, de 0 à 300, pour les heures du jeton d'identité. |
| `attempt_lifetime` | `600` | Les secondes pendant lesquelles une connexion attend que Google renvoie la personne, de 60 à 3600. |
| `cache_pool` | `cache.app` | Le bassin de cache des documents de découverte et des clés de Google, et des identifiants des événements de sécurité reçus. |
| `http_client` | aucun | Le client HTTP avec lequel Google est appelé ; phil/safe-http l'enveloppe. Celui de phil/safe-http par défaut. |
| `clock` | `clock` | L'horloge PSR-20 des vérifications de temps. |
| `risc.enabled` | `false` | Si le point de terminaison reçoit les événements de sécurité de Google ; sinon il répond 404. |
| `risc.audiences` | `[]` | Les identifiants client auxquels les événements peuvent être destinés ; l'identifiant client par défaut. |
| `risc.max_age` | `604800` | Les secondes que peut avoir un jeton d'événement de sécurité, de 60 à 30 jours ; son identifiant est gardé aussi longtemps pour refuser un rejeu. |
| `risc.hold_hijacked` | `true` | Si un compte Google désactivé parce qu'il a été détourné ne peut pas se connecter tant que Google ne l'a pas réactivé. |
| `risc.holds_pool` | le bassin de cache | Le bassin PSR-6 des comptes retenus. Il doit conserver ce qu'on lui confie : voir [Cross-Account Protection](risc.md#held-accounts). |

## Ce qui est mis en cache

| Entrée | Durée de vie |
|---|---|
| Le document de découverte de Google | 24 heures |
| Le document de configuration de la Cross-Account Protection | 24 heures |
| Les clés de Google | Aussi longtemps que le `Cache-Control` de Google le dit, 30 jours au plus, une heure quand il ne dit rien ; relues quand un jeton nomme une clé que le cache ne contient pas, au plus une fois par minute |
| L'identifiant d'un jeton d'événement de sécurité reçu | `risc.max_age` |
| Un compte retenu | Jusqu'à ce que Google le réactive |
