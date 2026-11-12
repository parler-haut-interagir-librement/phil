<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

/**
 * The texts that say nothing of where a link leads — "click here", "ici", "lire la suite" —, by language, and how a
 * link text is compared with them: in lower case, its spaces collapsed, the punctuation and arrows around it left out,
 * the typographic apostrophe read as `'`.
 */
final class GenericLinkText
{
    /**
     * @var array<string, list<string>>
     */
    public const array DEFAULTS = [
        'en' => [
            'click here', 'click', 'here', 'this', 'link', 'this link', 'this page', 'more', 'read more', 'learn more',
            'more info', 'more information', 'find out more', 'see more', 'view more', 'details', 'continue',
            'continue reading', 'go',
        ],
        'fr' => [
            'cliquez ici', 'cliquer ici', 'cliquez', 'ici', 'là', 'lien', 'ce lien', 'cette page', 'plus', 'en savoir plus',
            'lire la suite', 'la suite', 'suite', 'lire plus', 'en lire plus', 'voir plus', 'voir', "plus d'infos",
            "plus d'information", "plus d'informations", 'détails', 'continuer', 'continuer la lecture',
        ],
        'es' => [
            'haga clic aquí', 'haz clic aquí', 'clic aquí', 'pulse aquí', 'aquí', 'enlace', 'este enlace', 'esta página',
            'más', 'leer más', 'saber más', 'ver más', 'más información', 'detalles', 'continuar', 'seguir leyendo',
        ],
        'de' => [
            'hier klicken', 'klicken sie hier', 'klicken', 'hier', 'link', 'dieser link', 'diese seite', 'mehr',
            'weiterlesen', 'mehr lesen', 'mehr erfahren', 'mehr informationen', 'mehr infos', 'details', 'weiter',
        ],
        'it' => [
            'clicca qui', 'fai clic qui', 'clicca', 'qui', 'qua', 'link', 'questo link', 'questa pagina', 'altro', 'di più',
            'leggi tutto', 'leggi di più', 'scopri di più', 'maggiori informazioni', 'dettagli', 'continua',
            'continua a leggere',
        ],
        'pt' => [
            'clique aqui', 'carregue aqui', 'clique', 'aqui', 'link', 'este link', 'ligação', 'esta ligação', 'esta página',
            'mais', 'leia mais', 'ler mais', 'saiba mais', 'ver mais', 'mais informações', 'detalhes', 'continuar',
            'continue lendo', 'continuar a ler',
        ],
    ];

    private const string AROUND = '/^[\s\p{P}\p{S}]+|[\s\p{P}\p{S}]+$/u';

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(str_replace('’', "'", $text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return preg_replace(self::AROUND, '', $text) ?? '';
    }
}
