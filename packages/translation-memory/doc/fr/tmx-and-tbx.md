<!-- phil-dev source-sha256=26f0308ea5c6b6a9f9cc9899b9a62e599dbf1272a2b27e5ab974555117bb0b87 -->
# TMX et TBX

Une mémoire de traduction part et arrive en TMX, un glossaire en TBX : les formats d'échange que lisent et écrivent les
outils de traduction. Les deux lecteurs refusent un fichier qui déclare un type de document avant de l'analyser, ne
récupèrent rien sur le réseau et ne développent aucune entité.

## TMX 1.4b

`TmxWriter` écrit les unités d'une seule langue source ; les unités d'une autre langue source sont omises :

```php
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tmx\TmxReader;
use Phil\TranslationMemory\Tmx\TmxWriter;

$xml = new TmxWriter()->write(LanguageTag::of('en'), $units);   // iterable<MemoryUnit>
$units = new TmxReader()->read($xml);                          // list<MemoryUnit>
```

```xml
<tmx version="1.4">
  <header creationtool="phil/translation-memory" creationtoolversion="0.1" segtype="sentence" o-tmf="phil" adminlang="en" srclang="en" datatype="html"/>
  <body>
    <tu>
      <prop type="x-origin">page:1</prop>
      <tuv xml:lang="en"><seg>A <bpt i="1">&lt;b&gt;</bpt>bold<ept i="1">&lt;/b&gt;</ept> move</seg></tuv>
      <tuv xml:lang="fr"><seg>Un coup <bpt i="1">&lt;b&gt;</bpt>audacieux<ept i="1">&lt;/b&gt;</ept></seg></tuv>
    </tu>
  </body>
</tmx>
```

Chaque unité est un `<tu>` avec sa source et sa cible en `<tuv>` ; son origine, quand elle est connue, est une propriété
`x-origin`. Les balises restent hors des mots qu'un outil peut modifier : une balise ouvrante et la balise fermante qui
lui correspond deviennent `<bpt>` et `<ept>` qui partagent un numéro, toute autre balise un `<ph>`, chacun contenant le
HTML qu'il représente.

`TmxReader` lit du TMX 1.4b, et les versions antérieures dans la mesure où elles concordent. La source d'un `<tu>` est
le `<tuv>` de son propre `srclang`, ou de celui de l'en-tête ; avec `*all*`, son premier `<tuv>`. Chaque autre `<tuv>`
donne une unité de cette source vers sa langue, `xml:lang` ou l'ancien `lang`. Les codes en ligne reviennent sous forme
du HTML qu'ils contiennent ; `<hi>` et `<sub>` gardent leurs mots. Un `<tuv>` sans mots, ou un `<tu>` sans sa source,
est omis. Un fichier contient au plus 100 000 unités (`TmxReader::MAX_UNITS`).

Pour charger un fichier dans une mémoire :

```php
foreach (new TmxReader()->read($xml) as $unit) {
    $store->add($unit);
}
```

`InvalidTmx` est lancée pour un texte qui n'est pas du XML bien formé avec une racine `<tmx>`, un type de document, un
`<header>` ou un `<body>` manquant, une langue qui n'est pas une étiquette, ou trop d'unités.

## TBX

`TbxWriter` écrit un glossaire en TBX de l'ISO 30042:2019, dans le dialecte TBX-Basic et son style DCA ; `TbxReader` lit
les termes d'une paire de langues à partir de ce TBX ou du TBX de 2008, dont la racine est `<martif>`.

```php
use Phil\TranslationMemory\Tbx\TbxReader;
use Phil\TranslationMemory\Tbx\TbxWriter;

$xml = new TbxWriter()->write($glossary);
$glossary = new TbxReader()->read($xml, LanguageTag::of('en'), LanguageTag::of('fr-CA'));
```

| Glossaire | TBX |
|---|---|
| Un terme | Un `<conceptEntry>` (`<termEntry>` en 2008) |
| Sa note | Une `<note>` de l'entrée ; relue à partir d'une `<note>` ou d'une définition |
| Le terme | Le terme préféré du `<langSec>` (`<langSet>`) de la langue source |
| Ses formes fléchies | Les termes admis de la langue source |
| Sa première traduction, ou le terme quand il est conservé | Le terme préféré de la langue cible |
| Les autres traductions et leurs formes | Les termes admis de la langue cible |
| Les traductions refusées | Les termes déconseillés de la langue cible |

Chaque terme est un `<termSec>` (`<tig>` ou `<ntig>` en 2008) avec son `administrativeStatus`. À la lecture, le terme
source est le terme préféré, ou sinon le premier qui n'est pas déconseillé ; les autres termes sources qui ne sont pas
déconseillés sont ses formes. Les termes cibles qui ne sont pas déconseillés sont ses traductions ; les termes
déconseillés, remplacés et non recommandés sont refusés : un terme dont chaque terme cible est refusé se lit comme
conservé. Une entrée qui n'a pas les deux langues est omise. Une langue correspond à son parent : une entrée avec une
section `fr` et sans section `fr-CA` est lue pour `fr-CA`.

Le TBX n'a pas de place pour la casse : un terme sensible à la casse est relu comme un terme qui l'ignore. Un fichier
contient au plus 100 000 entrées (`TbxReader::MAX_ENTRIES`). `InvalidTbx` est lancée pour un texte qui n'est pas du XML
bien formé avec une racine `<tbx>` ou `<martif>`, un type de document, une langue qui n'est pas une étiquette, ou trop
d'entrées.
