<!-- phil-dev source-sha256=62bae0f89dc998615defd6d22c62a98b60c0b122527d0e872319013e8414870b -->
# phil/content-access

**Décidez qui lit un contenu — public, membres, paliers par règles, accès anticipé puis public, accès goutte-à-goutte
et glissant, institutions — et n'envoyez jamais la partie réservée à qui ne peut pas la lire.**

phil/content-access est une bibliothèque PHP simple. Un contenu porte une politique d'accès ; un décideur dit, pour un
lecteur à un instant donné, s'il lit tout le contenu, sa partie publique avant la coupure, une entrée verrouillée qui
dit ce qu'il reste, ou rien du tout. Les blogues, les publications, les cours, les calendriers, les médias et les
abonnements lisent les mêmes politiques au lieu d'écrire chacun son mur payant.

- **Les droits sont des noms de fonctionnalités** : un contenu demande `tier:gold` ou `course:intro` ; les droits
  viennent des abonnements, des adhésions, des achats, des inscriptions ou d'une plateforme externe par un seul port,
  `Entitlements`, lu seulement quand une règle demande une fonctionnalité, et jamais pour un visiteur non connecté.
- **Des règles, pas du code** : un contenu restreint porte une règle de `phil/rules` — tout, au moins un et non
  imbriqués, bornés et expliqués — avec quatre types de conditions d'accès : `access.member`, `access.role`,
  `access.feature` (avec une ouverture selon l'ancienneté et une durée glissante) et `access.institution` (un domaine
  de courriel vérifié ou une plage de réseau déclarée), à côté des comparaisons génériques et des faits propres à
  l'application.
- **Accès anticipé puis public** : une politique peut devenir publique pour de bon à un instant donné ; la décision le
  dit, pour qu'un cache ne garde rien au-delà.
- **Rien de réservé ne quitte le serveur** : `PreviewCut` donne les blocs d'un document UJO (Universal JSON Output)
  que la décision laisse passer — tout le contenu, la partie avant la coupure, ou rien — ; un document sans coupure n'a
  pas de partie publique.
- **Aucun oracle** : un contenu caché reçoit la réponse d'un contenu absent ; une décision personnelle n'est jamais
  mise en cache pour les autres.
- **Les moteurs de recherche reçoivent la vérité** : `StructuredData` donne `isAccessibleForFree` et `hasPart` pour la
  partie réservée, les mêmes pour tous les lecteurs.
- **Aucun pistage** : aucun compteur d'articles lus, aucun mur à compteur ; une institution correspond sans que son
  domaine ni son adresse soient conservés ou journalisés.

## État

| Partie | État |
|---|---|
| Politiques : publics, accès anticipé, aperçu, refus verrouillé ou caché | Implémenté et testé |
| Le décideur, ses décisions et leur explication | Implémenté et testé |
| Les types de conditions `access.member`, `access.role`, `access.feature`, `access.institution` | Implémenté et testé |
| Les droits et le port `Entitlements`, en mémoire et chaînés | Implémenté et testé |
| La coupure d'un document UJO et les propriétés schema.org | Implémenté et testé |
| La forme JSON versionnée : lecteur, écrivain, empreinte | Implémenté, testé et fuzzé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, une première décision. |
| [Politiques et décisions](doc/fr/policies.md) | Publics, accès anticipé, aperçu, refus, décisions, cache, coupure, données structurées. |
| [Conditions](doc/fr/conditions.md) | Les faits que lit une règle et les quatre types de conditions d'accès. |
| [Forme JSON](doc/fr/json-form.md) | La forme versionnée d'une politique, ce qui est refusé, l'empreinte. |
| [Points d'extension](doc/fr/extension-points.md) | Les sources de droits, les faits de l'application, les types de conditions, ce qui est stable. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing, le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/content-access
```

```php
use Phil\ContentAccess\AccessDecider;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\PreviewCut;
use Phil\ContentAccess\Viewer;
use Phil\Rules\Json\RuleReader;

$rule = new RuleReader(AccessConditions::types())->fromJson('{"version": 1, "condition": {"any": [
    {"type": "access.feature", "operands": {"feature": "tier:gold"}},
    {"type": "access.institution", "operands": {"domains": ["library.example"]}}
]}}');

$policy = AccessPolicy::restricted($rule)
    ->withPublicFrom(new DateTimeImmutable('2027-01-15T00:00:00+00:00'))
    ->withPreview();

$decision = new AccessDecider($entitlements, $clock)->decide($policy, Viewer::member($user->id(), $user->roles()));

$decision->access;                                            // Access::Full, Preview, Locked or Hidden
$blocks = new PreviewCut()->visibleBlocks($decision, $document['blocks']);
```

## Prérequis

PHP 8.5 ou une version plus récente, `phil/rules`, `psr/clock` et `symfony/http-foundation` (pour la comparaison des
plages de réseau).

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
