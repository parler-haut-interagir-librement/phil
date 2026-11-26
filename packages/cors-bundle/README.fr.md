<!-- phil-dev source-sha256=280cfafb6dbbbbbd16efa9ba6c630d2f4fb26d741d5bed06b8e4ab75d9cf46d9 -->
# PHIL CORS Bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/cors-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/cors-bundle)

**Les navigateurs bloquent les appels entre origines qu'une API n'autorise pas : des règles CORS par chemin pour
Symfony, le préambule répondu tôt, aucun joker avec des identifiants de connexion.**

phil/cors-bundle répond au protocole de partage des ressources entre origines (CORS, Cross-Origin Resource Sharing)
pour une application Symfony. Chaque motif de chemin a sa propre politique : les origines, exactes ou par motif ancré,
les méthodes, les en-têtes de requête, les en-têtes de réponse que la page peut lire, les identifiants de connexion, la
durée pendant laquelle un navigateur garde un préambule, et l'accès au réseau privé des navigateurs qui le demandent.
Le bundle :

- répond aux préambules (*preflight*) de ces chemins avant le routeur et le pare-feu : aucune route `OPTIONS` n'est
  nécessaire ;
- envoie `Vary: Origin` dans chaque réponse de ces chemins, pour qu'un cache partagé ne donne jamais les en-têtes d'une
  origine à une autre ;
- retire les en-têtes `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials` et
  `Access-Control-Expose-Headers` qu'un contrôleur a posés, quand la politique n'autorise pas l'origine ;
- refuse, pendant la compilation du conteneur, ou à la première requête pour une valeur lue dans l'environnement, un
  joker avec des identifiants de connexion, l'origine `null`, une origine avec un chemin ou un port par défaut, un
  motif d'origine non ancré et un motif qui ne compile pas ;
- ne garde aucun état entre les requêtes : il s'exécute tel quel dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `PhilCorsBundle` et sa configuration `phil_cors` | Implémenté et testé |
| Préambules, en-têtes de réponse et `Vary` (`CorsListener`, `CorsHeaders`) | Implémenté et testé |
| Politiques et règles de chemin (`CorsPolicy`, `PathRule`, `PathPolicyResolver`) | Implémenté et testé |
| La recette Flex, dans `recipe/0.1` | Préparée |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple, et ce qui est refusé. |
| [Comment les requêtes reçoivent leur réponse](doc/fr/behaviour.md) | Les préambules, les réponses, `Vary`, les pages d'erreur et les bornes de ce qu'une requête envoie. |
| [Venir de NelmioCorsBundle](doc/fr/nelmio-migration.md) | Les options de `nelmio_cors` et leurs équivalents. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\CorsBundle\PhilCorsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_cors.yaml
phil_cors:
    paths:
        '^/api/':
            allow_origins: ['https://app.example.com']
            allow_methods: [GET, POST, PUT, DELETE]
            allow_headers: [Content-Type, Authorization]
            allow_credentials: true
            max_age: 3600
        '^/feed\.json$':
            allow_origins: '*'
```

## Prérequis

PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
