# Synonyms

A search reads its words with the synonyms of their language. The language is the one the query gives, else that of
its filter `locale` when it is one value — `fr_CA` reads as `fr` —; a query with no language is searched as it is.

## Rules

| Rule | Written | A search for |
|---|---|---|
| Equivalent | `SynonymRule::equivalent(['jdr', 'jeu de rôle'])` | either finds both |
| One-way | `SynonymRule::oneWay(['donjon'], ['donjon', 'dungeon'])` | `donjon` finds `donjon` and `dungeon`; `dungeon` finds only itself |

A one-way rule replaces the phrases on its left by those on its right, as the Solr format does: list the left phrase
on the right too to keep finding it. Each phrase is compared in lower case, every run of white space one space; it is
1 to 100 characters and 8 words at most, and a rule holds 64 phrases at most.

## Providers

Every service implementing `SynonymProvider` is tagged `phil_search.synonym_provider`, and the rules of all of them
are read together.

| Source | Configured with |
|---|---|
| Files of the project, one per language in the Solr format — `config/synonyms/fr.txt` —, through `phil/search-synonyms` | `synonyms.directory` |
| A store of `phil/search-synonyms` of the application's own — a table the administration edits | `synonyms.store`, its service |
| The forms a vocabulary rejects for its preferred terms, each a service implementing `RejectedForms` | `synonyms.rejected_forms`, their services |
| Any other source | a service implementing `SynonymProvider` |

```yaml
phil_search:
    synonyms:
        directory: '%kernel.project_dir%/config/synonyms'
        rejected_forms: ['app.taxonomy.rejected_forms']
        languages: ['fr', 'en']   # for Meilisearch, see below
```

A rejected form is a one-way synonym of itself and its preferred term: `D&D` finds `D&D` and `Donjons et Dragons`,
and `Donjons et Dragons` finds only itself. A form that cannot be a synonym — too long, the same as its term — is left
out.

## How a query is expanded

`SynonymExpander` reads the rules of a language from every provider at the first search of that language, then keeps
them until the container is reset, between two requests of a worker. From the left of the query, the longest phrase a
rule knows becomes one term with its alternatives — every phrase of an equivalent rule, the right side of a one-way
rule —, then the next word, until the end. A term keeps 32 alternatives at most, the first ones read; a phrase on the
left of several rules gets the alternatives of all of them. Rules are applied once, never in chain.

`jeu de rôle donjon`, with the rules above, is two terms: `jeu de rôle | jdr`, and `donjon | dungeon`. The database
index finds a document holding one alternative of every term.

## Engines that keep synonyms

Meilisearch keeps one list of synonyms per index and applies it itself: the bundle gives it the synonyms of the
languages of `synonyms.languages`, merged, after each rebuild and with `phil:search:synonyms`, run once the synonyms
changed. A search there sends the words as they are. Meilisearch adds synonyms and never replaces a word, so a one-way
rule still finds the word on its left; and its list is the same for every language of the index — a word that means
two things in two languages is better searched in an index per language.

Loupe reads neither the alternatives of a query nor a list of its own: with `adapter: loupe`, synonyms are not
applied.

```bash
bin/console phil:search:synonyms          # every index
bin/console phil:search:synonyms games    # one
```
