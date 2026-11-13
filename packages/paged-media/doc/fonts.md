# Fonts

A text is written and measured in a `Font`: one of the standard fonts every PDF reader has, or a TrueType font the
document embeds.

## Standard fonts

`StandardFont::Helvetica` and `StandardFont::HelveticaBold` embed nothing, so a document written in them is small.
They are encoded in Windows-1252, which holds the accents of French and the other Western languages. A character it
lacks cannot be printed: the minus sign, the dashes and the narrow and thin spaces common in prices and labels print
as their nearest equivalent, and any other character as `?`.

Each has its own widths, so a right-aligned bold total lines up exactly.

## TrueType fonts

```php
$font = TrueTypeFont::fromFile('/path/to/Font-Regular.ttf');
```

A `TrueTypeFont` prints any character its file holds. The document embeds it **reduced to the glyphs it prints** —
the glyphs used, the components of composite glyphs, and the missing glyph — under their own numbers, with a map back
to Unicode so that text can be copied and searched. A 380 KB font printing one page of text adds about 15 KB.

A character the font lacks prints as the font's missing glyph, usually an empty box.

Only TrueType outlines are read: an OpenType font with PostScript outlines (`CFF`) is refused with `InvalidFont`.
Kerning and ligatures are not applied.

## Licences

The `OS/2` table of a font says what its licence allows, and the library obeys it:

| The font says | The library |
|---|---|
| Installable, editable or print and preview embedding | Embeds the font reduced to the glyphs used |
| No subsetting | Embeds the whole font, without a subset tag |
| Restricted licence embedding, or bitmap embedding only | Refuses the font with `InvalidFont` |

The embedding bits are not the whole licence: an application remains responsible for the right to use the font.

## Measuring and wrapping

```php
$width = $font->width('12,50 $', 10);                       // in points
$lines = Document::wrap($description, 240, 10, $font);      // non-empty list of lines
```

`wrap()` cuts between words, and cuts a word too long for a line between its characters; line breaks and tabs count as
spaces, and an empty text is one empty line.
