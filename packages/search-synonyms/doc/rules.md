# Rules

| Rule | Built with | A search for |
|---|---|---|
| Equivalent | `SynonymSet::equivalent(['jdr', 'jeu de rôle'])` | either finds both |
| One-way | `SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon'])` | `donjon` finds `donjon` and `dungeon`; `dungeon` finds only itself |

A one-way rule replaces the phrases on its left by those on its right: list a left phrase on the right too to keep
finding it. `$from` and `$to` hold the two sides — the same list for an equivalent rule —, `$equivalent` says which
kind a rule is, and `phrases()` gives every phrase once.

## Phrases

Each phrase is kept as a search compares words: in lower case, every run of white space — a tab, a line break, a
no-break space — one space, none around it. Once kept so, a phrase appears once in a rule.

| Limit | Value |
|---|---|
| A phrase | 1 to 100 characters, 8 words at most, valid UTF-8 without control characters |
| A rule | 64 phrases at most; two or more for an equivalent rule; one or more on each side of a one-way rule, which must change something |
| A language | 10 000 rules at most |

A rule outside these limits is refused with `InvalidSynonyms` when it is built.

## A language

`SynonymList::of('fr-CA', $sets)` holds the rules of a language, in their order, under its primary subtag in lower
case: `fr`. `with($set)` gives the same list with one more rule.

## The validator

`SynonymValidator::validate($list)` lists what is allowed but likely a mistake, as `Violation` — the position of the
rule from 0, a code and a sentence —, in the order of the rules:

| Code | When | Why it matters |
|---|---|---|
| `overlap` | A phrase is on the left of a second rule | A search reads it as both rules merged |
| `chain` | A one-way rule leads to a phrase another rule rewrites | Synonyms are applied once: the second rule is not followed |

An administration shows them before saving; nothing stops a list that has some.
