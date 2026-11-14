<!-- phil-dev source-sha256=bb21d1d8a7bd8e2e481fd9dbd7377e20ca2df4f7ae8af3657fdd95e620415738 -->
# phil/mentions-bundle

**Les @mentions des membres dans un texte, et leur notification, pour les applications Symfony.**

phil/mentions-bundle permet à une personne qui écrit de nommer un autre membre — `@ada` dans un commentaire, une
proposition, une page — et de le prévenir. Quand l'application enregistre un texte, le bundle en trouve les mentions :
les identifiants tapés après `@` dans un texte brut ou en Markdown, selon un motif que choisit l'application, ou les
marques de mention que contient un document UJO (Universal JSON Output), `<a data-mention="user:42">@Ada</a>`.
L'annuaire des membres de l'application les résout. L'auteur et les membres qui ne peuvent pas voir le contenu sont
écartés. Les autres sont conservés pour ce texte, et seuls les membres qu'il mentionne pour la première fois sont
annoncés et notifiés. Enregistrer de nouveau le même texte ne notifie personne ; une modification ne notifie que les
membres qu'elle ajoute. Un filtre Twig change les identifiants d'un texte en liens vers le profil des membres, et un
contrôleur Stimulus propose les membres dès que la personne tape `@` dans un champ de texte brut.

Il sert à toute application Symfony où des gens s'écrivent : commentaires sur un brouillon, propositions dans un projet
partagé, publications dans une communauté, messages d'une équipe. L'application nomme ses membres par des références de
`phil/resource` (`user:42`) et écrit un seul service, l'annuaire. Celui-ci les trouve par identifiant ou par référence,
les cherche et dit si l'un d'eux peut voir un contenu. La notification passe par `phil/notifications-bundle`, sous le
type `member.mentioned`, sur les canaux que chaque membre a choisis.

C'est un bundle Symfony qui repose sur `phil/resource`, `phil/ujo`, `phil/notifications-bundle` et Doctrine ORM : les
mentions vivent dans une table, ou en mémoire pour les tests. Avec `phil/block-editor-bundle`, le menu `@` de l'éditeur
cherche dans l'annuaire ; avec `phil/comments-bundle`, les commentaires sont enregistrés et leurs auteurs nommés à partir
de l'annuaire ; avec `phil/settings-bundle`, chaque membre choisit s'il veut être notifié. Les mentions des réseaux
sociaux et des forges servent de modèles de comportement.

## État

| Partie | État |
|---|---|
| `Mention`, `MentionPattern` : un identifiant ou une marque, la forme d'un identifiant | Implémenté et testé |
| `TextMentions`, `UjoMentions` : les mentions d'un texte brut, de Markdown, d'un document UJO | Implémenté, testé et soumis au fuzzing |
| `MentionRecorder`, `Mentions` : les mentions conservées par sujet, la différence d'une modification, `MemberMentioned` | Implémenté et testé |
| `MentionLimits` : les membres par texte et la longueur d'un texte | Implémenté et testé |
| Stockages Doctrine et en mémoire | Implémenté et testé, sur SQLite, MySQL 8 et PostgreSQL 17 ; lignes ajoutées par un upsert, stockage en mémoire vidé entre deux requêtes |
| `NotifyMentionedMember`, le type `member.mentioned` sur le centre, le courriel et le Web Push, `MentionPreferences` | Implémenté et testé |
| `MentionLinker` et le filtre Twig `mentions` | Implémenté et testé |
| `MemberReferenceSearch`, `CommentMentions`, `RecordCommentMentions`, `MemberAuthorDirectory` : les intégrations | Implémenté et testé |
| Le contrôleur `mention` : l'autocomplétion `@` des champs de texte brut | Implémenté ; modules purs testés sous Node, contrôleur piloté dans Chrome par la suite de navigateur |
| La recette Flex : le bundle, sa configuration et un annuaire à remplir | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la table, l'annuaire, les autres bundles. |
| [Configuration](doc/fr/configuration.md) | Chaque option et les services enregistrés. |
| [Mentions](doc/fr/mentions.md) | Les identifiants, le motif, Markdown, les marques UJO, les limites. |
| [Enregistrement](doc/fr/recording.md) | Les sujets, les contextes, la visibilité, les modifications, l'événement et les stockages. |
| [Notifications](doc/fr/notifications.md) | Le type de notification, ses traductions, la préférence des membres. |
| [Rendu](doc/fr/rendering.md) | Le filtre `mentions` et `MentionLinker`. |
| [Intégrations](doc/fr/integrations.md) | L'éditeur par blocs, les commentaires et les réglages. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur, ses valeurs, ce qu'il fait, ses modules et la feuille de style. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, les bancs d'essai, la cible de fuzzing et les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/mentions-bundle
```

```yaml
# config/packages/phil_mentions.yaml
phil_mentions:
    member_directory: App\Members\MemberDirectory   # a MemberDirectory
```

```php
$mentions->inText(ResourceReference::of('comment', $comment->id), $author, $comment->body, markdown: true, context: $page);
```

```twig
{{ comment.body|mentions }}
<textarea name="body" data-controller="phil--mentions-bundle--mention"
          data-phil--mentions-bundle--mention-url-value="{{ path('phil_block_editor_mentions') }}"></textarea>
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine ORM 3, `phil/resource`, `phil/ujo` et `phil/notifications-bundle`. Pour
le filtre, Twig 3 ; pour le contrôleur, Stimulus 3 et les routes de `phil/block-editor-bundle`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
