<!-- phil-dev source-sha256=eafc5d13f8b22547bee351a8009c0c876c50cd4d1dee69ddd9cd87295c56ff4b -->
# XLIFF

Les segments partent vers un outil de traduction dans un fichier XLIFF et en reviennent.

## Exportation : XLIFF 2.1

```php
$file = XliffFile::fromSegments('page:42', LanguageTag::of('en'), LanguageTag::of('fr'), $segments);
$xml = new XliffWriter()->write($file);
```

```xml
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.1" srcLang="en" trgLang="fr">
  <file id="f1" original="page:42">
    <unit id="u1" name="b1/text">
      <originalData><data id="d1">&lt;b&gt;</data><data id="d2">&lt;/b&gt;</data></originalData>
      <segment state="translated">
        <source>A <ph id="1" dataRef="d1"/>bold<ph id="2" dataRef="d2"/> word</source>
        <target>Un mot <ph id="1" dataRef="d1"/>gras<ph id="2" dataRef="d2"/></target>
      </segment>
    </unit>
  </file>
</xliff>
```

(Le rédacteur ne met aucune espace entre les éléments ; elles sont ajoutées ici pour la lecture.)

- Une unité par segment, identifiée `u1`, `u2`… (les identifiants XLIFF sont des jetons, les clés n'en sont pas) et
  nommée d'après la clé.
- Les balises deviennent des codes `<ph>` dont le HTML se trouve dans le `<originalData>` de l'unité : un outil de
  traduction les affiche comme des codes à placer. Une balise de la cible que la source contient prend le même code.
- Le texte est écrit comme du texte : `&amp;` dans la source est `&` dans le fichier, échappé par le XML.

| Segment | `state` | `subState` |
|---|---|---|
| non traduit | `initial` | — |
| proposé | `initial` | `phil:suggested` |
| traduit | `translated` | — |
| révisé | `reviewed` | — |
| périmé | `initial` | `phil:stale`, et la source antérieure dans `<note category="phil:previous-source">` |

## Importation : XLIFF 2.x et 1.2

```php
$file = new XliffReader()->read($xml);
$result = XliffMerge::merge($segments, $file, LanguageTag::of('en'), LanguageTag::of('fr'));
```

`XliffReader` lit le XLIFF 2.0, 2.1 et 2.2 (espace de noms `urn:oasis:names:tc:xliff:document:2.0`) et le XLIFF 1.2
(espace de noms `urn:oasis:names:tc:xliff:document:1.2`). Un fichier qui déclare un type de document est refusé avant
d'être analysé ; rien n'est récupéré sur le réseau.

| | XLIFF 2.x | XLIFF 1.2 |
|---|---|---|
| Clé | `name`, sinon `id` de l'unité | `resname`, sinon `id` du `trans-unit` |
| Langues | `srcLang`, `trgLang` de la racine | `source-language`, `target-language` du premier `<file>` |
| Codes reconvertis en HTML | `<ph>`, `<sc>`, `<ec>` par `dataRef` ; `<pc>` par `dataRefStart` et `dataRefEnd` ; `<cp>` comme son caractère | Le code natif à l'intérieur de `<ph>`, `<bpt>`, `<ept>`, `<it>` |
| Codes réduits à leurs mots | `<mrk>`, `<pc>` sans données | `<g>`, `<mrk>` |
| Plusieurs segments | Joints, avec les `<ignorable>` entre eux ; l'état le moins avancé | — |

| État dans le fichier | Segment |
|---|---|
| pas de cible, ou une cible sans mots | non traduit |
| 2.x `initial` ; 1.2 `new`, `needs-*` ou un état personnalisé | proposé |
| 2.x `translated` ; 1.2 `translated` ou aucun état | traduit |
| 2.x `reviewed` ou `final` ; 1.2 `final`, `signed-off` ou `approved="yes"` | révisé |

## Fusionner

`XliffMerge::merge()` compare les langues données à celles du fichier, puis applique chaque unité au segment de sa
clé :

| Ignorée comme | Quand |
|---|---|
| `unknown-key` | Aucun segment n'a la clé |
| `source-changed` | La source du segment n'a plus les mots à partir desquels le fichier a été fait |
| `no-target` | L'unité n'a pas de cible avec des mots |
| `unchanged` | La cible et l'état sont ceux du segment, ou la même cible seulement proposée |

Les autres unités sont appliquées comme proposées, traduites ou révisées, selon ce que dit le fichier. Les balises que
la source ne contient pas sont retirées de la cible, leurs mots conservés : un fichier ne peut pas apporter un
balisage que le document n'a jamais eu. Le `MergeResult` contient chaque segment dans son ordre, les clés appliquées
et les unités ignorées.
