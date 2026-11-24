<!-- phil-dev source-sha256=905de6106e2b788f9cdb91947b61a23204444ff013b8e6a0ad9aa975e3cea203 -->
# L'archive

Rien n'entre dans l'archive de soi-même. Une campagne est archivée lorsqu'un éditeur le dit, et seulement une fois
envoyée : `Archive` refuse un brouillon, une campagne planifiée ou une campagne en cours d'envoi avec `NotArchivable`.

## Publier et dépublier

```php
$issue = $archive->publish($campaignId, ['events', 'game-night']);
$archive->unpublish($campaignId);
```

`publish()` rend la version Web de la campagne, la fige et liste le numéro dans l'archive, à son slug. Publier un numéro
de nouveau le rend de nouveau — après la correction d'un gabarit, par exemple —, garde son slug et l'instant de sa
première publication, et garde ses étiquettes à moins qu'on en donne d'autres.

`unpublish()` retire le numéro de l'archive : il quitte la liste, sa page répond 404 et son lien, s'il en avait un, est
révoqué. La ligne reste, pour que le publier de nouveau lui redonne le même slug.

## Partager par un lien

```php
$token = $archive->share($campaignId);
$url = $urls->link($token);   // IssueUrls: https://example.com/newsletter/archive/shared/<token>
$archive->revokeLink($campaignId);
```

`share()` donne un lien à un numéro, qu'il soit publié ou non : un numéro non publié et partagé par un lien est lu par
qui détient le lien, et par personne d'autre. Le jeton est de 32 octets aléatoires, soit 43 caractères de Base64 pour
adresses ; l'archive ne garde que son SHA-256 et donne le jeton une seule fois. Partager de nouveau remplace le lien :
le précédent cesse de fonctionner. `revokeLink()` le révoque.

## Étiquettes

Une étiquette est un slug d'au plus 64 caractères : des lettres minuscules et des chiffres, en mots joints par des
traits d'union simples. Un numéro est classé sous 16 étiquettes au plus ; elles sont gardées distinctes et triées.
`retag()` les remplace, `[]` les retire toutes.

## Slugs

Le slug d'un numéro vient de son sujet, par le slugger de la [configuration](configuration.md), et reste une fois
donné. Deux numéros d'un site au même sujet se distinguent par `-2`, `-3`… jusqu'à `-20` ; au-delà, un numéro prend les
douze premiers caractères du SHA-256 de l'identifiant de sa campagne. Un sujet sans lettre ni chiffre donne `issue`.

## Par la ligne de commande

```sh
bin/console phil:newsletter-archive publish <campaign-id> --tag=events --tag=game-night
bin/console phil:newsletter-archive unpublish <campaign-id>
bin/console phil:newsletter-archive share <campaign-id>
bin/console phil:newsletter-archive revoke <campaign-id>
bin/console phil:newsletter-archive retag <campaign-id> --tag=news
```

`publish` affiche l'adresse du numéro, `share` l'adresse du nouveau lien, une seule fois. La commande est enregistrée
quand `symfony/console` est installé ; elle se termine par `1` quand l'archive refuse la campagne ou une étiquette, et
par `2` pour une action inconnue.

## Les derniers numéros, pour un fil

`IssueFeed::latest($site, $limit, $listKey, $tag)` donne les derniers numéros publiés, du plus récemment envoyé au plus
ancien, sous forme de `FeedItem` : identifiant, titre, adresse absolue, résumé — le texte d'aperçu —, version Web, date
d'envoi et étiquettes. L'archive ne construit aucun fil elle-même ; un bundle de fil la lit.
