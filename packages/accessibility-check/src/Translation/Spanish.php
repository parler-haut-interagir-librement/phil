<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

/**
 * The messages of the rules in Spanish, by key.
 */
final class Spanish
{
    public const string LOCALE = 'es';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'image-alt.missing' => 'La imagen {source} no tiene alternativa textual: descríbala en un texto alternativo, o dele alt="" si solo es decoración.',
        'image-alt.blank' => 'La alternativa textual de la imagen {source} solo contiene espacios: describa la imagen, o dele alt="" si solo es decoración.',
        'image-alt.filename' => 'La alternativa textual de la imagen {source} es un nombre de archivo, «{alt}»: describa lo que muestra la imagen.',
        'heading-order.skipped' => 'Este encabezado h{level} sigue a un h{previous}: se salta un nivel.',
        'heading-h1.multiple' => 'Este es el encabezado de primer nivel número {position}: una página tiene uno.',
        'heading-h1.forbidden' => 'Este contenido tiene un encabezado de primer nivel, que ya es el título de su página: empiece sus encabezados en h2.',
        'heading-h1.missing' => 'La página no tiene encabezado de primer nivel.',
        'empty-heading.empty' => 'Este encabezado h{level} no tiene texto.',
        'link-name.empty' => 'El enlace a {href} no tiene texto: los lectores de pantalla solo anuncian su dirección.',
        'button-name.empty' => 'Este botón no tiene texto: diga lo que hace.',
        'link-text.generic' => 'El texto del enlace a {href}, «{text}», no dice adónde lleva: describa su destino.',
        'color-contrast.insufficient' => 'El texto «{text}» tiene un contraste de {ratio}:1 entre {foreground} y {background}; necesita {required}:1.',
        'color-contrast.unresolved' => 'El color {value} depende de las hojas de estilo de la página: compruebe el contraste de este texto en un navegador.',
        'table-headers.missing' => 'Esta tabla no tiene celdas de encabezado: marque sus encabezados con th, o active la fila de encabezado del bloque.',
        'html-lang.missing' => 'La página no indica su idioma: dé un atributo lang al elemento html.',
        'html-lang.invalid' => 'El idioma de la página, «{lang}», no es una etiqueta de idioma válida.',
        'lang-change.invalid' => '«{lang}» no es una etiqueta de idioma válida.',
        'lang-change.empty' => 'Este pasaje está marcado como escrito en un idioma desconocido.',
        'lang-change.direction-only' => 'Este pasaje cambia de dirección sin indicar su idioma.',
        'frame-title.missing' => 'El marco {source} no tiene título: diga lo que contiene.',
        'video-captions.missing' => 'El vídeo {source} no tiene pista de subtítulos.',
        'duplicate-id.duplicate' => 'El identificador «{id}» se usa {count} veces: una referencia a él solo alcanza el primero.',
        'svg-name.missing' => 'Este dibujo no tiene título: dele un elemento title, o aria-hidden="true" si solo es decoración.',
        'report.blocked' => 'La comprobación de accesibilidad encontró {count} problema(s) que corregir antes de publicar, empezando por: {message}',
    ];
}
