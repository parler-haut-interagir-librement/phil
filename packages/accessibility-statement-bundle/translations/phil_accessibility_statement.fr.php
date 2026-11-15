<?php

declare(strict_types=1);

use Phil\AccessibilityStatement\CriterionTitles;

// The titles of the success criteria are those of the W3C Authorized Translation of WCAG 2.2, read from
// phil/accessibility-statement, whose data/NOTICE.md names their source and licence.
return [
    'title' => 'Déclaration d’accessibilité',
    'link' => 'Accessibilité',
    'entity.default' => 'Notre organisation',
    'scope.default' => 'ce site',

    'wording.wcag.commitment' => '%entity% s’engage à rendre %scope% accessible à toutes et à tous, quelles que soient leurs capacités et les technologies qu’ils utilisent.',
    'wording.wcag.basis' => 'Cette déclaration mesure %scope% aux Règles pour l’accessibilité des contenus Web du W3C : %target%.',
    'wording.en_301_549.commitment' => '%entity% s’engage à rendre %scope% accessible, comme le demandent les exigences européennes d’accessibilité.',
    'wording.en_301_549.basis' => 'Ces exigences sont celles de la norme harmonisée EN 301 549, qui applique aux contenus Web les Règles pour l’accessibilité des contenus Web : cette déclaration mesure %scope% à %target%.',
    'wording.aca.commitment' => '%entity% s’engage à rendre %scope% accessible et à reconnaître, éliminer et prévenir les obstacles, comme le demande la Loi canadienne sur l’accessibilité.',
    'wording.aca.basis' => 'Cette déclaration mesure %scope% à la norme CAN/ASC-EN 301 549 et aux Règles pour l’accessibilité des contenus Web qu’elle applique : %target%.',
    'wording.aca.feedback' => 'La rétroaction peut être donnée de façon anonyme ; nous en accusons réception de la façon dont elle nous est parvenue, sauf si elle est anonyme.',

    'status.heading' => 'État de conformité',
    'status.full' => 'Au regard des %target%, %scope% est entièrement conforme : tous les critères de succès sont respectés.',
    'status.partial' => 'Au regard des %target%, %scope% est partiellement conforme : certains critères de succès ne sont pas respectés, comme l’indique la liste ci-dessous.',
    'status.non-conformant' => 'Au regard des %target%, %scope% n’est pas conforme : la plupart des critères de succès ne sont pas respectés, comme l’indique la liste ci-dessous.',
    'status.standard' => 'Lire les %standard% (en anglais)',

    'content.heading' => 'Contenus non accessibles',
    'reason.non-compliance' => 'Critères de succès non respectés',
    'reason.disproportionate-burden' => 'Charge disproportionnée',
    'reason.out-of-scope' => 'Contenus hors du champ de cette déclaration',
    'criterion.title' => '%number% %title% (niveau %level%)',
    'content.occurrences' => '{1}Relevé une fois lors de la dernière évaluation.|]1,Inf[Relevé %count% fois lors de la dernière évaluation.',
    'content.alternative' => 'En attendant : %alternative%',
    'content.fix_by' => 'À respecter d’ici le %date%.',

    'alternatives.heading' => 'Solutions de rechange',

    'feedback.heading' => 'Rétroaction',
    'feedback.intro' => '%entity% accueille votre rétroaction : signalez-nous un obstacle rencontré, ou demandez un contenu dans un autre format.',
    'feedback.form' => 'Nous écrire',
    'feedback.page' => 'Donner une rétroaction',
    'feedback.email' => 'Courriel :',
    'feedback.phone' => 'Téléphone :',
    'feedback.postal_address' => 'Adresse postale :',
    'feedback.response_days' => '{1}Nous répondons en un jour.|]1,Inf[Nous répondons dans un délai de %count% jours.',

    'enforcement.heading' => 'Voies de recours',

    'preparation.heading' => 'Établissement de cette déclaration',
    'preparation.published' => 'Cette déclaration, version %version%, a été publiée le %date%.',
    'preparation.method.self-assessment' => 'Le contenu a été évalué pour la dernière fois le %date%, par notre propre évaluation.',
    'preparation.method.third-party' => 'Le contenu a été évalué pour la dernière fois le %date%, par une partie indépendante.',
    'preparation.method.automated' => 'Le contenu a été évalué pour la dernière fois le %date%, par une vérification automatisée.',
    'preparation.pages' => '{1}Une page a été évaluée.|]1,Inf[%count% pages ont été évaluées.',
    'preparation.report' => 'Lire le rapport d’évaluation',

    'versions.heading' => 'Versions de cette déclaration',
    'versions.version' => 'Version %version%, %date%',
    'versions.in_force' => 'en vigueur',
    'versions.number' => 'Version',
    'versions.published' => 'Publiée le',

    'admin.title' => 'Déclaration d’accessibilité : administration',
    'admin.published' => 'La version %version% est publiée.',
    'admin.none' => 'Aucune version n’est encore publiée.',
    'admin.next' => 'Prochaine version',
    'admin.read_only' => 'La déclaration vient de la configuration : écrivez-y la prochaine version, comme ci-dessous.',
    'admin.refused' => 'La version n’est pas publiée',
    'admin.document' => 'La déclaration, en JSON',
    'admin.help' => 'Le brouillon est la version que donne le dernier audit, ou la version en vigueur avec le numéro suivant et la date du jour. Une version publiée ne change jamais.',
    'admin.publish' => 'Publier cette version',
] + array_combine(
    array_map(static fn (string $number): string => 'criterion.'.$number, array_keys(CriterionTitles::french())),
    CriterionTitles::french(),
);
