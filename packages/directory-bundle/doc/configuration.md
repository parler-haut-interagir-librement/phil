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

| Key | Default | Meaning |
|---|---|---|
| `genres` | none | The genres, by key: a lowercase letter followed by up to 63 lowercase letters, digits or underscores. Each has a `path`, trimmed of its slashes, and a `title`. |
| `relations` | none | The relations between genres, by key — the same form as a genre's. Each has a `from` and a `to` genre, both declared, a `title` and an `inverse_title`, and `multiple`, `true` by default. See below. |
| `admin_role` | `ROLE_ADMIN` | The role that administers the directory: it publishes, changes every listing and names editors on any of them. |
| `publish_every_change` | `false` | Publish the change of an author or an editor at once instead of keeping it as a draft for an administrator. |
| `page_size` | `50` | How many listings a page of a genre lists, from 1 to 500. |
| `sitemap_locale` | `%kernel.default_locale%` | The locale whose published listings the sitemap lists. |
| `allow_localhost` | `false` | Whether the pages of the sitemap may be on `localhost` or under `.localhost`, which `phil/sitemap` refuses otherwise: `true` under `when@dev` and `when@test` only. |
| `words` | English | The words of the addresses: `podcasts/new`, `podcasts/{id}/edit`. A French site writes `ajouter`, `modifier`. |

## Genres share one table

Every listing sits in `phil_directory_listing`, with its genre in an indexed column. A genre is not a class: adding
one is a line of configuration, and a listing keeps its genre for life. Removing a genre from the configuration hides
its listings and leaves their rows.

## Relations between genres

A relation says that a listing of one genre is related to listings of another: a larp is played at larp sites, a
larp site hosts several larps.

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

- The relation is written on the listing of `from`, in its form: a choice among the published listings of `to`,
  several unless `multiple: false`. The listings it already names stay among the choices, published or not, so that
  saving the form keeps them; a relation that becomes `multiple: false` keeps the first.
- It is shown on both: under `title` on the larp, under `inverse_title` on each larp site it names.
- It belongs to the version, as every field does: an author's change waits as a draft unless
  `publish_every_change` is on, and a page shows only what is published, on both sides.
- Whoever may change a listing relates it to any listing of the other genre, which then shows it under its inverse
  title: the administrator who publishes the change is the one who checks it. With `publish_every_change`, nobody
  does.
- Two genres may have several relations, and a genre may be related to itself; a listing is never related to itself.
- Removing a relation from the configuration hides it; the next change of a listing that carries `related` drops it
  from its data.
