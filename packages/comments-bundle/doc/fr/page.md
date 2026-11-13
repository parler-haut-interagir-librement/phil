<!-- phil-dev source-sha256=8660694ae7e6830b1f6dc00348febe8f736dc1c7b6353591dcae1dbcaf5b3838 -->
# La page

Sous un article publié, sur une page d'un site, sur une fiche de produit, les lecteurs commentent la page entière. Le
contrôleur des commentaires le fait à côté de l'éditeur par blocs ; là où une page n'a aucun script, le composant Twig
`PhilComments:Page` dessine les mêmes fils côté serveur, et chaque action est un formulaire simple qui envoie ses
données et revient à la page.

```twig
<twig:PhilComments:Page subject="article:42" />
```

`subject` est une `ResourceReference` ou son texte. Le composant demande Symfony UX Twig Component, la route de
[`config/page_routes.php`](installation.md#les-routes) et les recettes du kit
([installation](installation.md#les-recettes-du-kit)).

## Ce qu'il dessine

Une `<section id="phil-comments-page">` intitulée *On the page* :

- pour une personne non connectée — le `AuthorProvider` de l'application ne nomme personne —, une `Alert` qui demande
  de se connecter, et aucun formulaire ;
- pour un lecteur que la politique ne laisse pas lire (`mayRead`), une phrase qui le dit ;
- sinon, les fils sur la page entière, du plus ancien au plus récent, chacun dans une `Card` : un `Badge` *Resolved
  by …* dans son en-tête quand il est résolu, ses commentaires — auteur, date, *edited* quand ils ont changé —, et dans
  son pied le formulaire de réponse tant qu'il est ouvert et le bouton *Resolve* ou *Reopen* quand la politique le
  permet (`mayResolve`) ;
- sous chaque commentaire que son auteur peut encore modifier, ou qu'un modérateur peut modifier (`mayModerate`), un
  `<details>` *Edit* avec son formulaire, et *Delete* ;
- enfin, quand le lecteur peut commenter (`mayComment`), le formulaire *Comment on the page*.

Les fils sur des passages ne sont pas listés : ils appartiennent au contrôleur des commentaires, à côté du texte. Les
textes sont échappés par Twig ; rien de ce qu'écrivent les lecteurs n'est lu comme du HTML.

| Recette | Où |
|---|---|
| `Alert`, `Alert:Description` | La demande de connexion |
| `Card`, `Card:Header`, `Card:Content`, `Card:Footer` | Chaque fil |
| `Badge` | Un fil résolu |
| `Field`, `Field:Label`, `Textarea` | Le texte de chaque formulaire et son libellé |
| `Button` | Chaque action |

Les recettes se trouvent sous le sous-répertoire des composants de la configuration `ux_toolkit` ; dans un
environnement où le Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans l'option
`kit_subdirectory`. Elles ont les noms, les props et les blocs du kit Shadcn : la section est donc dessinée de la même
façon avec PHIL UX ou avec Tailwind.

## Les formulaires simples

Chaque formulaire envoie ses données à la route `phil_comments_page`, à une adresse signée par le `UriSigner` de Symfony
qui porte la page commentée et la page où revenir : un navigateur ne peut changer ni l'une ni l'autre. Un formulaire
envoie `_token`, `do` — `open`, `reply`, `edit`, `delete`, `resolve` ou `reopen` —, `id`, le fil ou le commentaire sur
lequel il agit, et `body`, le texte.

| Vérification | Réponse quand elle échoue |
|---|---|
| Le fournisseur d'auteurs et la politique sont configurés | `404` |
| L'adresse est signée | `403`, et le retour à la page d'accueil |
| La vérification CSRF sans état de Symfony du jeton `phil_comments` : le `Sec-Fetch-Site`, l'`Origin` ou le `Referer` du navigateur nomme ce site | `403` |
| Quelqu'un est connecté | `401` |
| Le texte est gardé, l'action est connue | `400` |
| La politique | `403` |
| Le fil ou le commentaire est toujours là | `404` |

Une fois l'action faite, le navigateur revient à la page avec `303 See Other`, à `#phil-comments-page`. Quand elle est
arrêtée, il reçoit la page `@PhilComments/stopped.html.twig`, qui dit pourquoi, garde le texte envoyé dans un champ en
lecture seule à copier et renvoie vers la page ; une application l'habille en remplaçant le gabarit sous
`templates/bundles/PhilCommentsBundle/stopped.html.twig`. Chaque événement de [Fils](threads.md#les-événements) est
réparti comme pour les points de terminaison : `phil/mentions-bundle` enregistre donc aussi les mentions d'un commentaire
envoyé par un formulaire simple.

## Mentions

Avec `phil/mentions-bundle` activé et les routes de l'éditeur par blocs importées, chaque zone de texte de la section
prend le contrôleur `mention` de ce bundle, dont l'autocomplétion `@` aide là où JavaScript s'exécute. Sans lui, les
zones de texte restent simples, et les identifiants tapés sont retrouvés quand même sur le serveur.

## Les rôles de WAI-ARIA 1.3

Avec l'option `aria_roles`, chaque commentaire de la section est enveloppé dans un élément de rôle `comment`, comme dans
le panneau du contrôleur ([JavaScript](javascript.md#les-rôles-de-wai-aria-13)).

## Les libellés

Les libellés vivent dans le domaine de traduction `phil_comments`, en anglais et en français, sous les clés `page.*` ;
une application en remplace un dans son propre fichier `translations/phil_comments.<locale>`.
