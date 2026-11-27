<!-- phil-dev source-sha256=22a95c1af0d21a9950efa1e808ab371a250d9fbb367b10306c2ffcf2905180df -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/newsletter-archive` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-24

### Ajouté

- `PhilNewsletterArchiveBundle`, un bundle Symfony sur `phil/newsletter-bundle` 0.3 ou plus récent, et sa configuration
  : `enabled`, `connection`, `page_size`, `slugger` et `slug_max_length`.
- `ArchivedIssue` : la version web d'une campagne envoyée, son objet, son texte d'aperçu, sa liste, ses étiquettes et
  son slug, publiée, partagée par un lien, ou les deux ; `unpublish()` retire les deux. `Tag`, `ArchiveFilter`,
  `IssuePage`, le port `ArchiveRepository`, `NewsletterArchiveException` et `InvalidArchiveValue`.
- `Archive` : `publish()`, `unpublish()`, `share()`, `revokeLink()` et `retag()` ; seule une campagne envoyée est
  archivée, et seulement quand une personne responsable de la rédaction le dit, `NotArchivable` sinon. Un slug fait de
  l'objet par un générateur de slugs de `phil/slug`, distingué par `-2`, `-3`…, gardé une fois donné.
- Des liens par jetons opaques de 32 octets aléatoires, dont l'archive ne garde que le SHA-256 : `LinkTokens` ; partager
  de nouveau remplace le lien.
- `NeutralWebVersion`, derrière le port `WebVersionRenderer` : la version web rendue avec des données neutres à la place
  de celles de l'abonné, sans lien de désabonnement ni de préférences, jeton, lien suivi, pixel de suivi ou élément
  caché, purifiée par `phil/markup`, figée à la publication. Effacer un abonné laisse l'archive telle quelle.
- `ArchiveReader`, et `IssueFeed` avec `FeedItem`, les derniers numéros publiés pour un fil ; le port `IssueUrls` et
  `RouterIssueUrls`.
- `DbalArchiveRepository`, `ArchiveSchema` sur les tables `phil_newsletter_archive` et `phil_newsletter_archive_tag`,
  les instants en `datetime_utc_immutable`, et `ArchiveSchemaListener` pour l'ORM.
- Les pages : la liste, une page à la fois, filtrée par liste et par étiquette ; un numéro à son slug, avec son adresse
  canonique et ses données Open Graph ; un numéro partagé, jamais mis en cache ni indexé, sans `Referer` ; une 404 pour
  ce qui n'est pas public. Leurs gabarits Twig et leurs textes en anglais et en français.
- La commande `phil:newsletter-archive` : publier, retirer, partager, révoquer et réétiqueter.
- La recette Flex, dans `recipe/0.1`.
- La cible de fuzzing des jetons de lien.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
