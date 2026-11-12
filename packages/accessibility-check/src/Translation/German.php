<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

/**
 * The messages of the rules in German, by key.
 */
final class German
{
    public const string LOCALE = 'de';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'image-alt.missing' => 'Das Bild {source} hat keine Textalternative: Beschreiben Sie es in einem Alternativtext, oder geben Sie ihm alt="", wenn es nur Dekoration ist.',
        'image-alt.blank' => 'Die Textalternative des Bildes {source} enthält nur Leerzeichen: Beschreiben Sie das Bild, oder geben Sie ihm alt="", wenn es nur Dekoration ist.',
        'image-alt.filename' => 'Die Textalternative des Bildes {source} ist ein Dateiname, „{alt}“: Beschreiben Sie, was das Bild zeigt.',
        'heading-order.skipped' => 'Diese Überschrift h{level} folgt auf eine h{previous}: Eine Ebene wird übersprungen.',
        'heading-h1.multiple' => 'Dies ist die Überschrift erster Ebene Nummer {position}: Eine Seite hat eine.',
        'heading-h1.forbidden' => 'Dieser Inhalt hat eine Überschrift erster Ebene, die der Titel seiner Seite bereits ist: Beginnen Sie seine Überschriften mit h2.',
        'heading-h1.missing' => 'Die Seite hat keine Überschrift erster Ebene.',
        'empty-heading.empty' => 'Diese Überschrift h{level} hat keinen Text.',
        'link-name.empty' => 'Der Link zu {href} hat keinen Text: Screenreader kündigen nur seine Adresse an.',
        'button-name.empty' => 'Diese Schaltfläche hat keinen Text: Sagen Sie, was sie tut.',
        'link-text.generic' => 'Der Text des Links zu {href}, „{text}“, sagt nicht, wohin er führt: Beschreiben Sie sein Ziel.',
        'color-contrast.insufficient' => 'Der Text „{text}“ hat einen Kontrast von {ratio}:1 zwischen {foreground} und {background}; er braucht {required}:1.',
        'color-contrast.unresolved' => 'Die Farbe {value} hängt von den Stylesheets der Seite ab: Prüfen Sie den Kontrast dieses Textes in einem Browser.',
        'table-headers.missing' => 'Diese Tabelle hat keine Kopfzelle: Kennzeichnen Sie ihre Überschriften mit th, oder schalten Sie die Kopfzeile des Blocks ein.',
        'html-lang.missing' => 'Die Seite nennt ihre Sprache nicht: Geben Sie dem Element html ein Attribut lang.',
        'html-lang.invalid' => 'Die Sprache der Seite, „{lang}“, ist kein gültiges Sprach-Tag.',
        'lang-change.invalid' => '„{lang}“ ist kein gültiges Sprach-Tag.',
        'lang-change.empty' => 'Diese Passage ist als in einer unbekannten Sprache geschrieben gekennzeichnet.',
        'lang-change.direction-only' => 'Diese Passage ändert die Schreibrichtung, ohne ihre Sprache zu nennen.',
        'frame-title.missing' => 'Der Rahmen {source} hat keinen Titel: Sagen Sie, was er enthält.',
        'video-captions.missing' => 'Das Video {source} hat keine Untertitelspur.',
        'duplicate-id.duplicate' => 'Der Bezeichner „{id}“ wird {count} Mal verwendet: Ein Verweis darauf erreicht nur den ersten.',
        'svg-name.missing' => 'Diese Zeichnung hat keinen Titel: Geben Sie ihr ein Element title, oder aria-hidden="true", wenn sie nur Dekoration ist.',
        'report.blocked' => 'Die Barrierefreiheitsprüfung hat {count} Problem(e) gefunden, die vor der Veröffentlichung zu beheben sind, zuerst: {message}',
    ];
}
