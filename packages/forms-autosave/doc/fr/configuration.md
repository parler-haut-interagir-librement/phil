<!-- phil-dev source-sha256=c54f82abe3f697f1cd14ef93b99e51a3e9d5460f00210fb5bf100ff41d94e415 -->
# Configuration

```yaml
# config/packages/phil_forms_autosave.yaml
phil_forms_autosave:
    secret: '%kernel.secret%'   # what keys and owners' hashes are derived with
    ttl: 604800                 # seconds a draft is kept after its last change, in the browser and on the server
    debounce: 800               # milliseconds the controller waits after the last change before it saves
    max_bytes: 262144           # how large a draft the controller keeps in the browser
    server:
        enabled: false          # whether drafts are also kept on the server, for signed-in users
        store: doctrine         # "doctrine", or "memory" for tests
        connection: default     # the Doctrine connection of the table
        encrypt: true           # sealed through Symfony's KeyManagement component; false stores them in clear
        key: ~                  # the master key of the KMS that seals drafts, required while encrypt is true
        kms: ~                  # a client such as key_management.vault; by default, the component's default client
        max_bytes: 65536        # how large a draft the server keeps
        owner: ~                # a DraftOwner service; by default, the user of Symfony Security
```

| Option | Bornes | Effet |
|---|---|---|
| `secret` | non vide | Le changer donne une nouvelle clé à chaque formulaire : les brouillons conservés sous les anciennes clés ne sont plus trouvés |
| `ttl` | 60 à 31 536 000 | Un brouillon plus ancien est retiré quand il est lu dans le navigateur, et purgé sur le serveur |
| `debounce` | 100 à 10 000 | Le contrôleur enregistre quand personne n'a tapé pendant ce délai, et tout de suite quand le formulaire est envoyé ou la page quittée |
| `max_bytes` | 1 024 à 4 194 304 | Un brouillon plus gros n'est pas conservé dans le navigateur, et l'état le dit |
| `server.enabled` | | Active le stockage, ses points de terminaison, sa commande de purge et le mappage de sa table |
| `server.store` | `doctrine`, `memory` | `memory` garde les brouillons dans le processus, vidés entre les requêtes |
| `server.connection` | non vide | La connexion `doctrine.dbal.<name>_connection` |
| `server.encrypt` | | Avec `true`, `symfony/key-management` et une clé maîtresse sont requis ; `false` garde en clair dans la table ce que les utilisateurs ont tapé |
| `server.key` | non vide | La clé maîtresse du KMS qui scelle les brouillons : une clé d'un DSN `sodium://`, un alias, un ARN |
| `server.kms` | non vide | Le service d'un client du composant KeyManagement, comme `key_management.<client>` ; sans lui, le client par défaut |
| `server.max_bytes` | 1 024 à 4 194 304 | Un brouillon plus gros est refusé avec `413` ; un corps plus de deux fois plus gros n'est pas lu |
| `server.owner` | | Un service qui implémente `DraftOwner` ; sans lui, l'utilisateur de Symfony Security quand SecurityBundle est installé |

Le stockage serveur arrête le conteneur avec un message quand la protection CSRF de FrameworkBundle est désactivée,
quand rien ne nomme l'utilisateur connecté, ou quand le chiffrement est activé sans le composant KeyManagement, sans
`server.key` ou sans le client qui la détient.
