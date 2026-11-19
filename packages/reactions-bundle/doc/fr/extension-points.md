<!-- phil-dev source-sha256=a8b1c43aa249aa77d3cfa7c0066af7bfa956a4348aeba673743bb5e6be03b054 -->
# Points d'extension

| Point | Comment |
|---|---|
| Qui peut réagir | Un *voter* sur `phil_reactions.react`, au sujet d'un `ReactionSubject` ; ou un `ReactionAccess` nommé par `access` |
| Qui est le membre | Un `CurrentMember` nommé par `current_member`, qui donne un identifiant stable sans `@` ; obligatoire avec Security |
| Combien de réactions un membre donne à un contenu | `per_member`, de 1 à 20 |
| Comment les membres sont nommés | Un `MemberNames` nommé par `member_names` |
| Qui voit quoi, contenu par contenu | Une `VisibilityPolicy` nommée par `visibility_policy` |
| D'autres réactions | Un service qui implémente `ReactionSource`, configuré automatiquement ; leur place avec `order` |
| L'emplacement d'une image | Le service `EmojiImages`, décoré ou remplacé : `urls()` reçoit toutes les déclarations d'image à la fois |
| La vitesse à laquelle un membre peut réagir | Le limiteur `phil_reactions` de `framework.rate_limiter`, ou `rate_limiter` |
| Ce qui se passe après une réaction | Un écouteur de `ReactionAdded` et de `ReactionRemoved` ; `messenger: true` pour un travail ultérieur |
| Le stockage | Le service `ReactionRepository`, remplacé |
| L'apparence d'un bouton | Les recettes, du code de l'application une fois installées |
| La page après une réaction arrêtée | `templates/bundles/PhilReactionsBundle/stopped.html.twig` |
| Les textes | Le domaine de traduction `phil_reactions` |
| La liste des noms | `format_list` de twig/intl-extra, dans la langue du traducteur ; l'extension de TwigExtraBundle quand elle est enregistrée |
