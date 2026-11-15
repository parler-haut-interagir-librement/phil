<!-- phil-dev source-sha256=c122cefa9241769150ceeeb9f421091a6fe8a83186ac7fe8a6088875d0ef7a88 -->
# Exposés des motifs

Une décision qui restreint l'auteur d'un contenu — `Hide`, `Remove` ou `Warn` — s'accompagne d'un **exposé des motifs**
qui lui est adressé : ce qui a été décidé et sur quoi, pourquoi, sur quels motifs, et comment la contester. Ses champs
suivent ceux que l'article 17 du Digital Services Act européen demande d'un exposé des motifs, et ils répondent à
l'obligation de dire à une personne les motifs d'une décision qui la concerne, que des lois sur la vie privée comme la
Loi 25 du Québec et le RGPD posent aussi. L'exposé ne nomme jamais le modérateur ni le membre qui a signalé.

## Ce que donne un modérateur

Le formulaire de décision demande les motifs quand la décision restreint l'auteur :

| Champ | Ce que c'est |
|---|---|
| Raison | L'une des raisons configurées, le même catalogue qu'offre le formulaire de signalement |
| Faits | Ce sur quoi la décision repose, jusqu'à 2 000 caractères, obligatoire |
| Disposition | La clause des conditions, ou la loi pour un contenu illégal, jusqu'à 255 caractères ; celle de la configuration quand le champ est vide |

En PHP, les motifs sont le dernier argument de `Decisions::decide()` :

```php
use Phil\ModerationBundle\Application\Grounds;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

$decisions->decide($target, DecisionAction::Hide, 'Advertising', grounds: new Grounds('spam', 'The post advertises a shop.'));
```

**Les motifs sont vérifiés avant que l'action soit appliquée** : une décision sans eux, avec une raison hors du
catalogue, sans faits, ou reposant sur la loi sans la nommer, est refusée avec `InvalidModeration`, et rien ne change —
le contenu reste tel quel, les signalements restent ouverts, aucun événement n'est réparti.

Aucun exposé ne suit une décision qui ne restreint personne : `Dismiss`, `Approve` et `Restore`, ou un contenu dont le
paquet ne nomme aucun auteur. Un masquage temporaire par un membre de confiance est examiné par la modération, et la
décision qui le confirme s'accompagne de l'exposé.

## Ce que dit l'exposé

| Champ | D'où il vient |
|---|---|
| La décision et sa portée | L'action : masqué pour tous sauf l'auteur et la modération jusqu'au rétablissement, retiré de tout le site, ou un avertissement |
| Le contenu | Son type, tel que le paquet de la cible l'a enregistré |
| La raison | Le code que le modérateur a choisi, traduit sous `reason.<code>` |
| Les motifs | `terms` ou `illegal`, selon la configuration de la raison, et la disposition |
| Les faits | Ce que le modérateur a écrit |
| Un signalement ou l'initiative de la modération | Si un signalement d'un membre était ouvert sur le contenu |
| La détection automatisée | Si les mots surveillés avaient signalé ou retenu le contenu |
| Qui a décidé | Toujours une personne : le bundle ne décide jamais par des moyens automatisés |
| Les moyens de la contester | La page de plainte, les conditions, les organismes extrajudiciaires de la configuration, et les tribunaux |

## Motifs selon la raison

```yaml
phil_moderation:
    statements:
        grounds:
            spam: { ground: terms, reference: 'Terms of use, section 4' }
            hate: { ground: illegal, reference: 'Criminal Code, s. 319' }
        terms_url: /terms
        complaint_url: /contact/moderation
        out_of_court:
            - { name: 'A certified dispute settlement body', url: 'https://settlement.example' }
```

Une raison absente de la liste repose sur les conditions, sans disposition ; `illegal` repose sur la loi, et une
restriction pour contenu illégal nomme la loi sur laquelle elle repose, depuis la configuration ou depuis le modérateur.
Les adresses sont des chemins du site ou des adresses `https` ; tout autre schéma est refusé à la construction du
conteneur.

## Où le membre le lit

| Page | Route | Ce qu'elle fait |
|---|---|---|
| Ses exposés | `phil_moderation_statements` | Les exposés adressés au membre courant, le plus récent d'abord |
| Un exposé | `phil_moderation_statement` | Un exposé, pour le membre à qui il est adressé |

Importez-les derrière le pare-feu qui connaît les membres :

```yaml
phil_moderation_statements:
    resource: '@PhilModerationBundle/config/routes/statement.php'
    prefix: /moderation/statements
```

Un visiteur se heurte au pare-feu, comme devant toute page refusée. **Un exposé adressé à quelqu'un d'autre est
introuvable.** Les pages sont `private, no-store`.

## Informer le membre

`StatementIssued` est réparti après `DecisionTaken`, avec l'exposé. Quand `phil/notifications-bundle` est enregistré et
que `statements.notification_type` nomme un type que l'application a déclaré, le bundle envoie cette notification au
membre : son sujet est `moderation-statement` et l'identifiant de l'exposé, jamais son contenu, et elle mène à la page
de l'exposé.

```yaml
phil_notifications:
    types:
        moderation.statement:
            channels: [site, email]

phil_moderation:
    statements:
        notification_type: moderation.statement
```

Un identifiant de membre que les notifications refusent ne reçoit aucune notification ; l'exposé reste sur la page du
membre. Sans `phil/notifications-bundle`, un écouteur de `StatementIssued` informe le membre autrement, avec
l'identifiant de l'exposé seulement.

## Les désactiver

`statements: false` laisse les décisions sans exposé : le formulaire ne demande aucun motif, et les pages des exposés ne
sont pas enregistrées. La table reste dans le schéma, de sorte que les réactiver n'exige aucune migration.

## Appel

Un membre conteste une décision par la page de plainte que nomme la configuration. Un appel examiné dans le bundle, par
un autre rôle que celui qui a décidé, n'est pas commencé.
