<!-- phil-dev source-sha256=370e14b47d415ec8b1fd7c6891d2dafd68accfdeb41de0e15e25f84dcf4db077 -->
# phil/moderation-bundle

**Une seule file pour tous les signalements d'un site — messages de forum, commentaires, réactions, annonces,
messages, profils —, les décisions de ses modérateurs et leur historique, un exposé des motifs à l'auteur de chaque
contenu restreint, les crochets du bac à sable du premier message et des mots surveillés, et un journal public qui
montre le rôle d'un modérateur, jamais son nom.**

phil/moderation-bundle donne à un site Symfony un seul endroit où ses modérateurs examinent ce que les membres
signalent. Chaque paquet qui détient du contenu branche sa propre `ModerationTarget` — ce que la file montre d'un
contenu, et ce qu'une décision lui fait — et chaque signalement, quel que soit le contenu, aboutit dans la même file.
Un modérateur agit sous un rôle de la configuration du site, qui peut couvrir tous les types de contenu ou seulement
certains ; sa décision — rejeter, approuver, masquer, rétablir, retirer, avertir — est appliquée par le paquet du
contenu, ferme les signalements ouverts et rejoint l'historique du contenu et de son auteur. Une décision qui restreint
l'auteur s'accompagne d'un exposé des motifs : la raison, si elle repose sur les conditions du site ou sur la loi, les
faits, si un signalement ou une détection automatisée l'a provoquée, et les moyens de la contester, que l'auteur lit sur
sa propre page et dont il peut être notifié.

La même file reçoit ce que le site retient de lui-même : un paquet appelle `Submissions::submit()` quand un membre
publie, et les mots surveillés bloquent, retiennent ou signalent le texte, tandis que le bac à sable du premier message
retient le premier contenu d'un nouveau venu jusqu'à ce qu'un modérateur en approuve un. Les membres d'un niveau de
confiance élevé peuvent masquer un contenu sur-le-champ, jusqu'à ce que la modération l'examine. Les modérateurs
gardent des notes sur un membre, que le membre ne lit jamais. Le journal public énumère les décisions publiques avec le
rôle sous lequel elles ont été prises. Quand `phil/incident-mode-bundle` est enregistré, la file
offre son mode d'incident au rôle qui l'exploite, et propose un incident quand les signalements montent en flèche.

Les membres sont désignés par un identifiant stable, jamais une adresse de courriel. Les enregistrements vivent dans
cinq tables d'une connexion Doctrine DBAL, leurs moments en UTC, et rejoignent l'export et l'effacement d'un membre
par `phil/privacy-bundle`. Les pages répondent sur les routes du bundle et, avec EasyAdmin, dans chaque tableau de
bord ; elles dessinent leurs éléments avec les recettes du kit PHIL UX.

## État

| Partie | État |
|---|---|
| Une seule file pour chaque type de cible enregistré ; un signalement par membre et par cible tant qu'il est ouvert ; les raisons viennent de la configuration | Implémenté et testé |
| Les rôles de la modération : le premier détenu, les types de cibles que chacun couvre | Implémenté et testé |
| Les décisions appliquées par le paquet de la cible, fermant les signalements ouverts ; historique par cible et par membre | Implémenté et testé |
| Les exposés des motifs : motifs vérifiés avant l'action, conditions ou loi selon la raison, signalement et détection automatisée, recours ; les pages du membre ; la notification avec `phil/notifications-bundle` | Implémenté et testé |
| `Submissions` : le crochet des mots surveillés (bloquer, retenir, signaler, remplacer) et du bac à sable du premier message | Implémenté et testé |
| Le masquage temporaire par des membres de confiance, examiné par la modération | Implémenté et testé |
| Les profils comme cible, par les `MemberProfiles` de l'application | Implémenté et testé |
| Les notes de la modération sur un membre | Implémenté et testé |
| Le journal public : le rôle, jamais le nom | Implémenté et testé |
| Le mode d'incident de `phil/incident-mode-bundle` offert dans la file, et proposé quand les signalements montent en flèche | Implémenté et testé |
| Les pages, sur les routes du bundle et dans les tableaux de bord EasyAdmin ; le formulaire de signalement ; les recettes du kit | Implémenté et testé |
| L'export et l'effacement par `phil/privacy-bundle` | Implémenté et testé |
| Les tables DBAL sur PostgreSQL, MySQL et SQLite, moments en UTC, écouteur de schéma de l'ORM | Implémenté et testé |
| Les mots surveillés de `phil/watched-words` | Port seulement : `WordScreen` ; l'adaptateur vient avec ce paquet |
| Les niveaux de confiance de `phil/trust-levels` | Port seulement : `TrustedHiders` ; l'adaptateur vient avec ce paquet |
| Les signalements sur du contenu du fédivers et l'activité `Flag` | Pas commencé |
| L'appel d'une décision, examiné dans le bundle par un autre rôle que celui qui a décidé | Pas commencé : l'exposé des motifs renvoie à la page de plainte que le site configure |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, les tables, les routes, les recettes, les membres. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [La file](doc/fr/queue.md) | Les cibles, les signalements, les rôles, les décisions et leur historique. |
| [Crochets](doc/fr/hooks.md) | Le bac à sable du premier message, les mots surveillés, le masquage temporaire par des membres de confiance, les profils. |
| [Exposés des motifs](doc/fr/statements-of-reasons.md) | Les motifs d'une restriction, ce que lit l'auteur, la notification, le recours. |
| [Pages](doc/fr/pages.md) | L'interface d'administration, EasyAdmin, les notes sur les membres, le formulaire de signalement, le journal public. |
| [Mode d'incident](doc/fr/incident-mode.md) | Le mode d'incident de `phil/incident-mode-bundle` dans la file, et ses points de contrôle dans les pages du site. |
| [Renseignements personnels](doc/fr/privacy.md) | Les identifiants stables, l'export et l'effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, étiquettes et événements, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, son banc d'essai, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\ModerationBundle\PhilModerationBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_moderation.yaml
phil_moderation:
    roles:
        - { name: moderator, attribute: ROLE_MODERATOR }
    sandbox:
        types: [forum.post]
```

```twig
{{ include('@PhilModeration/_report_form.html.twig', {type: 'forum.post', id: post.id}) }}
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium`, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4.5, Twig
et Symfony UX Twig Component 3.7. SecurityBundle pour les pages de la modération.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
