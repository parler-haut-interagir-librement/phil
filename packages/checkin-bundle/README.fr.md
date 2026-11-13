<!-- phil-dev source-sha256=3327a182d046b7e008732fcdb1395ef5772627c4917845dbd9d825e1cbdbcb99 -->
# phil/checkin-bundle

**L'enregistrement à l'entrée d'une application Symfony : lu en ligne, décidé hors ligne, enregistré une seule fois.**

phil/checkin-bundle intègre `phil/checkin` à une application Symfony. Il construit le poste d'accueil sur les
résolveurs de codes de l'application et garde les enregistrements dans une table de la base de données de
l'application. Il fournit à un appareil du personnel trois points de terminaison : lire un code, télécharger le
manifeste hors ligne d'un point de contrôle et envoyer le journal tenu hors ligne. Il fournit aussi un contrôleur
Stimulus qui s'en sert : un champ pour un lecteur de codes ou un clavier, le résultat annoncé dans une région active
et, quand le réseau tombe, les mêmes décisions prises à partir du manifeste, puis synchronisées au retour du réseau.

C'est le noyau sur lequel s'appuient la billetterie, les inscriptions, les adhésions et les présences. Chacun apporte
ses titres d'accès par un `CredentialResolver` et, pour les appareils hors ligne, par un `CredentialCatalog`, étiquetés
par la configuration automatique et interrogés avec les autres. Chaque point de contrôle peut ajouter une règle
d'admission dans l'ExpressionLanguage de Symfony — un âge, un produit, une heure —, appliquée en ligne comme dans le
manifeste hors ligne. Qui peut enregistrer à un point de contrôle relève de la sécurité de l'application, par un
*voter* sur un seul attribut ; sans *voter*, personne ne le peut. Un lecteur par caméra transmet ses codes au
contrôleur par un événement DOM, et chaque lecture enregistrée parvient aux écouteurs de l'application sous forme
d'événement.

Les points de terminaison ne font confiance qu'au contrôleur du site : son propre en-tête, les métadonnées de requête
du navigateur et le jeton CSRF sans état de Symfony. Les codes ne sont jamais stockés, jamais journalisés et ne
quittent jamais le serveur. Un manifeste hors ligne en porte des empreintes à clé, et les noms des titulaires
seulement quand l'application les demande.

## État

| Partie | État |
|---|---|
| Le poste d'accueil sur les résolveurs étiquetés, la politique et les services hors ligne tirés de la configuration | Implémenté et testé |
| L'admission d'un module branchée par un seul service, résolveur et catalogue, les catalogues interrogés ensemble | Implémenté et testé |
| La règle d'admission de chaque point de contrôle, dans l'ExpressionLanguage de Symfony, en ligne et dans les manifestes | Implémenté et testé |
| Le registre Doctrine, sa table jointe au schéma que génère l'ORM | Implémenté et testé |
| Les points de terminaison de lecture, de manifeste et de synchronisation, leurs vérifications CSRF et d'autorisation | Implémenté et testé |
| Les lectures enregistrées, transmises aux écouteurs de l'application | Implémenté et testé |
| Le contrôleur du poste, son manifeste et son journal gardés dans IndexedDB, en anglais et en français | Implémenté et testé, dans Node.js et dans Chrome |
| Chaque appareil hors ligne par lui-même, synchronisé ensuite, sans relais sur le site | Implémenté et testé |
| La lecture des codes par caméra | Pas dans ce bundle : un lecteur déclenche `phil-checkin:code` |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, les routes, la table, la sécurité. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_checkin`. |
| [Points de terminaison](doc/fr/endpoints.md) | Les trois points de terminaison, leurs corps, leurs réponses et leurs erreurs. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur du poste, sa configuration, le mode hors ligne et les événements. |
| [Points d'extension](doc/fr/extension-points.md) | Les règles, les attributs, les résolveurs, les catalogues, l'admission d'un module, le registre, le *voter*, les lecteurs, les événements et les paquets construits sur le bundle. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester l'enregistrement d'une application. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/checkin-bundle
```

```yaml
# config/packages/phil_checkin.yaml
phil_checkin:
    enabled: true
    catalog: App\Checkin\TicketCatalog
```

```yaml
# config/routes/phil_checkin.yaml
phil_checkin:
    resource: '@PhilCheckinBundle/config/routes.php'
    prefix: /_checkin
```

```twig
<form data-controller="phil--checkin-bundle--checkin-desk"
      data-phil--checkin-bundle--checkin-desk-config-value="{{ desk|json_encode }}"
      data-action="phil--checkin-bundle--checkin-desk#submit">
    <input data-phil--checkin-bundle--checkin-desk-target="code" autocomplete="off" autofocus>
    <output data-phil--checkin-bundle--checkin-desk-target="result" role="status"></output>
</form>
```

`desk` vaut `DeskConfig::for(CheckpointId::of('main-door'))` ; le résolveur, le catalogue et le *voter* sont ceux de
l'application : voyez [Points d'extension](doc/fr/extension-points.md).

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 avec sa protection CSRF active, Doctrine DBAL 4.5 et `phil/checkin`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
