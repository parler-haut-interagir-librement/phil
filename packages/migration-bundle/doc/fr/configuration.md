<!-- phil-dev source-sha256=31f03ae1afff2b20cdc109a0d341d20d1999ec5a56527e07d281e727a927daaf -->
# Configuration

```yaml
phil_migration:
    channels:
        discord_releases:                 # the transport, unless `transport` names another
            locale: fr
            title: '# Une nouvelle version est en ligne{counter}'
            continued: '## Suite{counter}'
        english:
            transport: discord_releases_en
            locale: en
            title: '# A new release is online{counter}'
        discord_news:
            locale: fr
            content: news
            heading: '# {title}'
            link: 'Pour voir la nouvelle : {url}'
            max_length: 2000
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `transport` | le nom du canal | Le transport de chatter, tel que le nomme `framework.notifier.chatter_transports` |
| `locale` | `%kernel.default_locale%` | La locale dans laquelle l'annonce est lue : une langue, éventuellement suivie d'une région |
| `content` | `notes` | `notes`, les notes de version, ou `news` |
| `title` | `''` | Le titre du premier message ; aucun s'il est vide |
| `continued` | `''` | Le titre des messages qui suivent ; aucun s'il est vide |
| `heading` | `'{title}'` | La ligne d'une nouvelle qui contient son titre |
| `link` | `'{url}'` | La ligne d'une nouvelle qui contient son URL ; aucune si elle est vide |
| `max_length` | `2000` | Le message le plus long qu'accepte le transport, au moins 100 : 2000 caractères sur Discord |

`{counter}` devient ` (2/3)` quand une version prend plus d'un message, et rien quand elle en prend un seul. Un titre
occupe au plus la moitié d'un message. Une note ou une nouvelle qui n'est pas écrite dans la locale d'un canal en est
omise, de sorte qu'un canal par locale annonce une version dans chaque langue.

La façon dont une version est coupée en messages est décrite dans la référence de `phil/migration`.
