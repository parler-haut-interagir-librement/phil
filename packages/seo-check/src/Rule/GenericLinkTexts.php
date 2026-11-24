<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

/**
 * The link texts that say nothing of where a link leads, by primary language, and how a text is compared with them: in
 * lower case, its spaces collapsed, the punctuation and symbols around it left out, the typographic apostrophe read as
 * `'`.
 */
final class GenericLinkTexts
{
    /** @var array<string, list<string>> */
    public const array DEFAULTS = [
        'en' => [
            'click here', 'click', 'here', 'go', 'link', 'this', 'this link', 'this page', 'more', 'read more',
            'learn more', 'see more', 'more info', 'more information', 'find out more', 'details', 'continue',
            'start', 'right here', 'tap here',
        ],
        'fr' => [
            'cliquez ici', 'cliquer ici', 'ici', 'cliquez', 'lien', 'ce lien', 'cette page', 'plus', 'en savoir plus',
            'en lire plus', 'lire la suite', 'la suite', 'suite', 'voir plus', 'voir', "plus d'info", "plus d'infos",
            "plus d'informations", 'détails', 'continuer', 'commencer', 'appuyez ici',
        ],
        'es' => [
            'haga clic aquí', 'haz clic aquí', 'pulse aquí', 'aquí', 'enlace', 'este enlace', 'esta página', 'más',
            'leer más', 'saber más', 'ver más', 'más información', 'detalles', 'continuar',
        ],
        'de' => [
            'hier klicken', 'klicken sie hier', 'hier', 'link', 'dieser link', 'diese seite', 'mehr', 'weiterlesen',
            'mehr erfahren', 'mehr lesen', 'mehr informationen', 'details', 'weiter',
        ],
        'it' => [
            'clicca qui', 'fai clic qui', 'qui', 'link', 'questo link', 'questa pagina', 'altro', 'leggi di più',
            'scopri di più', 'maggiori informazioni', 'dettagli', 'continua',
        ],
        'pt' => [
            'clique aqui', 'aqui', 'link', 'este link', 'esta página', 'mais', 'leia mais', 'saiba mais', 'ver mais',
            'mais informações', 'detalhes', 'continuar',
        ],
    ];

    private function __construct()
    {
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(str_replace('’', "'", $text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return preg_replace('/^[\s\p{P}\p{S}]+|[\s\p{P}\p{S}]+$/u', '', $text) ?? '';
    }
}
