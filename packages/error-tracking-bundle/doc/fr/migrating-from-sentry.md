<!-- phil-dev source-sha256=f8769e4ca0224d326a0c6121c045154cb1b5cbf0521efc6a9d96623e8532c685 -->
# Migrer depuis sentry-symfony

Ce guide fait passer une application Symfony de `sentry/sentry-symfony` à ce bundle. Les deux ne lisent pas la
configuration l'un de l'autre : l'application remplace le SDK, côté serveur, par les [points de capture](capture.md) du
bundle et, dans le navigateur, par le [contrôleur Stimulus](browser-errors.md). Dès lors, les événements restent dans la
base de données de l'application ; ceux déjà envoyés à un serveur Sentry y restent.

## Avant de commencer

- **Le traçage et le profilage ne sont pas couverts.** Le bundle garde les erreurs. Les étendues, les transactions, les
  profils et les moniteurs de cron du SDK n'ont pas d'équivalent ici : gardez un outil à eux, ou abandonnez-les.
- **Les fils d'Ariane, l'utilisateur et le corps de la requête ne sont jamais gardés.** Un événement porte la requête
  réduite — méthode, adresse, route, quelques en-têtes, l'adresse du client tronquée par `phil/ip-anonymizer` — et les
  étiquettes de son point de capture. Il n'y a pas de `send_default_pii` à activer.
- **Faites tourner les deux un moment si vous voulez comparer.** Le bundle capture par ses propres écouteurs ; il n'a pas
  besoin que le SDK soit parti pour fonctionner. Retirez le SDK quand les groupes de `/admin/errors` vous semblent
  justes.

## 1. Installer le bundle

Suivez [Installation](installation.md) : requérez le bundle, créez ses deux tables, importez les routes des pages
d'administration, et lancez `phil:error-tracking:purge` depuis le planificateur.

## 2. Reporter les options

| sentry-symfony | phil_error_tracking | Remarques |
|---|---|---|
| `dsn` | aucune | Les événements sont écrits par DBAL : `storage.connection` nomme la connexion. |
| `options.environment` | `environment` | `%kernel.environment%` par défaut. |
| `options.release` | `release` | Les régressions se reconnaissent par elle : définissez-la à chaque déploiement. |
| `options.sample_rate` | `sample_rate` | La part des événements gardés, de 0.0 à 1.0. |
| `options.ignore_exceptions` | `ignored_exceptions` | Les classes et leurs sous-classes ne sont jamais capturées. |
| `options.in_app_exclude`, `in_app_include` | `project_dir` | Les cadres qui y sont et hors de son `vendor/` sont ceux de l'application. |
| `options.before_send` | `scrub_fields`, `ignored_exceptions` | L'assainisseur s'exécute avant que rien ne soit écrit ; voir le document sur l'assainissement de `phil/error-tracking`. |
| `options.send_default_pii` | aucune | Les renseignements personnels sont assainis quelle que soit la configuration. |
| `options.max_breadcrumbs` | aucune | Aucun fil d'Ariane n'est gardé. |
| `options.traces_sample_rate`, `tracing` | aucune | Non couvert : voir plus haut. |
| `register_error_listener` | `capture.http` | Activé par défaut ; `capture.http.client_errors` ajoute les statuts inférieurs à 500. |
| `messenger.enabled` | `capture.messenger` | Activé par défaut ; `capture.messenger.retries` capture chaque tentative échouée. |
| `messenger.capture_soft_fails` | `capture.messenger.retries` | Un échec qui sera retenté n'est capturé que si elle vaut vrai. |
| L'intégration de la console | `capture.console` | Activée par défaut. Les arguments d'une commande ne sont jamais gardés. |

[Configuration](configuration.md) liste chaque clé.

## 3. Remplacer le gestionnaire Monolog

Un gestionnaire `sentry` dans `monolog.yaml` devient un gestionnaire `service` sur celui du bundle, dont le niveau est
`capture.monolog_level` :

```yaml
monolog:
    handlers:
        errors:
            type: service
            id: phil_error_tracking.monolog_handler
```

Un enregistrement qui porte une exception est groupé comme cette exception ; l'enregistrement de journal d'une exception
que l'écouteur de requêtes a déjà capturée n'est pas capturé deux fois.

## 4. Remplacer les appels dans le code

| sentry-symfony | Ce bundle |
|---|---|
| `\Sentry\captureException($exception)` ou `HubInterface::captureException()` | `SafeCapture::throwable($exception, new CaptureContext())` |
| `\Sentry\captureMessage('Export failed')` | un enregistrement de journal au niveau du gestionnaire, `$logger->error('Export failed')` |
| `\Sentry\configureScope()` avec `setTag()` | `new CaptureContext(context: ['order' => $order->id])`, valeurs scalaires seulement |
| `\Sentry\configureScope()` avec `setUser()` | aucun : l'utilisateur n'est jamais gardé |
| `\Sentry\withScope()` | un `CaptureContext` donné à cette seule capture |

`SafeCapture` ne lève jamais d'exception et ne boucle jamais : une capture qui échoue est journalisée une fois et
retourne `null`. Voir [Capture](capture.md) pour chaque point de capture et ce qu'il étiquette.

## 5. Remplacer le SDK du navigateur

Retirez le SDK du navigateur et son script de chargement, activez `browser` dans la configuration, importez les routes
du point de terminaison et mettez le contrôleur Stimulus sur le corps de la page, comme le montre
[Erreurs du navigateur](browser-errors.md). Le contrôleur envoie le type, le message, la trace et la page sans sa chaîne
de requête ; le point de terminaison vérifie l'origine, le jeton facultatif, la taille et le débit. Retirez l'hôte de
Sentry du `connect-src` de la politique de sécurité du contenu : les rapports vont à l'origine de l'application.

## 6. Avertir quelqu'un

Les alertes remplacent les notifications du serveur Sentry : nommez des destinataires ou une audience de
`phil/notifications-bundle`, ou un transport de *chatter* de `symfony/notifier`, comme l'expliquent les
[Alertes](alerts.md). Sans aucun d'eux, les alertes sont dans l'état dégradé : `phil:error-tracking:status`, les pages
d'administration et le journal le disent, et rien n'échoue.

## 7. Retirer les anciens paquets

```sh
composer remove sentry/sentry-symfony
```

Supprimez ensuite `config/packages/sentry.yaml`, la ligne du bundle dans `config/bundles.php` si Flex l'a laissée, la
variable `SENTRY_DSN` de chaque environnement, et le paquet ou le script du SDK du navigateur. Lancez
`phil:error-tracking:status` dans chaque environnement : les points de capture qu'il liste sont activés, le magasin
répond, et les alertes ont une destination.
