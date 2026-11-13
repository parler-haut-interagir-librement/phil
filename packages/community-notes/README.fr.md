<!-- phil-dev source-sha256=5a5fb6ab8629deaccbb14fe0a25e20f6a87931158cd3c5d3c79fd4739e72d203 -->
# phil/community-notes

**Notes communautaires rédigées par des membres qualifiés et publiées selon une règle d'accord croisé explicable.**

phil/community-notes permet aux membres d'une communauté d'ajouter du contexte sous une publication, une page ou un
message : une courte note qui dit ce que les lecteurs devraient savoir — la photo date d'une autre année, le chiffre
vient d'une source qui dit le contraire. Des membres qualifiés rédigent les notes et évaluent celles des autres, utiles
ou non. Une note n'est pas publiée par une majorité : elle l'est quand des évaluateurs qui divergent d'ordinaire la
trouvent utile ensemble.

C'est l'idée du pont entre camps, sous une forme qu'une petite communauté peut vérifier à la main. Deux évaluateurs
divergent d'ordinaire quand, sur les autres notes qu'ils ont évaluées tous les deux, ils ont rendu le même verdict sur
moins de la moitié d'entre elles. Une note est jugée utile quand elle a assez d'évaluations, quand la plupart disent
qu'elle est utile, et quand assez de paires d'évaluateurs qui divergent d'ordinaire l'ont dit. Chaque décision est
conservée avec la note et s'explique d'elle-même : la raison, les évaluations de chaque verdict, les nombres de la
règle, et les paires d'évaluateurs qui l'ont prise, avec les notes qu'elles ont partagées et celles sur lesquelles elles
se sont accordées.

La bibliothèque demande à l'application qui peut rédiger et évaluer — par un rôle de Symfony Security, par l'ancienneté,
par un registre qu'elle tient — et où vivent les notes et les évaluations. Elle exporte et efface sur demande les notes
et les évaluations d'un membre, diffuse des événements qui nomment la note et rien d'autre, et offre un message pour
décider de nouveau des notes en arrière-plan.

C'est une bibliothèque PHP simple qui repose sur `phil/resource`, `symfony/uid` et l'horloge et le répartiteur
d'événements PSR. Ses services ne gardent aucun état entre les appels et sont sûrs dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Les notes : rédiger, évaluer, retirer, les notes affichées sous une ressource | Implémenté et testé |
| La règle d'accord croisé, sa décision et son explication | Implémenté, testé et mesuré par un banc d'essai |
| La qualification des rédacteurs et des évaluateurs, par Symfony Security ou l'application | Implémenté et testé |
| Les données d'un membre exportées et effacées | Implémenté et testé |
| Les événements qui nomment la note, et le message `ReconsiderNote` | Implémenté et testé |
| Les stockages en mémoire | Implémenté et testé |
| Les stockages en base de données, la configuration, les pages | Hors de la bibliothèque : l'application, ou un bundle qui repose sur elle, les fournit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les services à construire. |
| [Notes et évaluations](doc/fr/notes-and-ratings.md) | Rédiger, évaluer et retirer des notes, ce qui est affiché et à qui. |
| [La règle d'accord croisé](doc/fr/cross-agreement-rule.md) | Comment une note est décidée, ses nombres, l'explication, le coût. |
| [Qualification](doc/fr/qualification.md) | Qui peut rédiger et évaluer : Symfony Security, ou une qualification de l'application. |
| [Vie privée](doc/fr/privacy.md) | Ce que la bibliothèque conserve d'un membre, l'export et l'effacement. |
| [Événements et messages](doc/fr/events.md) | Les événements diffusés, et décider de nouveau des notes en arrière-plan. |
| [Points d'extension](doc/fr/extension-points.md) | La qualification, les stockages, la règle, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, et comment tester du code qui utilise des notes. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/community-notes
```

```php
use Phil\CommunityNotes\CommunityNotes;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;

$note = $notes->write(ResourceReference::of('post', '42'), $member, 'The photo was taken in 2019, not this week.');

$note = $notes->rate($note->id, $rater, Verdict::Helpful);

$note->status;                        // NoteStatus::NeedsMoreRatings until raters who usually disagree agree
$note->decision?->toPublicArray();    // why, without the raters' names

$notes->shownOf(ResourceReference::of('post', '42'));   // the helpful notes, shown to the readers
```

## Prérequis

PHP 8.5 ou plus récent avec `mbstring`. `symfony/security-core` 8.2 pour demander à Symfony Security qui peut rédiger et
évaluer.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
