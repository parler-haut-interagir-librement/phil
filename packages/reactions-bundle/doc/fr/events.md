<!-- phil-dev source-sha256=175a5bf352aebcef2ea43a9c6f1736c14192648ad69ac074f8e253e1201938cf -->
# Événements

| Événement | Quand | Propriétés |
|---|---|---|
| `ReactionAdded` | Un membre a choisi une réaction | `content`, `member`, `code`, `at` |
| `ReactionRemoved` | Un membre a retiré une réaction | `content`, `member`, `code`, `at` |

Changer une réaction, c'est un `ReactionRemoved`, puis un `ReactionAdded`. Rien n'est réparti quand rien ne change. La
modération d'un contenu, ses notifications et la réputation d'un membre écoutent ces événements ou lisent le stockage :
aucune d'elles ne fait partie du bundle.

## Sur le bus de messages

Les événements ne contiennent que des identifiants : une référence, l'identifiant d'un membre, un code et un moment.
Avec `messenger: true`, chacun est aussi envoyé sur le bus par défaut, et l'application l'achemine vers un transport et
le traite là. Sans *handler* ni route, un bus synchrone refuse le message : activez l'option une fois que quelque chose
le consomme.

## L'effacement

Quand `phil/privacy-bundle` est présent, `ReactionsPrivacy` se joint aux exportations et aux effacements d'une personne,
la personne concernée étant l'identifiant du membre. Une exportation liste les réactions du membre, les privées aussi ;
un effacement les supprime et les compteurs baissent. Aucun des deux ne répartit d'événement.

Quand un contenu est supprimé, l'application appelle `Reactions::eraseContent()`.
