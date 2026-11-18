<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * The data model of a version: every element the content may read or write, the collections — objectives,
 * interactions, comments — with the most elements each keeps, which elements answer `_children`, and the patterns of
 * the responses of the interactions of SCORM 2004. Written from SCORM 1.2's run-time environment (CMI data model 3.4)
 * and SCORM 2004 4th edition's run-time environment, its smallest permitted maximums kept as the bounds.
 *
 * The patterns are regular expressions PHP (with the `u` modifier) and JavaScript (with the `u` flag) read alike:
 * `describe()` hands them to the browser, which checks a value as the server will.
 */
final class DataModel
{
    public const string DECIMAL = '-?(?:\d{1,15}(?:\.\d{0,15})?|\.\d{1,15})';

    public const int MAX_ELEMENT_LENGTH = 255;

    private const string IDENTIFIER_12 = '^[!-~]+$';

    private const string LONG_IDENTIFIER = '^(?:(?![Uu][Rr][Nn]:)\S+|[Uu][Rr][Nn]:[A-Za-z0-9][A-Za-z0-9-]{0,31}:\S+)$';

    private const string SHORT_ID = '(?:(?!\[[,.:]\])\S)+';

    private const string LANGUAGE = '[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*';

    private const string LOCALIZED = '^(?:(?!\{lang=)[\s\S]*|\{lang='.self::LANGUAGE.'\}[\s\S]*)$';

    private const string TIME_12 = '^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,2})?$';

    private const string TIMESPAN_12 = '^\d{2,4}:[0-5]\d:[0-5]\d(?:\.\d{1,2})?$';

    private const string TIME_2004 = '^(?:19[7-9]\d|20[0-2]\d|203[0-8])(?:-(?:0[1-9]|1[0-2])(?:-(?:0[1-9]|[12]\d|3[01])(?:T(?:[01]\d|2[0-3])(?::[0-5]\d(?::[0-5]\d(?:\.\d{1,2})?)?)?(?:Z|[+-](?:[01]\d|2[0-3])(?::?[0-5]\d)?)?)?)?)?$';

    private const string INTERVAL_2004 = '^P(?=\d|T\d)(?:\d+Y)?(?:\d+M)?(?:\d+D)?(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d{1,2})?S)?)?$';

    private const string STATUS_12 = 'passed|completed|failed|incomplete|browsed';

    /** The collections, with the most elements each keeps. */
    private const array COLLECTIONS = [
        'Scorm12' => [
            'cmi.objectives' => 100,
            'cmi.interactions' => 250,
            'cmi.interactions.n.objectives' => 10,
            'cmi.interactions.n.correct_responses' => 10,
        ],
        'Scorm2004' => [
            'cmi.comments_from_learner' => 250,
            'cmi.comments_from_lms' => 100,
            'cmi.interactions' => 250,
            'cmi.interactions.n.objectives' => 10,
            'cmi.interactions.n.correct_responses' => 10,
            'cmi.objectives' => 100,
        ],
    ];

    /** The elements that answer `_children`. */
    private const array PARENTS = [
        'Scorm12' => ['cmi.core', 'cmi.core.score', 'cmi.objectives', 'cmi.objectives.n.score', 'cmi.student_data', 'cmi.student_preference', 'cmi.interactions'],
        'Scorm2004' => ['cmi.comments_from_learner', 'cmi.comments_from_lms', 'cmi.interactions', 'cmi.learner_preference', 'cmi.objectives', 'cmi.objectives.n.score', 'cmi.score'],
    ];

    /** The correct responses an interaction of SCORM 2004 keeps, by its type. */
    private const array CORRECT_RESPONSES = [
        'true-false' => 1, 'choice' => 10, 'fill-in' => 5, 'long-fill-in' => 5, 'likert' => 1,
        'matching' => 5, 'performance' => 5, 'sequencing' => 10, 'numeric' => 1, 'other' => 1,
    ];

    /**
     * The definitions of each version, built once per process: they are constants, the same for every request.
     *
     * @var array<string, array<string, ElementDefinition>>
     */
    private static array $definitions = [];

    private function __construct()
    {
    }

    /**
     * The definitions of a version, by name.
     *
     * @return array<string, ElementDefinition>
     */
    public static function definitions(ScormVersion $version): array
    {
        $key = $version->name;

        self::$definitions[$key] ??= self::index(ScormVersion::Scorm12 === $version ? self::scorm12() : self::scorm2004());

        return self::$definitions[$key];
    }

    public static function definition(ScormVersion $version, string $name): ?ElementDefinition
    {
        return self::definitions($version)[$name] ?? null;
    }

    /**
     * The collections of a version, each with the most elements it keeps.
     *
     * @return array<string, int>
     */
    public static function collections(ScormVersion $version): array
    {
        return self::COLLECTIONS[$version->name];
    }

    /**
     * What `_children` answers for an element: its children, by the order of the data model; null when the element
     * does not answer `_children`.
     */
    public static function children(ScormVersion $version, string $parent): ?string
    {
        if (!\in_array($parent, self::PARENTS[$version->name], true)) {
            return null;
        }

        $prefix = $parent.'.'.(isset(self::COLLECTIONS[$version->name][$parent]) ? 'n.' : '');
        $children = [];

        foreach (array_keys(self::definitions($version)) as $name) {
            if (str_starts_with($name, $prefix)) {
                $children[explode('.', substr($name, \strlen($prefix)))[0]] = true;
            }
        }

        return implode(',', array_keys($children));
    }

    /**
     * Whether a name is the parent of elements of the data model, such as `cmi.core` or `cmi.interactions.n`.
     */
    public static function isParent(ScormVersion $version, string $name): bool
    {
        return array_any(array_keys(self::definitions($version)), static fn (string $defined): bool => str_starts_with($defined, $name.'.'));
    }

    /**
     * How many correct responses an interaction of SCORM 2004 of this type keeps; that of the collection otherwise.
     */
    public static function correctResponses(ScormVersion $version, string $type): int
    {
        return ScormVersion::Scorm2004 === $version ? (self::CORRECT_RESPONSES[$type] ?? 1) : self::COLLECTIONS['Scorm12']['cmi.interactions.n.correct_responses'];
    }

    /**
     * The pattern of a correct response (`pattern`) or of a learner's response (`response`) of an interaction of
     * SCORM 2004, by its type; null for a type whose responses are only bounded in length.
     */
    public static function responsePattern(string $type, bool $correct): ?string
    {
        $id = self::SHORT_ID;
        $decimal = self::DECIMAL;

        return match ($type) {
            'true-false' => '^(?:true|false)$',
            'choice' => '^(?:'.$id.'(?:\[,\]'.$id.')*)?$',
            'sequencing' => '^'.$id.'(?:\[,\]'.$id.')*$',
            'likert' => '^'.$id.'$',
            'matching' => '^'.$id.'\[\.\]'.$id.'(?:\[,\]'.$id.'\[\.\]'.$id.')*$',
            'numeric' => $correct ? '^(?:'.$decimal.')?\[:\](?:'.$decimal.')?$' : '^'.$decimal.'$',
            default => null,
        };
    }

    /**
     * The data model as the browser reads it: the version, the elements, the collections and their bounds, the
     * elements that answer `_children`, the correct responses by type of interaction and their patterns, and the
     * messages of the error codes.
     *
     * @return array{version: string, dataModelVersion: string, elements: array<string, array<string, mixed>>, collections: array<string, int>, parents: list<string>, correctResponses: array<string, int>, responses: array<string, array{pattern: ?string, response: ?string}>, errors: array<int, string>}
     */
    public static function describe(ScormVersion $version): array
    {
        $responses = [];

        foreach (array_keys(self::CORRECT_RESPONSES) as $type) {
            $responses[$type] = ['pattern' => self::responsePattern($type, true), 'response' => self::responsePattern($type, false)];
        }

        return [
            'version' => $version->value,
            'dataModelVersion' => $version->dataModelVersion(),
            'elements' => array_map(static fn (ElementDefinition $definition): array => $definition->describe(), self::definitions($version)),
            'collections' => self::COLLECTIONS[$version->name],
            'parents' => self::PARENTS[$version->name],
            'correctResponses' => ScormVersion::Scorm2004 === $version ? self::CORRECT_RESPONSES : [],
            'responses' => ScormVersion::Scorm2004 === $version ? $responses : [],
            'errors' => ScormError::codes($version),
        ];
    }

    /**
     * @param list<ElementDefinition> $definitions
     *
     * @return array<string, ElementDefinition>
     */
    private static function index(array $definitions): array
    {
        $indexed = [];

        foreach ($definitions as $definition) {
            $indexed[$definition->name] = $definition;
        }

        return $indexed;
    }

    private static function vocabulary(string $words): string
    {
        return '^(?:'.$words.')$';
    }

    /**
     * @return list<ElementDefinition>
     */
    private static function scorm12(): array
    {
        $ro = Access::ReadOnly;
        $rw = Access::ReadWrite;
        $wo = Access::WriteOnly;
        $decimalOrBlank = '^(?:'.self::DECIMAL.')?$';
        $integer = '^-?\d{1,5}$';

        return [
            new ElementDefinition('cmi.core.student_id', $ro, self::IDENTIFIER_12, 255, default: ''),
            new ElementDefinition('cmi.core.student_name', $ro, null, 255, default: ''),
            new ElementDefinition('cmi.core.lesson_location', $rw, null, 255, default: ''),
            new ElementDefinition('cmi.core.credit', $ro, self::vocabulary('credit|no-credit'), default: 'credit'),
            new ElementDefinition('cmi.core.lesson_status', $rw, self::vocabulary(self::STATUS_12), default: 'not attempted'),
            new ElementDefinition('cmi.core.entry', $ro, self::vocabulary('ab-initio|resume|'), default: ''),
            new ElementDefinition('cmi.core.score.raw', $rw, $decimalOrBlank, 40, 0.0, 100.0, ''),
            new ElementDefinition('cmi.core.score.min', $rw, $decimalOrBlank, 40, 0.0, 100.0, ''),
            new ElementDefinition('cmi.core.score.max', $rw, $decimalOrBlank, 40, 0.0, 100.0, ''),
            new ElementDefinition('cmi.core.total_time', $ro, self::TIMESPAN_12, default: '0000:00:00.00'),
            new ElementDefinition('cmi.core.lesson_mode', $ro, self::vocabulary('normal|review|browse'), default: 'normal'),
            new ElementDefinition('cmi.core.exit', $wo, self::vocabulary('time-out|suspend|logout|')),
            new ElementDefinition('cmi.core.session_time', $wo, self::TIMESPAN_12),
            new ElementDefinition('cmi.suspend_data', $rw, null, 4096, default: ''),
            new ElementDefinition('cmi.launch_data', $ro, null, 4096, default: ''),
            new ElementDefinition('cmi.comments', $rw, null, 4096, default: ''),
            new ElementDefinition('cmi.comments_from_lms', $ro, null, 4096, default: ''),
            new ElementDefinition('cmi.objectives.n.id', $rw, self::IDENTIFIER_12, 255, default: ''),
            new ElementDefinition('cmi.objectives.n.score.raw', $rw, $decimalOrBlank, 40, 0.0, 100.0, ''),
            new ElementDefinition('cmi.objectives.n.score.min', $rw, $decimalOrBlank, 40, 0.0, 100.0, ''),
            new ElementDefinition('cmi.objectives.n.score.max', $rw, $decimalOrBlank, 40, 0.0, 100.0, ''),
            new ElementDefinition('cmi.objectives.n.status', $rw, self::vocabulary(self::STATUS_12.'|not attempted'), default: 'not attempted'),
            new ElementDefinition('cmi.student_data.mastery_score', $ro, $decimalOrBlank, default: ''),
            new ElementDefinition('cmi.student_data.max_time_allowed', $ro, '^(?:\d{2,4}:[0-5]\d:[0-5]\d(?:\.\d{1,2})?)?$', default: ''),
            new ElementDefinition('cmi.student_data.time_limit_action', $ro, self::vocabulary('exit,message|exit,no message|continue,message|continue,no message|'), default: ''),
            new ElementDefinition('cmi.student_preference.audio', $rw, $integer, min: -1.0, max: 100.0, default: '0'),
            new ElementDefinition('cmi.student_preference.language', $rw, null, 255, default: ''),
            new ElementDefinition('cmi.student_preference.speed', $rw, $integer, min: -100.0, max: 100.0, default: '0'),
            new ElementDefinition('cmi.student_preference.text', $rw, $integer, min: -1.0, max: 1.0, default: '0'),
            new ElementDefinition('cmi.interactions.n.id', $wo, self::IDENTIFIER_12, 255),
            new ElementDefinition('cmi.interactions.n.objectives.n.id', $wo, self::IDENTIFIER_12, 255),
            new ElementDefinition('cmi.interactions.n.time', $wo, self::TIME_12),
            new ElementDefinition('cmi.interactions.n.type', $wo, self::vocabulary('true-false|choice|fill-in|matching|performance|sequencing|likert|numeric')),
            new ElementDefinition('cmi.interactions.n.correct_responses.n.pattern', $wo, null, 255),
            new ElementDefinition('cmi.interactions.n.weighting', $wo, '^'.self::DECIMAL.'$', 40),
            new ElementDefinition('cmi.interactions.n.student_response', $wo, null, 255),
            new ElementDefinition('cmi.interactions.n.result', $wo, '^(?:correct|wrong|unanticipated|neutral|'.self::DECIMAL.')$', 40),
            new ElementDefinition('cmi.interactions.n.latency', $wo, self::TIMESPAN_12),
        ];
    }

    /**
     * @return list<ElementDefinition>
     */
    private static function scorm2004(): array
    {
        $ro = Access::ReadOnly;
        $rw = Access::ReadWrite;
        $wo = Access::WriteOnly;
        $real = '^'.self::DECIMAL.'$';
        $completion = self::vocabulary('completed|incomplete|not attempted|unknown');
        $success = self::vocabulary('passed|failed|unknown');

        return [
            new ElementDefinition('cmi.comments_from_learner.n.comment', $rw, self::LOCALIZED, 4000),
            new ElementDefinition('cmi.comments_from_learner.n.location', $rw, null, 250),
            new ElementDefinition('cmi.comments_from_learner.n.timestamp', $rw, self::TIME_2004),
            new ElementDefinition('cmi.comments_from_lms.n.comment', $ro, self::LOCALIZED, 4000),
            new ElementDefinition('cmi.comments_from_lms.n.location', $ro, null, 250),
            new ElementDefinition('cmi.comments_from_lms.n.timestamp', $ro, self::TIME_2004),
            new ElementDefinition('cmi.completion_status', $rw, $completion, default: 'unknown'),
            new ElementDefinition('cmi.completion_threshold', $ro, $real, 40, 0.0, 1.0),
            new ElementDefinition('cmi.credit', $ro, self::vocabulary('credit|no-credit'), default: 'credit'),
            new ElementDefinition('cmi.entry', $ro, self::vocabulary('ab-initio|resume|'), default: ''),
            new ElementDefinition('cmi.exit', $wo, self::vocabulary('time-out|suspend|logout|normal|')),
            new ElementDefinition('cmi.interactions.n.id', $rw, self::LONG_IDENTIFIER, 4000),
            new ElementDefinition('cmi.interactions.n.type', $rw, self::vocabulary('true-false|choice|fill-in|long-fill-in|likert|matching|performance|sequencing|numeric|other')),
            new ElementDefinition('cmi.interactions.n.objectives.n.id', $rw, self::LONG_IDENTIFIER, 4000),
            new ElementDefinition('cmi.interactions.n.timestamp', $rw, self::TIME_2004),
            new ElementDefinition('cmi.interactions.n.correct_responses.n.pattern', $rw, null, 4000, check: ValueCheck::InteractionPattern),
            new ElementDefinition('cmi.interactions.n.weighting', $rw, $real, 40),
            new ElementDefinition('cmi.interactions.n.learner_response', $rw, null, 4000, check: ValueCheck::InteractionResponse),
            new ElementDefinition('cmi.interactions.n.result', $rw, '^(?:correct|incorrect|unanticipated|neutral|'.self::DECIMAL.')$', 40),
            new ElementDefinition('cmi.interactions.n.latency', $rw, self::INTERVAL_2004, 64),
            new ElementDefinition('cmi.interactions.n.description', $rw, self::LOCALIZED, 250),
            new ElementDefinition('cmi.launch_data', $ro, null, 4000),
            new ElementDefinition('cmi.learner_id', $ro, self::LONG_IDENTIFIER, 4000, default: ''),
            new ElementDefinition('cmi.learner_name', $ro, self::LOCALIZED, 250, default: ''),
            new ElementDefinition('cmi.learner_preference.audio_level', $rw, $real, 40, 0.0, default: '1'),
            new ElementDefinition('cmi.learner_preference.language', $rw, '^(?:'.self::LANGUAGE.')?$', 250, default: ''),
            new ElementDefinition('cmi.learner_preference.delivery_speed', $rw, $real, 40, 0.0, default: '1'),
            new ElementDefinition('cmi.learner_preference.audio_captioning', $rw, self::vocabulary('-1|0|1'), default: '0'),
            new ElementDefinition('cmi.location', $rw, null, 1000),
            new ElementDefinition('cmi.max_time_allowed', $ro, self::INTERVAL_2004, 64),
            new ElementDefinition('cmi.mode', $ro, self::vocabulary('browse|normal|review'), default: 'normal'),
            new ElementDefinition('cmi.objectives.n.id', $rw, self::LONG_IDENTIFIER, 4000),
            new ElementDefinition('cmi.objectives.n.score.scaled', $rw, $real, 40, -1.0, 1.0),
            new ElementDefinition('cmi.objectives.n.score.raw', $rw, $real, 40),
            new ElementDefinition('cmi.objectives.n.score.min', $rw, $real, 40),
            new ElementDefinition('cmi.objectives.n.score.max', $rw, $real, 40),
            new ElementDefinition('cmi.objectives.n.success_status', $rw, $success, default: 'unknown'),
            new ElementDefinition('cmi.objectives.n.completion_status', $rw, $completion, default: 'unknown'),
            new ElementDefinition('cmi.objectives.n.progress_measure', $rw, $real, 40, 0.0, 1.0),
            new ElementDefinition('cmi.objectives.n.description', $rw, self::LOCALIZED, 250),
            new ElementDefinition('cmi.progress_measure', $rw, $real, 40, 0.0, 1.0),
            new ElementDefinition('cmi.scaled_passing_score', $ro, $real, 40, -1.0, 1.0),
            new ElementDefinition('cmi.score.scaled', $rw, $real, 40, -1.0, 1.0),
            new ElementDefinition('cmi.score.raw', $rw, $real, 40),
            new ElementDefinition('cmi.score.min', $rw, $real, 40),
            new ElementDefinition('cmi.score.max', $rw, $real, 40),
            new ElementDefinition('cmi.session_time', $wo, self::INTERVAL_2004, 64),
            new ElementDefinition('cmi.success_status', $rw, $success, default: 'unknown'),
            new ElementDefinition('cmi.suspend_data', $rw, null, 64000),
            new ElementDefinition('cmi.time_limit_action', $ro, self::vocabulary('exit,message|continue,message|exit,no message|continue,no message'), default: 'continue,no message'),
            new ElementDefinition('cmi.total_time', $ro, self::INTERVAL_2004, 64, default: 'PT0H0M0S'),
            new ElementDefinition('adl.nav.request', $rw, '^(?:continue|previous|exit|exitAll|abandon|abandonAll|suspendAll|_none_|\{target=[^\s{}]{1,4000}\}(?:choice|jump))$', 4100, default: '_none_'),
            new ElementDefinition('adl.nav.request_valid.continue', $ro, self::vocabulary('true|false|unknown'), default: 'unknown'),
            new ElementDefinition('adl.nav.request_valid.previous', $ro, self::vocabulary('true|false|unknown'), default: 'unknown'),
            new ElementDefinition('adl.nav.request_valid.choice', $ro, self::vocabulary('true|false|unknown'), default: 'unknown'),
            new ElementDefinition('adl.nav.request_valid.jump', $ro, self::vocabulary('true|false|unknown'), default: 'unknown'),
        ];
    }
}
