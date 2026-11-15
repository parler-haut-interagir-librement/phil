<!-- phil-dev source-sha256=ee5424086c63ca4cd0e48a955ad1ace239ea6e62d115649c3541dfb54f2d87e0 -->
# Licence des contributions

Un site où des membres publient du contenu (textes, images, règles, critiques) dit sous quelle licence ce contenu est
publié. Le bundle conserve cette licence avec les conditions : une politique la nomme, chacune de ses versions la
choisit, et l'acceptation d'une version par le membre la date.

## Déclarer la politique

```yaml
phil_terms:
    policies:
        terms: ~
        contributions: { required: true, contribution_licence: true }
```

Une politique au plus nomme la licence : la construction s'arrête sur une deuxième. Si elle est requise, la porte
demande à chaque membre connecté de l'accepter avant de continuer, de sorte qu'aucune contribution ne se fait sans
licence.

## Publier une version

Chaque version de cette politique nomme sa licence par un identifiant SPDX, comme `CC-BY-SA-4.0`, `CC0-1.0` ou
`LicenseRef-site` pour une licence propre au site : un seul identifiant de 64 caractères au plus, jamais une expression
comme `MIT OR Apache-2.0`.

```php
$terms->publish('contributions', ['en' => $charter, 'fr' => $charte], licence: 'CC-BY-SA-4.0');
```

L'administration affiche un champ de licence pour cette politique seulement. Une version de cette politique sans licence
est refusée avec la raison `licence_required`, une licence sur une autre politique avec `licence_not_expected`, et une
valeur qui n'est pas un identifiant avec `licence`. La licence fait partie de la version, donc elle ne change jamais une
fois publiée : une nouvelle licence est une nouvelle version, importante ou non.

La page publique d'une version et la page d'acceptation affichent la licence sous le texte.

## Trouver la licence d'une contribution

`ContributionLicences::at()` répond à la question : sous quelle licence un membre a-t-il publié quelque chose à un
moment donné ?

```php
use Phil\TermsBundle\Application\ContributionLicences;

$licence = $licences->at($member, $post->publishedAt);

$licence?->licence;     // "CC-BY-SA-4.0"
$licence?->version;     // 2, the version of the policy the member accepted
$licence?->acceptedAt;  // when they accepted it
```

Elle prend la version la plus élevée que le membre avait acceptée à ce moment parmi celles en vigueur à ce moment. Une
version plus récente ne change jamais la licence d'une contribution plus ancienne, et une version acceptée avant son
entrée en vigueur ne s'applique qu'à partir de ce moment. La réponse est `null` quand le membre n'avait accepté aucune
version nommant une licence, ou quand aucune politique n'en nomme une.

La licence est lue dans les acceptations : l'effacement des acceptations d'un membre par `phil/privacy-bundle` efface
donc aussi cet enregistrement. Une application qui doit garder la licence du contenu qu'elle conserve la stocke avec le
contenu au moment de sa publication.
