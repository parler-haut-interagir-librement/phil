<?php

declare(strict_types=1);

use Phil\AccessibilityCheck\SuccessCriterion;

$criteria = [];

foreach (SuccessCriterion::cases() as $criterion) {
    $criteria['criterion.'.$criterion->value] = $criterion->title();
}

return $criteria + [
    'title' => 'Accessibility statement',
    'link' => 'Accessibility',
    'entity.default' => 'Our organisation',
    'scope.default' => 'this site',

    'wording.wcag.commitment' => '%entity% is committed to making %scope% accessible to everyone, whatever their abilities and the technologies they use.',
    'wording.wcag.basis' => 'This statement measures %scope% against the Web Content Accessibility Guidelines of the W3C: %target%.',
    'wording.en_301_549.commitment' => '%entity% is committed to making %scope% accessible, as the European accessibility requirements ask.',
    'wording.en_301_549.basis' => 'The requirements are those of the harmonised standard EN 301 549, which applies to web content the Web Content Accessibility Guidelines: this statement measures %scope% against %target%.',
    'wording.aca.commitment' => '%entity% is committed to making %scope% accessible and to identifying, removing and preventing barriers, as the Accessible Canada Act asks.',
    'wording.aca.basis' => 'This statement measures %scope% against the standard CAN/ASC-EN 301 549 and the Web Content Accessibility Guidelines it applies: %target%.',
    'wording.aca.feedback' => 'Feedback may be given anonymously; it is acknowledged in the way it was received, unless it was given anonymously.',

    'status.heading' => 'Conformance status',
    'status.full' => 'Measured against %target%, %scope% fully conforms: every success criterion is met.',
    'status.partial' => 'Measured against %target%, %scope% partially conforms: some success criteria are not met, as listed below.',
    'status.non-conformant' => 'Measured against %target%, %scope% does not conform: most success criteria are not met, as listed below.',
    'status.standard' => 'Read %standard%',

    'content.heading' => 'Content that is not accessible',
    'reason.non-compliance' => 'Success criteria not met',
    'reason.disproportionate-burden' => 'Disproportionate burden',
    'reason.out-of-scope' => 'Content outside the scope of this statement',
    'criterion.title' => '%number% %title% (level %level%)',
    'content.occurrences' => '{1}Found once by the last evaluation.|]1,Inf[Found %count% times by the last evaluation.',
    'content.alternative' => 'Meanwhile: %alternative%',
    'content.fix_by' => 'To be met by %date%.',

    'alternatives.heading' => 'Alternatives',

    'feedback.heading' => 'Feedback',
    'feedback.intro' => '%entity% welcomes your feedback: tell us of a barrier you met, or ask for content in another format.',
    'feedback.form' => 'Write to us',
    'feedback.page' => 'Give feedback',
    'feedback.email' => 'Email:',
    'feedback.phone' => 'Telephone:',
    'feedback.postal_address' => 'Postal address:',
    'feedback.response_days' => '{1}We answer within a day.|]1,Inf[We answer within %count% days.',

    'enforcement.heading' => 'Enforcement procedure',

    'preparation.heading' => 'Preparation of this statement',
    'preparation.published' => 'This statement, version %version%, was published on %date%.',
    'preparation.method.self-assessment' => 'The content was last evaluated on %date%, by our own assessment.',
    'preparation.method.third-party' => 'The content was last evaluated on %date%, by an independent party.',
    'preparation.method.automated' => 'The content was last evaluated on %date%, by an automated check.',
    'preparation.pages' => '{1}One page was evaluated.|]1,Inf[%count% pages were evaluated.',
    'preparation.report' => 'Read the evaluation report',

    'versions.heading' => 'Versions of this statement',
    'versions.version' => 'Version %version%, %date%',
    'versions.in_force' => 'in force',
    'versions.number' => 'Version',
    'versions.published' => 'Published on',

    'admin.title' => 'Accessibility statement: administration',
    'admin.published' => 'Version %version% is published.',
    'admin.none' => 'No version is published yet.',
    'admin.next' => 'Next version',
    'admin.read_only' => 'The statement comes from the configuration: write the next version there, as below.',
    'admin.refused' => 'The version is not published',
    'admin.document' => 'The statement, in JSON',
    'admin.help' => 'The draft is the version the latest audit gives, or the version in force with the next number and today’s date. A version is never changed once published.',
    'admin.publish' => 'Publish this version',
];
