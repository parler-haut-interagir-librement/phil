<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

/**
 * The messages of the rules in Italian, by key.
 */
final class Italian
{
    public const string LOCALE = 'it';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'image-alt.missing' => 'L’immagine {source} non ha un’alternativa testuale: descrivila in un testo alternativo, o dalle alt="" se è solo decorazione.',
        'image-alt.blank' => 'L’alternativa testuale dell’immagine {source} contiene solo spazi: descrivi l’immagine, o dalle alt="" se è solo decorazione.',
        'image-alt.filename' => 'L’alternativa testuale dell’immagine {source} è un nome di file, «{alt}»: descrivi ciò che mostra l’immagine.',
        'heading-order.skipped' => 'Questo titolo h{level} segue un h{previous}: un livello è saltato.',
        'heading-h1.multiple' => 'Questo è il titolo di primo livello numero {position}: una pagina ne ha uno.',
        'heading-h1.forbidden' => 'Questo contenuto ha un titolo di primo livello, che è già il titolo della sua pagina: inizia i suoi titoli da h2.',
        'heading-h1.missing' => 'La pagina non ha un titolo di primo livello.',
        'empty-heading.empty' => 'Questo titolo h{level} non ha testo.',
        'link-name.empty' => 'Il link a {href} non ha testo: i lettori di schermo annunciano solo il suo indirizzo.',
        'button-name.empty' => 'Questo pulsante non ha testo: di’ cosa fa.',
        'link-text.generic' => 'Il testo del link a {href}, «{text}», non dice dove porta: descrivi la sua destinazione.',
        'color-contrast.insufficient' => 'Il testo «{text}» ha un contrasto di {ratio}:1 tra {foreground} e {background}; gli serve {required}:1.',
        'color-contrast.unresolved' => 'Il colore {value} dipende dai fogli di stile della pagina: verifica il contrasto di questo testo in un browser.',
        'table-headers.missing' => 'Questa tabella non ha celle di intestazione: segna le sue intestazioni con th, o attiva la riga di intestazione del blocco.',
        'html-lang.missing' => 'La pagina non dice la sua lingua: dai all’elemento html un attributo lang.',
        'html-lang.invalid' => 'La lingua della pagina, «{lang}», non è un tag di lingua valido.',
        'lang-change.invalid' => '«{lang}» non è un tag di lingua valido.',
        'lang-change.empty' => 'Questo passaggio è segnato come scritto in una lingua sconosciuta.',
        'lang-change.direction-only' => 'Questo passaggio cambia direzione senza dire la sua lingua.',
        'frame-title.missing' => 'Il riquadro {source} non ha titolo: di’ cosa contiene.',
        'video-captions.missing' => 'Il video {source} non ha una traccia di sottotitoli.',
        'duplicate-id.duplicate' => 'L’identificatore «{id}» è usato {count} volte: un riferimento a esso raggiunge solo il primo.',
        'svg-name.missing' => 'Questo disegno non ha titolo: dagli un elemento title, o aria-hidden="true" se è solo decorazione.',
        'report.blocked' => 'Il controllo di accessibilità ha trovato {count} problema/i da correggere prima di pubblicare, a partire da: {message}',
    ];
}
