<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Translation;

/**
 * The messages of the check in French, by key.
 */
final class French
{
    public const string LOCALE = 'fr';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'http-status.unsuccessful' => 'La page à {path} répond par le code {status} : les moteurs de recherche écartent une page qui répond par une erreur.',
        'http-status.redirect' => 'La page à {path} répond par le code {status}, une redirection : son adresse canonique devrait répondre elle-même.',
        'page-size.truncated' => 'La page fait {bytes} octets, plus que ce que lit le contrôle : la fin de la page n’a pas été contrôlée.',
        'document-title.missing' => 'La page n’a pas d’élément title : les pages de résultats affichent son adresse à la place.',
        'document-title.multiple' => 'La page a {count} éléments title : les moteurs de recherche ne lisent que le premier.',
        'document-title.empty' => 'Le titre de la page est vide : les pages de résultats affichent son adresse à la place.',
        'document-title.short' => 'Le titre de la page a {length} caractères : sous {min}, il en dit trop peu pour distinguer la page des autres.',
        'document-title.long' => 'Le titre de la page a {length} caractères : les pages de résultats le coupent au-delà de {max}.',
        'meta-description.missing' => 'La page n’a pas de meta description : les pages de résultats rédigent leur propre résumé à partir de son texte.',
        'meta-description.multiple' => 'La page a {count} meta descriptions : les moteurs de recherche ne lisent que la première.',
        'meta-description.empty' => 'La meta description de la page est vide : les pages de résultats rédigent leur propre résumé à partir de son texte.',
        'meta-description.short' => 'La meta description a {length} caractères : sous {min}, elle dit peu de la page.',
        'meta-description.long' => 'La meta description a {length} caractères : les pages de résultats la coupent au-delà de {max}.',
        'document-language.missing' => 'L’élément html n’a pas d’attribut lang : donnez-lui la langue de la page, {locale}.',
        'document-language.region' => 'La page est publiée en {locale}, mais son attribut lang indique {lang}.',
        'document-language.mismatch' => 'La page est publiée en {locale}, mais son attribut lang indique {lang} : les moteurs de recherche la montrent aux lecteurs d’une autre langue.',
        'document-language.header' => 'L’en-tête Content-Language indique {header}, mais la page est publiée en {locale}.',
        'single-h1.missing' => 'La page n’a pas de titre de premier niveau : dites de quoi parle la page dans un h1.',
        'single-h1.multiple' => 'La page a {count} titres de premier niveau : gardez-en un et faites des autres des h2.',
        'single-h1.empty' => 'Un titre de premier niveau de la page n’a pas de texte.',
        'canonical.missing' => 'La page ne nomme pas d’adresse canonique : ajoutez un lien canonique vers {expected}.',
        'canonical.variant-missing' => 'La page à {path} ne nomme pas d’adresse canonique : ajoutez un lien canonique vers {expected}, comme le fait la page à sa propre adresse.',
        'canonical.relative' => 'Le lien canonique « {href} » est relatif : écrivez-le en adresse absolue.',
        'canonical.invalid' => 'Le lien canonique « {href} » n’est pas une adresse que les moteurs de recherche peuvent suivre.',
        'canonical.conflicting' => 'La page à {path} nomme {count} adresses canoniques différentes : les moteurs de recherche les ignorent toutes.',
        'canonical.mismatch' => 'Le lien canonique nomme {found}, mais la page a été publiée à {expected}.',
        'canonical.variant-mismatch' => 'La page à {path} nomme {found} comme canonique, mais c’est la page publiée à {expected} : les deux chemins devraient la nommer.',
        'indexing.not-blocked' => 'La page à {path} n’est pas indexable, mais rien n’empêche les moteurs de recherche de l’indexer : ajoutez une consigne noindex.',
        'indexing.not-blocked-in-channel' => 'La page à {path} n’est pas indexable dans le canal {channel}, mais rien n’empêche les moteurs de recherche de l’y indexer : ajoutez une consigne noindex.',
        'indexing.blocked' => 'La page à {path} est publiée comme indexable, mais une consigne {directive} ({source}) la tient hors de l’index.',
        'indexing.blocked-in-channel' => 'La page à {path} est indexable dans le canal {channel}, mais une consigne {directive} ({source}) la tient hors de l’index.',
        'indexing.blocked-for-agent' => 'La page à {path} est publiée comme indexable, mais une consigne {directive} ({source}) la tient hors de l’index de {agent}.',
        'hreflang.invalid-code' => '« {code} » n’est pas une langue pour hreflang : écrivez une étiquette de langue BCP 47, ou x-default.',
        'hreflang.relative' => 'La traduction {code} est liée par une adresse relative, « {href} » : écrivez-la en adresse absolue.',
        'hreflang.invalid-url' => 'La traduction {code} est liée à « {href} », qui n’est pas une adresse que les moteurs de recherche peuvent suivre.',
        'hreflang.duplicate' => 'La langue {code} mène à plusieurs adresses : listez une traduction par langue.',
        'hreflang.missing-self' => 'La page liste ses traductions, mais pas elle-même : ajoutez un lien alternate vers {canonical} sous sa propre langue.',
        'hreflang.self-language' => 'La page se liste sous {codes}, mais elle est publiée en {locale}.',
        'hreflang.not-reciprocal' => 'La traduction {code}, {href}, ne liste pas cette page en retour : les moteurs de recherche ignorent une traduction qui n’est pas réciproque.',
        'crawlable-links.no-href' => 'Le lien « {text} » n’a pas de href : les moteurs de recherche ne peuvent pas le suivre.',
        'crawlable-links.javascript' => 'Le lien « {text} » exécute un script, {href} : les moteurs de recherche ne peuvent pas le suivre.',
        'link-text.generic' => 'Le texte du lien vers {href}, « {text} », ne dit pas où il mène : décrivez sa destination.',
        'image-alt.missing' => 'L’image {src} n’a pas d’attribut alt : décrivez-la, ou donnez-lui alt="" si elle n’est qu’une décoration.',
        'broken-links.broken' => 'Le lien vers {href} répond par le code {status} : corrigez-le ou retirez-le.',
        'broken-links.server-error' => 'Le lien vers {href} a répondu par le code {status}, une erreur de son serveur : vérifiez-le plus tard.',
        'broken-links.redirect' => 'Le lien vers {href} redirige, avec le code {status} : liez directement là où il mène.',
        'structured-data.unreadable' => 'Les données structurées de la page ne peuvent pas être lues : {reason}',
        'structured-data.none' => 'La page n’a pas de données structurées : les moteurs de recherche n’en montrent aucun résultat enrichi.',
        'structured-data.issue' => 'Données structurées : {message}',
        'share-tags.issue' => 'Balises de partage : {message}',
        'check.rule-failed' => 'La règle {rule} n’a pas pu contrôler la page ({error}) : ses points manquent à ce rapport.',
        'report.title' => 'Contrôle de référencement',
        'report.clean' => 'Aucun point à corriger.',
        'report.count' => '{count} point(s) à corriger.',
        'severity.error' => 'Erreur',
        'severity.warning' => 'Avertissement',
        'severity.info' => 'Information',
    ];

    private function __construct()
    {
    }
}
