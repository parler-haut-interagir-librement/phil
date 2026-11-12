<!-- phil-dev source-sha256=539731f76e189818b00031ecf2f6d0597e91ccfa066f568267215b43245bfce0 -->
# phil/almanac-bundle

**Des jours fériés et des dates calculées déclarés dans la configuration, modifiables par un administrateur, affichés
dans Twig.**

phil/almanac-bundle apporte `phil/almanac` dans une application Symfony. Les dates se déclarent dans la configuration,
par exemple *Action de grâce, 2nd monday of october* ou *Mardi gras, easter - 47 days*, et se calculent dans le fuseau
horaire que donne la configuration. L'almanach est un service, construit à partir de ces dates chaque fois qu'on le
demande. `almanac_dates` donne les dates d'une période dans Twig, `sun_times` le lever, le coucher et les crépuscules
d'un lieu configuré, et `almanac_eclipses` les éclipses solaires et lunaires, que `eclipses: true` déclare aussi comme
des dates. Les libellés et les messages sont en anglais et en français.

Il s'adresse à un site qui affiche des jours fériés et des dates saisonnières à côté de son propre contenu. Lorsque
l'application le veut, les dates sont aussi conservées dans une table Doctrine : un administrateur en ajoute ou en
modifie une avec `StoredDateType`, sans déploiement. Une date stockée remplace la date configurée de même clé. Une ligne
dont la règle ne peut pas être lue est laissée de côté : une seule mauvaise ligne n'arrête donc pas toutes les pages.
Hors de Symfony, tournez-vous vers `phil/almanac` seul. `phil/calendar-bundle` affiche ces dates parmi ses événements.

Les dates stockées se trouvent derrière le port `StoredDateRepository`, avec `DoctrineStoredDateRepository` comme
adaptateur, activé par `database: true`. Doctrine ORM et Twig sont facultatifs : la fonction Twig n'est enregistrée que
si Twig est installé.

## État

| Partie | État |
|---|---|
| `phil_almanac` : fuseau horaire, dates lues à la construction | Implémenté et testé |
| Dates stockées : `StoredDate`, `DoctrineStoredDateRepository`, qui remplacent les dates configurées | Implémenté et testé |
| Le service `Phil\Almanac\Almanac` | Implémenté et testé |
| `StoredDateType` et `DateDraft` | Implémenté et testé |
| Twig : `almanac_dates` | Implémenté et testé |
| Éclipses déclarées comme dates ; lieux ; Twig : `sun_times`, `almanac_eclipses` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et enregistrement. |
| [Configuration](doc/fr/configuration.md) | Le fuseau horaire, les dates, les éclipses et les lieux. |
| [Dates stockées et formulaire](doc/fr/stored-dates.md) | La table, le dépôt, le formulaire et sa validation. |
| [Twig](doc/fr/twig.md) | `almanac_dates`, `sun_times`, `almanac_eclipses`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```yaml
phil_almanac:
    timezone: America/Toronto
    dates:
        thanksgiving: { title: Action de grâce, rule: 2nd monday of october, tags: [holiday] }
        full-moon: { title: Pleine lune, rule: full moon, all_day: false }
```

```twig
{% for date in almanac_dates('2026-10-01', '2026-11-01') %}
    {{ date.definition.title }} — {{ date.span.start|date('Y-m-d') }}
{% endfor %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/almanac` 0.1 ; les composants config, dependency injection, form, HTTP kernel, options
resolver et validator de Symfony 8.2. Doctrine ORM avec `symfony/uid`, pour les dates stockées, et Twig sont
facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
