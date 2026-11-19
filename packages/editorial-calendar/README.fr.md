<!-- phil-dev source-sha256=458a7c4fa87d5e01123e8c67bf4b8926fd96a399131d032d607003c71bc11091 -->
# phil/editorial-calendar

**Un calendrier éditorial des publications prévues, avec des lots publiés en entier ou pas du tout.**

phil/editorial-calendar planifie ce qu'un site publie et quand. Chaque élément prévu désigne par référence le contenu
qu'il publie (`article:42`), sa langue, sa section, et son auteur par un identifiant stable — jamais une adresse
courriel —, et passe par un flux de travail : idée, rédaction, prêt, planifié, publié, retiré. Les moments sont gardés
en UTC ; les vues les découpent en jours dans le fuseau horaire du lecteur.

Les éléments peuvent être regroupés en lots de publication — un lancement, une série, un ensemble traduit — montrés
comme une seule entrée et appliqués en bloc : à leur moment ou à la main, chaque élément d'un lot est publié, ou aucun
ne l'est et le calendrier reste tel qu'il était. Le calendrier décrit ce contrat ; l'application l'exécute dans une
seule transaction avec son planificateur.

Le calendrier montre un jour, une semaine ou un mois dans un fuseau horaire, découpé en ses jours et juste lors des
changements d'heure, et une liste entre deux moments, avec les transitions qu'un planificateur prévoit ailleurs, lues
par un port, filtrées par auteur, section et état. Des gabarits dessinent une vue avec Twig et les recettes du kit
PHIL UX, sans JavaScript. Une revue signale le même contenu prévu deux fois dans une langue, les éléments prévus alors
qu'ils sont encore des idées ou des brouillons, les publications plus rapprochées qu'un espacement choisi, et les
périodes qui n'en ont aucune. Un rédacteur iCalendar exporte les publications pour qu'une application de calendrier
s'y abonne.

C'est une bibliothèque PHP simple : des valeurs immuables et des services sans état, sans dépendance au-delà de
`ext-mbstring` et `phil/resource`.

## État

| Partie | État |
|---|---|
| Éléments prévus, auteurs par identifiant, flux de travail des états | Implémenté et testé |
| Lots de publication, appliqués en entier ou pas du tout, lots échus et manuels | Implémenté et testé |
| Vues jour, semaine, mois et liste dans un fuseau horaire, transitions lues par un port | Implémenté, testé et mesuré par des bancs d'essai |
| Filtres par auteur, section et état | Implémenté et testé |
| La revue : contenu répété, éléments non prêts, espacement, périodes vides | Implémenté, testé et mesuré par des bancs d'essai |
| L'export iCalendar (RFC 5545) | Implémenté, testé et mesuré par des bancs d'essai |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Planification](doc/fr/planning.md) | Éléments, auteurs, états et flux de travail, moments en UTC, le calendrier. |
| [Lots de publication](doc/fr/batches.md) | Les lots, leur moment, leur application en bloc, le contrat avec un planificateur. |
| [Vues](doc/fr/views.md) | Jours, semaines, mois et listes, entrées, fuseaux horaires, filtres, transitions prévues ailleurs. |
| [Twig](doc/fr/twig.md) | Dessiner une vue avec Twig et les recettes du kit PHIL UX. |
| [Revue](doc/fr/review.md) | Les conflits et les périodes vides signalés, et leurs règles. |
| [iCalendar](doc/fr/icalendar.md) | L'export pour les applications de calendrier. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure ou sur quoi elle peut compter. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/editorial-calendar
```

```php
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\View\CalendarView;
use Phil\Resource\ResourceReference;

$author = new AuthorId('0192f3c4-7d1e-7b2a-9c3d-4e5f6a7b8c9d');
$calendar = EditorialCalendar::of(
    [
        PlannedItem::plan('launch-fr', ResourceReference::of('article', '42'), 'Lancement', 'fr', $author),
        PlannedItem::plan('launch-en', ResourceReference::of('article', '43'), 'Launch', 'en', $author),
    ],
    [new PublicationBatch('launch', 'Launch', ['launch-fr', 'launch-en'], new DateTimeImmutable('2026-12-01 09:00', new DateTimeZone('America/Toronto')))],
);

$calendar = $calendar->transition('launch-fr', ItemState::Drafting); // and so on, to ready

$now = new DateTimeImmutable();
$week = new CalendarView()->week($calendar, new DateTimeImmutable(), new DateTimeZone('America/Toronto'));

foreach ($calendar->dueBatches($now) as $batch) {
    $calendar = $calendar->applyBatch($batch->id, $now); // every item published, or BatchFailed and nothing changed
}
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`, et `phil/resource`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
