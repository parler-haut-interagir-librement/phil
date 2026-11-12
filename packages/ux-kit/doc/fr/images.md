<!-- phil-dev source-sha256=858aad493e844de04dcf501dad9e53f809c1c3bde3191d2cb70b24ca1e722d67 -->
# Images

Comment choisir le `layout` et les `sizes` d'une image, ce dont l'image principale d'une page a besoin pour être peinte
tôt, et comment passer des éléments `img` et `picture` qu'un gabarit écrit à la main aux recettes `Image` et `Picture`.
Les props elles-mêmes sont décrites dans la [recette d'image](../../image/README.md).

## Choisir layout et sizes

Le navigateur choisit un candidat d'un `srcset` de largeurs avant de mettre la page en forme : `sizes` lui dit à quelle
largeur l'image sera dessinée. Un `sizes` trop large télécharge un fichier plus lourd que nécessaire ; un `sizes` trop
étroit, un fichier flou. `layout` l'écrit pour les cas courants, à partir de `width`.

| L'image | `layout` | `sizes` écrit | `srcset` |
|---|---|---|---|
| Garde une seule largeur sur tous les écrans : un avatar, un logo, une icône | `fixed` | aucun | densités, `{1: …, 2: …}` |
| Grandit avec son conteneur jusqu'à sa propre largeur : l'image d'un article | `constrained` | `(min-width: <width>px) <width>px, 100vw` | largeurs, jusqu'au double de `width` |
| Couvre son conteneur sur tous les écrans : une bannière, un héros | `full-width` | `100vw` | largeurs, jusqu'au plus large écran servi |
| Occupe une colonne d'une grille ou d'une galerie | aucun | le vôtre : `(min-width: 48rem) 33vw, 100vw` | largeurs |
| N'a qu'un fichier | aucun, ou `fixed` | aucun | aucun |

Choisir dans cet ordre :

1. **Une seule largeur ?** `fixed`, et une table de densités pour les écrans de densité 2 et plus. Pas de `sizes`.
2. **Aussi large que son conteneur, quel que soit l'écran ?** `full-width`. Quand le conteneur a des marges, donner
   `sizes` soi-même, `calc(100vw - 2rem)`, pour serrer au plus juste.
3. **Jamais plus large que son fichier ?** `constrained`, avec pour `width` la largeur du plus grand fichier.
4. **Dans une grille ?** Aucun layout : écrire `sizes` comme la grille se comporte, une condition par point de rupture,
   la plus large d'abord, la dernière sans condition. `Gallery` passe son propre `sizes` à chaque image.

Quelques règles valent dans tous les cas :

- Toujours donner `width` et `height`, la taille intrinsèque du fichier : le navigateur réserve la place à ce rapport
  avant le chargement, et la page ne bouge pas. `aspect` recadre à un autre rapport sans les changer.
- Une image différée ajoute `auto, ` devant le `sizes` écrit : là où le navigateur le prend en charge, il mesure la
  boîte une fois la page en forme. L'image principale, chargée tout de suite, garde le `sizes` tel qu'il est écrit.
- Des candidats espacés d'un facteur 1,5 à 2 suffisent ; davantage de fichiers ne fait que remplir le cache du serveur.
- `sizes` s'écrit en pixels CSS ou en unités de la fenêtre, jamais en pour cent du conteneur.

## Liste de contrôle du LCP

Le LCP (largest contentful paint) est le moment où la plus grande image ou le plus grand bloc de texte du premier écran
est peint. Sur la plupart des pages, c'est une image : le héros, la couverture d'un article, la première photo d'une
galerie.

- [ ] Une seule image par page, la plus grande du premier écran, porte `priority`. Une seule : en précharger plusieurs
      partage la bande passante entre elles.
- [ ] Elle n'est pas différée : `priority` écrit `loading="eager"` ; ne pas lui mettre `loading="lazy"`.
- [ ] Elle est préchargée : `priority` demande à WebLink un en-tête `Link` ; vérifier que la réponse le porte et,
      derrière FrankenPHP, l'envoyer en early hint (103).
- [ ] Son `srcset` et son `sizes` sont ceux de l'image, pour que le préchargement télécharge le fichier même que la page
      dessine.
- [ ] Un `Picture` avec une requête `media`, ou une variante sombre ou immobile, ne peut pas être préchargé : garder
      cette direction artistique pour les images plus bas, ou accepter une peinture plus tardive.
- [ ] Son fichier est d'abord en AVIF ou en WebP, à la taille de son `sizes`, pas l'original de l'appareil photo.
- [ ] Elle est servie par l'hôte du site, ou par un hôte listé sous `phil_ux_kit.image.hosts` et ouvert tôt par un
      `preconnect`.
- [ ] Aucun aperçu flou par-dessus : `preview` sert aux images plus bas ; l'image principale est peinte à mesure
      qu'elle arrive.
- [ ] Ses `width` et `height` sont donnés, pour qu'elle ne pousse pas le texte en arrivant (cumulative layout shift).
- [ ] Les images sous la ligne de flottaison restent différées, et celles très bas portent `deferred`.
- [ ] Mesuré : l'élément du LCP et son temps dans le panneau de performance du navigateur, sur une connexion ralentie.

## Migrer les images écrites à la main

`phil:ux-kit:migrate-images` lit les gabarits Twig d'un projet, `templates/` par défaut, et liste chaque élément `img`
et `picture` avec la recette proposée à sa place, ou ce qui s'y oppose. Elle ne propose que ce qu'elle comprend, et ne
change rien tant qu'on ne le lui demande pas.

```sh
php bin/console phil:ux-kit:migrate-images
php bin/console phil:ux-kit:migrate-images templates/page templates/base.html.twig
php bin/console phil:ux-kit:migrate-images --write
```

```text
templates/page/show.html.twig:12 proposed
  - <img src="{{ asset('images/hall.png') }}" alt="The hall" width="960" height="640" loading="lazy">
  + <twig:Image src="{{ asset('images/hall.png') }}" alt="The hall" :width="960" :height="640" />
templates/page/show.html.twig:20 left as it is
  - <img src="{{ asset('images/plan.png') }}" class="w-100">
    The image has no text alternative: write its alt, or alt="" for a decorative image.
    The image has no width: give its size, so that its space is kept while it loads.
    The image has no height: give its size, so that its space is kept while it loads.
1 element(s) proposed, 1 left as they are.
```

Ce qu'elle propose :

- un `img` dont chaque attribut a sa place dans `Image`, avec son `src`, son `alt`, même vide, son `width` et son
  `height`, des nombres ou imprimés par `{{ … }}` ; `42px` perd son unité, et le dit ;
- un `picture` d'éléments `source` et d'un seul tel `img`, en `Picture` dont les `sources` sont écrites en expressions
  Twig ;
- `loading="lazy"` et `decoding="async"` retirés, puisque ce sont les valeurs par défaut de la recette ;
  `fetchpriority="high"` en `priority`, qui précharge aussi l'image, et `fetchpriority="low"` en `deferred` ;
- `class`, `id`, `title`, `crossorigin`, `referrerpolicy`, `itemprop`, `draggable`, `data-*` et `aria-*` passés tels
  quels.

Ce qu'elle laisse, à réécrire par une personne : une image sans `alt`, `width` ou `height` ; une taille en pour cent ;
un attribut sans place dans la recette, `style` ou un gestionnaire d'événement ; une balise qui contient un bloc
`{% … %}` ou imprime ses attributs d'un bloc ; un `picture` qui contient autre chose que ses sources et une image ; une
valeur de source imprimée avec une chaîne entre guillemets doubles. Une image d'un autre hôte est proposée avec une
note : son hôte va sous `phil_ux_kit.image.hosts`, sinon la recette la refuse. Les commentaires, de Twig et de HTML,
sont sautés.

`--write` réécrit les seules propositions, sur place, et laisse tous les autres éléments tels qu'ils sont. Lire d'abord
les propositions, puis commiter avant d'écrire, pour que le changement soit un seul diff à relire. La commande vient
avec le bundle du kit, dans une application qui a le composant Console.
