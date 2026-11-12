<!-- phil-dev source-sha256=874cadc57436070ae6674755dea8744c2bf016db2c5915ce302987936ef7c939 -->
# Points d'extension

Ce qu'une application configure, remplace, décide ou utilise. Les fiches sont des contenus de `phil/content-bundle`, et
les adresses, les fiches de contact et les positions passent par `phil/address-bundle`, `phil/contact-bundle` et
`phil/geo-bundle`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `genres` | Les sortes de fiches, chacune avec son chemin et son titre |
| `relations` | La façon dont les fiches d'un genre pointent vers celles d'un autre, une ou plusieurs, avec un titre dans chaque sens |
| `admin_role` | Le rôle qui publie, modifie toutes les fiches et nomme des rédacteurs |
| `publish_every_change` | Si une modification est publiée sur-le-champ ou conservée comme brouillon |
| `page_size`, `sitemap_locale` | La liste d'un genre, et la locale qu'énumère le plan du site |
| `words` | Les mots des adresses, `new`, `edit`, dans la langue du site |

Voir [Configuration](configuration.md).

## Accès

Les attributs de *voter* `phil_directory.edit`, `phil_directory.editors` et `phil_directory.publish` décident qui
modifie, gère et publie une fiche ; un *voter* propre à l'application sur les mêmes attributs ajoute ses règles. Les
utilisateurs sont lus par le `getUserIdentifier()` du jeton de sécurité. Voir [Fiches](listings.md#qui-peut-faire-quoi).

## Gabarits et mots

| Point | Comment |
|---|---|
| Les pages | `@PhilDirectory/index`, `show`, `form`, `editors` et `history`, qui étendent chacun `layout.html.twig`, remplacés dans `templates/bundles/PhilDirectoryBundle/` — voir [Routes et pages](routes.md#gabarits) |
| Afficher un nom plutôt qu'un identifiant | Les gabarits `editors` et `history` remplacés |
| Les libellés | Le domaine de traduction `phil_directory` |
| Les formulaires | Une extension de type de formulaire de l'application sur les formulaires de fiche et de rédacteurs |

## Ports

| Port | Fourni | Ce que fait celui de l'application |
|---|---|---|
| `Phil\DirectoryBundle\Application\UserErasure` | `DoctrineUserErasure` | `erase(string $user): int` — retire l'identifiant d'un utilisateur de toutes les fiches et de toutes les versions, en une seule transaction ; renvoie le nombre d'éléments modifiés |
| `Phil\DirectoryBundle\Application\RelatedLookup` | `DoctrineRelatedLookup` | `pointingAt(string $genre, string $relation, string $resourceId): array` — les fiches d'un genre dont la relation pointe vers une ressource |

## Services

| Point | Comment |
|---|---|
| `Directory` | Crée, modifie, nomme des rédacteurs, publie et efface des utilisateurs dans le code |
| Le plan du site | `DirectoryUrlProvider` porte l'étiquette `phil_sitemap.url_provider` : une section par genre |
| Les capacités d'une fiche | `ContactCapable`, `AddressCapable`, `LocationCapable`, `RelatedCapable` et `EditedByCapable`, associées pour `phil/content-bundle` par `phil_content.capability_mapping` |
