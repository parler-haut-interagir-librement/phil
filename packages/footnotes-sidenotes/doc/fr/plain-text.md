<!-- phil-dev source-sha256=d575db80dc644c1f51ea23993882cbd33929d9ec060763347c1e386b01cd94a5 -->
# Texte brut

`Text\NotesPlainText` donne un document avec ses notes sous forme de texte, pour un index de recherche ou une
vérification d'accessibilité.

```php
use Phil\FootnotesSidenotes\Text\NotesPlainText;

echo new NotesPlainText()->convert($document, $notes);
```

```text
Text with a note[1] and another[2].

[1] The first note.
[2] The second.
```

Chaque renvoi est écrit `[label]` ; un renvoi sans note est omis. Les notes figurent une seule fois, après le dernier
bloc, une par ligne, chacune sur une seule ligne. Le `PlainText` de `phil/ujo` dessine les blocs : sans cette classe, un
numéro conservé dans un renvoi se colle au mot qui le précède et chaque note se trouve à l'endroit de son bloc.

Un `PlainText` passé au constructeur conserve les conversions de l'appelant.
