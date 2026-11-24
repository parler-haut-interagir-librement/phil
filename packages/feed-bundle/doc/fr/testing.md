<!-- phil-dev source-sha256=314a37078a132b390d8ab50429ce94efbf858cf808fab20cd26741741f7a6c14 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Les objets-valeurs | Chaque règle et ses bornes, les textes qui ne sont pas en UTF-8, la mise à jour jamais avant la publication, l'empreinte de chaque champ, les formats |
| `AtomWriter`, `RssWriter`, `JsonFeedWriter` | Un flux complet comparé à un fichier écrit une fois et relu — caractères hors ASCII, HTML à échapper, un épisode de balado, des pages et un concentrateur —, un flux vide, les éléments que chaque format exige, l'auteur d'un flux Atom, la description et le contenu de RSS, le texte de JSON Feed, les caractères que XML ne peut pas contenir ; JSON Feed vérifié champ par champ contre sa spécification |
| `Feeds`, `FeedLinkBuilder`, `FeedWriters` | Les noms, un nom refusé ou pris deux fois, les sources indexées une fois, les pages et l'entrée en trop, les flux d'une page, les liens de chaque page, les écrivains des formats servis |
| `AnnounceFeedUpdateHandler`, `AnnounceChangedFeeds` | Sur un client HTTP mock derrière `phil/safe-http` : chaque concentrateur averti de chaque format, un concentrateur privé refusé et non réessayé, un `5xx` et un délai dépassé réessayés une fois que tous les concentrateurs ont été essayés, un flux disparu ; le message qui ne contient que le nom |
| `ContentFeedSource` | Sur des contenus en mémoire : l'ordre, `noindex`, aucune adresse, jamais publié, une ligne sans extrait ni corps, le contenu en trop, l'autorité d'étiquette, les pages du flux |
| `PhilFeedBundle`, `FeedServices` | Les valeurs par défaut, chaque option, les services et leurs étiquettes, les flux de contenus, ce que la configuration refuse |
| Une application entière | FrameworkBundle, TwigBundle, Messenger en mémoire et deux sources : chaque format et son type de média, les pages, les `404`, un flux vide, `ETag`, `Last-Modified` et les `304`, les en-têtes de cache, `phil_feed_links()`, les annonces, et une application sans concentrateur qui ne sert qu'Atom |

```sh
vendor/bin/phpunit packages/feed-bundle/tests
```

## Fuzzing

La cible `write` (`tests/Fuzz/write.php`) construit une entrée à partir d'un titre, d'un résumé, d'un contenu HTML,
d'une catégorie et d'un auteur que personne n'a écrits, puis l'écrit dans les trois formats : l'entrée est refusée avec
`InvalidFeed`, ou chaque document se relit — du XML que DOM analyse, avec son titre, et du JSON que `json_decode()`
lit. Depuis la racine du monodépôt :

```sh
composer fuzz -- write
```
