<!-- phil-dev source-sha256=be198026067c045da2d78c567be46a5ea2a230dcf58e0f40ded245c29bae03be -->
# phil/citations

**Citations et bibliographies dans les styles courants : références conservées en CSL-JSON, importées de BibTeX et de
RIS, et écrites dans un style auteur-date, à notes ou numérique, en anglais ou en français, en texte ou en HTML
échappé.**

phil/citations conserve chaque document cité comme une référence CSL-JSON typée — son genre parmi les types de
documents de CSL 1.0.2, ses noms en leurs parties, ses dates partielles ou en intervalle, ses autres variables comme
texte — et lit et écrit du CSL-JSON, le format d'échange des gestionnaires de citations. Elle importe BibTeX, avec ses
macros `@string`, ses accents LaTeX et son `crossref`, et RIS, le format à balises des gestionnaires de références et
des catalogues de bibliothèques ; les deux lecteurs sont bornés contre les fichiers hostiles et soumis au fuzzing.

Les citations d'un texte passent par un seul processeur, avec un style et les termes d'une langue. Trois styles sont
écrits en PHP : auteur-date à la manière d'APA, notes et bibliographie à la manière de Chicago, numérique à la manière
d'IEEE. Le processeur résout les doublons, numérote et ordonne la bibliographie comme le style le demande, donne les
lettres d'année (« 2020a ») et distingue une première citation d'une citation suivante ; un autre style se branche par
une seule interface.

C'est une bibliothèque PHP simple, bâtie sur `ext-intl`, `ext-mbstring` et `ext-ctype`. Elle ne contient aucun fichier
de style CSL : un processeur de styles CSL que l'application fournirait serait un style de plus.

## État

| Partie | État |
|---|---|
| Le modèle CSL-JSON : types de documents, noms, dates partielles et en intervalle, variables | Implémenté et testé |
| CSL-JSON lu et écrit, borné en taille, en éléments et en profondeur | Implémenté et testé |
| Import BibTeX : macros, concaténation, accents et commandes LaTeX, noms, `crossref`, dates BibLaTeX | Implémenté et testé |
| Import RIS : types, balises, lignes continuées, noms, identifiants fabriqués | Implémenté et testé |
| Doublons par DOI, ou par titre, année et premier auteur | Implémenté et testé |
| Styles auteur-date, à notes et numérique, en anglais et en français, en texte et en HTML échappé | Implémenté et testé |
| Limites contre les entrées hostiles, cibles de fuzzing pour les trois lecteurs | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Références et import](doc/fr/references.md) | Le modèle, CSL-JSON, BibTeX, RIS, les limites et les doublons. |
| [Citer](doc/fr/citing.md) | Les citations, le processeur, les trois styles, les termes, le texte et le HTML. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, ses cibles de fuzzing et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/citations
```

```php
use Phil\Citations\Citation;
use Phil\Citations\CitationProcessor;
use Phil\Citations\Cite;
use Phil\Citations\Import\BibTeXReader;
use Phil\Citations\ReferenceList;
use Phil\Citations\Style\AuthorDate;
use Phil\Citations\Terms;

$references = new ReferenceList(new BibTeXReader()->read($bibtex));
$rendered = new CitationProcessor(new AuthorDate(), Terms::english())->render($references, [
    new Citation(new Cite('knuth1984', '12')),
]);

echo $rendered->citations[0]->toHtml();     // (Knuth, 1984, p. 12)
echo $rendered->bibliography[0]->toHtml();  // Knuth, D. E. (1984). <i>The TeXbook</i>. Addison-Wesley.
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-intl`, `ext-mbstring` et `ext-ctype`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
