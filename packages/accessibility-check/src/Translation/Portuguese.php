<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

/**
 * The messages of the rules in Portuguese, by key.
 */
final class Portuguese
{
    public const string LOCALE = 'pt';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'image-alt.missing' => 'A imagem {source} não tem alternativa textual: descreva-a num texto alternativo, ou dê-lhe alt="" quando for apenas decoração.',
        'image-alt.blank' => 'A alternativa textual da imagem {source} contém apenas espaços: descreva a imagem, ou dê-lhe alt="" quando for apenas decoração.',
        'image-alt.filename' => 'A alternativa textual da imagem {source} é um nome de ficheiro, «{alt}»: descreva o que a imagem mostra.',
        'heading-order.skipped' => 'Este título h{level} segue um h{previous}: um nível é saltado.',
        'heading-h1.multiple' => 'Este é o título de primeiro nível número {position}: uma página tem um.',
        'heading-h1.forbidden' => 'Este conteúdo tem um título de primeiro nível, que já é o título da sua página: comece os seus títulos em h2.',
        'heading-h1.missing' => 'A página não tem título de primeiro nível.',
        'empty-heading.empty' => 'Este título h{level} não tem texto.',
        'link-name.empty' => 'A ligação para {href} não tem texto: os leitores de ecrã anunciam apenas o seu endereço.',
        'button-name.empty' => 'Este botão não tem texto: diga o que faz.',
        'link-text.generic' => 'O texto da ligação para {href}, «{text}», não diz para onde leva: descreva o seu destino.',
        'color-contrast.insufficient' => 'O texto «{text}» tem um contraste de {ratio}:1 entre {foreground} e {background}; precisa de {required}:1.',
        'color-contrast.unresolved' => 'A cor {value} depende das folhas de estilo da página: verifique o contraste deste texto num navegador.',
        'table-headers.missing' => 'Esta tabela não tem célula de cabeçalho: marque os seus cabeçalhos com th, ou ative a linha de cabeçalho do bloco.',
        'html-lang.missing' => 'A página não diz a sua língua: dê ao elemento html um atributo lang.',
        'html-lang.invalid' => 'A língua da página, «{lang}», não é uma etiqueta de língua válida.',
        'lang-change.invalid' => '«{lang}» não é uma etiqueta de língua válida.',
        'lang-change.empty' => 'Esta passagem está marcada como escrita numa língua desconhecida.',
        'lang-change.direction-only' => 'Esta passagem muda de direção sem dizer a sua língua.',
        'frame-title.missing' => 'A moldura {source} não tem título: diga o que contém.',
        'video-captions.missing' => 'O vídeo {source} não tem faixa de legendas.',
        'duplicate-id.duplicate' => 'O identificador «{id}» é usado {count} vezes: uma referência a ele só alcança o primeiro.',
        'svg-name.missing' => 'Este desenho não tem título: dê-lhe um elemento title, ou aria-hidden="true" quando for apenas decoração.',
        'report.blocked' => 'A verificação de acessibilidade encontrou {count} problema(s) a corrigir antes de publicar, a começar por: {message}',
    ];
}
