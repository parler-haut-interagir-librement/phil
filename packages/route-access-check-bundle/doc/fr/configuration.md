<!-- phil-dev source-sha256=bcb21e9b354906067b260bd8ac196040a2156e3101533f050712a438af454fff -->
# Configuration

Chaque clé est facultative.

```yaml
# config/packages/phil_route_access_check.yaml
phil_route_access_check:
    environments: [dev, test]
    baseline: '%kernel.project_dir%/route-access-baseline.json'
    public_option: public
    attributes:
        - Symfony\Component\Security\Http\Attribute\IsGranted
    ignore:
        route_names: [_profiler, _wdt, _preview_error]
        paths: [/_profiler, /_wdt, /_error, /.well-known/, /healthz]
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `environments` | `[dev, test]` | Les environnements du noyau où la vérification est enregistrée ; dans tout autre, le bundle n'enregistre rien. |
| `baseline` | `null` | Le fichier de ligne de base dont les échecs sont tolérés, et le fichier qu'écrit `--generate-baseline` ; `null`, aucun. |
| `public_option` | `public` | L'option de route qui déclare une route publique, réglée à `true` ou à la raison. |
| `attributes` | `IsGranted` de Symfony | Les classes d'attributs lues comme vérifications sur les contrôleurs, avec les arguments de `#[IsGranted]`. |
| `ignore.route_names` | `[_profiler, _wdt, _preview_error]` | Préfixes de noms de route mis de côté. |
| `ignore.paths` | `[/_profiler, /_wdt, /_error]` | Préfixes de chemins mis de côté. |

Une liste donnée remplace sa valeur par défaut : pour ajouter un préfixe, répétez les valeurs par défaut à côté. Il
vaut mieux déclarer publique, avec sa raison, une sonde de santé ou un chemin `/.well-known/` que l'ignorer : le
rapport dit alors pourquoi elle est ouverte.

Une ligne de base configurée qui n'existe pas fait échouer la commande avec le code de sortie 2 ; créez-la d'abord
avec `--generate-baseline`.

## Ce qui est lu dans la configuration de sécurité

Les pare-feu et les entrées `access_control` sont lus dans les services que construit SecurityBundle, une fois tous
les fichiers fusionnés. Les correspondants de requête de symfony/http-foundation sont relus en chemin, hôte, méthodes,
port, adresses de clients et attributs, et les paramètres qu'ils nomment sont résolus. Un `request_matcher` sur mesure
est gardé sous son identifiant de service, et la vérification traite sa règle comme conditionnelle. Une variable
d'environnement, dont la valeur n'est connue qu'à l'exécution, est gardée sous son paramètre fictif `%env(NAME)%`. Un
chemin écrit à partir d'elle ne correspond à aucune route, et les routes pour lesquelles il a été écrit sont signalées
non couvertes plutôt que présumées protégées.
