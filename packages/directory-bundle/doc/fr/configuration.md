<!-- phil-dev source-sha256=234e1757661c5ed9c7842aad4131f72a01fb9afeab05019700995b7cf42f2b07 -->
# Configuration

```yaml
# config/packages/phil_directory.yaml
phil_directory:
    genres:
        podcast: { path: podcasts, title: Podcasts }
        shop:    { path: shops/local, title: Local shops }
    relations:
        heard_on: { from: shop, to: podcast, multiple: false, title: Heard on, inverse_title: Shops heard here }
    admin_role: ROLE_ADMIN
    publish_every_change: false
    page_size: 50
    sitemap_locale: '%kernel.default_locale%'
    allow_localhost: false
    words:
        new: new
        edit: edit
        editors: editors
        history: history
        publish: publish
```

| Clé | Valeur par défaut | Signification |
|---|---|---|
| `genres` | aucune | Les genres, par clé : une lettre minuscule suivie d'au plus 63 lettres minuscules, chiffres ou traits de soulignement. Chacun a un `path`, débarrassé de ses barres obliques, et un `title`. |
| `relations` | aucune | Les relations entre genres, par clé — de la même forme que celle d'un genre. Chacune a un genre `from` et un genre `to`, tous deux déclarés, un `title` et un `inverse_title`, et `multiple`, `true` par défaut. Voir plus bas. |
| `admin_role` | `ROLE_ADMIN` | Le rôle qui administre le répertoire : il publie, modifie toutes les fiches et nomme des rédacteurs sur n'importe laquelle. |
| `publish_every_change` | `false` | Publie sur-le-champ la modification d'un auteur ou d'un rédacteur au lieu de la conserver comme brouillon pour un administrateur. |
| `page_size` | `50` | Le nombre de fiches qu'énumère une page d'un genre, de 1 à 500. |
| `sitemap_locale` | `%kernel.default_locale%` | La locale dont le plan du site énumère les fiches publiées. |
| `allow_localhost` | `false` | Si les pages du plan du site peuvent être sur `localhost` ou sous `.localhost`, que `phil/sitemap` refuse autrement : `true` sous `when@dev` et `when@test` seulement. |
| `words` | anglais | Les mots des adresses : `podcasts/new`, `podcasts/{id}/edit`. Un site en français écrit `ajouter`, `modifier`. |

## Les genres partagent une table

Chaque fiche se trouve dans `phil_directory_listing`, avec son genre dans une colonne indexée. Un genre n'est pas une
classe : en ajouter un demande une ligne de configuration, et une fiche garde son genre à vie. Retirer un genre de la
configuration masque ses fiches et laisse leurs lignes en place.

## Les relations entre genres

Une relation indique qu'une fiche d'un genre est liée à des fiches d'un autre : un GN se joue sur des terrains de GN, un
terrain de GN accueille plusieurs GN.

```yaml
phil_directory:
    genres:
        larp:      { path: larps, title: Larps }
        larp_site: { path: larp-sites, title: Larp sites }
    relations:
        played_at:
            from: larp
            to: larp_site
            title: Played at
            inverse_title: Larps played here
```

- La relation s'écrit sur la fiche de `from`, dans son formulaire : un choix parmi les fiches publiées de `to`,
  plusieurs sauf avec `multiple: false`. Les fiches qu'elle nomme déjà restent parmi les choix, publiées ou non, afin
  que l'enregistrement du formulaire les conserve ; une relation qui devient `multiple: false` garde la première.
- Elle est montrée sur les deux : sous `title` sur le GN, sous `inverse_title` sur chaque terrain de GN qu'elle nomme.
- Elle appartient à la version, comme chaque champ : la modification d'un auteur attend comme brouillon, sauf si
  `publish_every_change` est actif, et une page ne montre que ce qui est publié, des deux côtés.
- Quiconque peut modifier une fiche la lie à n'importe quelle fiche de l'autre genre, qui la montre alors sous son titre
  inverse : l'administrateur qui publie la modification est celui qui la vérifie. Avec `publish_every_change`, personne
  ne la vérifie.
- Deux genres peuvent avoir plusieurs relations, et un genre peut être lié à lui-même ; une fiche n'est jamais liée à
  elle-même.
- Retirer une relation de la configuration la masque ; la prochaine modification d'une fiche qui porte `related` la
  retire de ses données.
