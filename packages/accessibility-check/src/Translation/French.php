<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

/**
 * The messages of the rules in French, by key.
 */
final class French
{
    public const string LOCALE = 'fr';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'image-alt.missing' => 'L’image {source} n’a pas d’équivalent textuel : décrivez-la dans un texte alternatif, ou donnez-lui alt="" si elle n’est qu’une décoration.',
        'image-alt.blank' => 'L’équivalent textuel de l’image {source} ne contient que des espaces : décrivez l’image, ou donnez-lui alt="" si elle n’est qu’une décoration.',
        'image-alt.filename' => 'L’équivalent textuel de l’image {source} est un nom de fichier, « {alt} » : décrivez ce que montre l’image.',
        'heading-order.skipped' => 'Ce titre h{level} suit un h{previous} : un niveau est sauté.',
        'heading-h1.multiple' => 'Ceci est le titre de premier niveau numéro {position} : une page n’en a qu’un.',
        'heading-h1.forbidden' => 'Ce contenu a un titre de premier niveau, ce qu’est déjà le titre de sa page : commencez ses titres à h2.',
        'heading-h1.missing' => 'La page n’a pas de titre de premier niveau.',
        'empty-heading.empty' => 'Ce titre h{level} n’a pas de texte.',
        'link-name.empty' => 'Le lien vers {href} n’a pas de texte : les lecteurs d’écran n’annoncent que son adresse.',
        'button-name.empty' => 'Ce bouton n’a pas de texte : dites ce qu’il fait.',
        'link-text.generic' => 'Le texte du lien vers {href}, « {text} », ne dit pas où il mène : décrivez sa destination.',
        'color-contrast.insufficient' => 'Le texte « {text} » a un contraste de {ratio}:1 entre {foreground} et {background} ; il lui faut {required}:1.',
        'color-contrast.unresolved' => 'La couleur {value} dépend des feuilles de style de la page : vérifiez le contraste de ce texte dans un navigateur.',
        'table-headers.missing' => 'Ce tableau n’a pas de cellule d’en-tête : marquez ses en-têtes avec th, ou activez la ligne d’en-tête du bloc.',
        'html-lang.missing' => 'La page ne dit pas sa langue : donnez un attribut lang à l’élément html.',
        'html-lang.invalid' => 'La langue de la page, « {lang} », n’est pas une étiquette de langue valide.',
        'lang-change.invalid' => '« {lang} » n’est pas une étiquette de langue valide.',
        'lang-change.empty' => 'Ce passage est marqué comme écrit dans une langue inconnue.',
        'lang-change.direction-only' => 'Ce passage change de sens d’écriture sans dire sa langue.',
        'frame-title.missing' => 'Le cadre {source} n’a pas de titre : dites ce qu’il contient.',
        'video-captions.missing' => 'La vidéo {source} n’a pas de piste de sous-titres.',
        'duplicate-id.duplicate' => 'L’identifiant « {id} » est utilisé {count} fois : un renvoi vers lui n’atteint que le premier.',
        'svg-name.missing' => 'Ce dessin n’a pas de titre : donnez-lui un élément title, ou aria-hidden="true" s’il n’est qu’une décoration.',
        'report.blocked' => 'Le contrôle d’accessibilité a trouvé {count} problème(s) à corriger avant de publier, à commencer par : {message}',
    ];
}
