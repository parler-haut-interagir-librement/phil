<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * The success criteria of WCAG 2.2, by number: what a rule tests, with its title, its conformance level and the page
 * of the W3C that explains it. 4.1.1 Parsing, obsolete in WCAG 2.2, is not one of them.
 */
enum SuccessCriterion: string
{
    case NonTextContent = '1.1.1';
    case AudioOnlyAndVideoOnlyPrerecorded = '1.2.1';
    case CaptionsPrerecorded = '1.2.2';
    case AudioDescriptionOrMediaAlternativePrerecorded = '1.2.3';
    case CaptionsLive = '1.2.4';
    case AudioDescriptionPrerecorded = '1.2.5';
    case SignLanguagePrerecorded = '1.2.6';
    case ExtendedAudioDescriptionPrerecorded = '1.2.7';
    case MediaAlternativePrerecorded = '1.2.8';
    case AudioOnlyLive = '1.2.9';
    case InfoAndRelationships = '1.3.1';
    case MeaningfulSequence = '1.3.2';
    case SensoryCharacteristics = '1.3.3';
    case Orientation = '1.3.4';
    case IdentifyInputPurpose = '1.3.5';
    case IdentifyPurpose = '1.3.6';
    case UseOfColor = '1.4.1';
    case AudioControl = '1.4.2';
    case ContrastMinimum = '1.4.3';
    case ResizeText = '1.4.4';
    case ImagesOfText = '1.4.5';
    case ContrastEnhanced = '1.4.6';
    case LowOrNoBackgroundAudio = '1.4.7';
    case VisualPresentation = '1.4.8';
    case ImagesOfTextNoException = '1.4.9';
    case Reflow = '1.4.10';
    case NonTextContrast = '1.4.11';
    case TextSpacing = '1.4.12';
    case ContentOnHoverOrFocus = '1.4.13';
    case Keyboard = '2.1.1';
    case NoKeyboardTrap = '2.1.2';
    case KeyboardNoException = '2.1.3';
    case CharacterKeyShortcuts = '2.1.4';
    case TimingAdjustable = '2.2.1';
    case PauseStopHide = '2.2.2';
    case NoTiming = '2.2.3';
    case Interruptions = '2.2.4';
    case ReAuthenticating = '2.2.5';
    case Timeouts = '2.2.6';
    case ThreeFlashesOrBelowThreshold = '2.3.1';
    case ThreeFlashes = '2.3.2';
    case AnimationFromInteractions = '2.3.3';
    case BypassBlocks = '2.4.1';
    case PageTitled = '2.4.2';
    case FocusOrder = '2.4.3';
    case LinkPurposeInContext = '2.4.4';
    case MultipleWays = '2.4.5';
    case HeadingsAndLabels = '2.4.6';
    case FocusVisible = '2.4.7';
    case Location = '2.4.8';
    case LinkPurposeLinkOnly = '2.4.9';
    case SectionHeadings = '2.4.10';
    case FocusNotObscuredMinimum = '2.4.11';
    case FocusNotObscuredEnhanced = '2.4.12';
    case FocusAppearance = '2.4.13';
    case PointerGestures = '2.5.1';
    case PointerCancellation = '2.5.2';
    case LabelInName = '2.5.3';
    case MotionActuation = '2.5.4';
    case TargetSizeEnhanced = '2.5.5';
    case ConcurrentInputMechanisms = '2.5.6';
    case DraggingMovements = '2.5.7';
    case TargetSizeMinimum = '2.5.8';
    case LanguageOfPage = '3.1.1';
    case LanguageOfParts = '3.1.2';
    case UnusualWords = '3.1.3';
    case Abbreviations = '3.1.4';
    case ReadingLevel = '3.1.5';
    case Pronunciation = '3.1.6';
    case OnFocus = '3.2.1';
    case OnInput = '3.2.2';
    case ConsistentNavigation = '3.2.3';
    case ConsistentIdentification = '3.2.4';
    case ChangeOnRequest = '3.2.5';
    case ConsistentHelp = '3.2.6';
    case ErrorIdentification = '3.3.1';
    case LabelsOrInstructions = '3.3.2';
    case ErrorSuggestion = '3.3.3';
    case ErrorPreventionLegalFinancialData = '3.3.4';
    case Help = '3.3.5';
    case ErrorPreventionAll = '3.3.6';
    case RedundantEntry = '3.3.7';
    case AccessibleAuthenticationMinimum = '3.3.8';
    case AccessibleAuthenticationEnhanced = '3.3.9';
    case NameRoleValue = '4.1.2';
    case StatusMessages = '4.1.3';

    /**
     * The title and the conformance level of each criterion, by number.
     *
     * @var array<string, array{string, ConformanceLevel}>
     */
    private const array CRITERIA = [
        '1.1.1' => ['Non-text Content', ConformanceLevel::A],
        '1.2.1' => ['Audio-only and Video-only (Prerecorded)', ConformanceLevel::A],
        '1.2.2' => ['Captions (Prerecorded)', ConformanceLevel::A],
        '1.2.3' => ['Audio Description or Media Alternative (Prerecorded)', ConformanceLevel::A],
        '1.2.4' => ['Captions (Live)', ConformanceLevel::AA],
        '1.2.5' => ['Audio Description (Prerecorded)', ConformanceLevel::AA],
        '1.2.6' => ['Sign Language (Prerecorded)', ConformanceLevel::AAA],
        '1.2.7' => ['Extended Audio Description (Prerecorded)', ConformanceLevel::AAA],
        '1.2.8' => ['Media Alternative (Prerecorded)', ConformanceLevel::AAA],
        '1.2.9' => ['Audio-only (Live)', ConformanceLevel::AAA],
        '1.3.1' => ['Info and Relationships', ConformanceLevel::A],
        '1.3.2' => ['Meaningful Sequence', ConformanceLevel::A],
        '1.3.3' => ['Sensory Characteristics', ConformanceLevel::A],
        '1.3.4' => ['Orientation', ConformanceLevel::AA],
        '1.3.5' => ['Identify Input Purpose', ConformanceLevel::AA],
        '1.3.6' => ['Identify Purpose', ConformanceLevel::AAA],
        '1.4.1' => ['Use of Color', ConformanceLevel::A],
        '1.4.2' => ['Audio Control', ConformanceLevel::A],
        '1.4.3' => ['Contrast (Minimum)', ConformanceLevel::AA],
        '1.4.4' => ['Resize Text', ConformanceLevel::AA],
        '1.4.5' => ['Images of Text', ConformanceLevel::AA],
        '1.4.6' => ['Contrast (Enhanced)', ConformanceLevel::AAA],
        '1.4.7' => ['Low or No Background Audio', ConformanceLevel::AAA],
        '1.4.8' => ['Visual Presentation', ConformanceLevel::AAA],
        '1.4.9' => ['Images of Text (No Exception)', ConformanceLevel::AAA],
        '1.4.10' => ['Reflow', ConformanceLevel::AA],
        '1.4.11' => ['Non-text Contrast', ConformanceLevel::AA],
        '1.4.12' => ['Text Spacing', ConformanceLevel::AA],
        '1.4.13' => ['Content on Hover or Focus', ConformanceLevel::AA],
        '2.1.1' => ['Keyboard', ConformanceLevel::A],
        '2.1.2' => ['No Keyboard Trap', ConformanceLevel::A],
        '2.1.3' => ['Keyboard (No Exception)', ConformanceLevel::AAA],
        '2.1.4' => ['Character Key Shortcuts', ConformanceLevel::A],
        '2.2.1' => ['Timing Adjustable', ConformanceLevel::A],
        '2.2.2' => ['Pause, Stop, Hide', ConformanceLevel::A],
        '2.2.3' => ['No Timing', ConformanceLevel::AAA],
        '2.2.4' => ['Interruptions', ConformanceLevel::AAA],
        '2.2.5' => ['Re-authenticating', ConformanceLevel::AAA],
        '2.2.6' => ['Timeouts', ConformanceLevel::AAA],
        '2.3.1' => ['Three Flashes or Below Threshold', ConformanceLevel::A],
        '2.3.2' => ['Three Flashes', ConformanceLevel::AAA],
        '2.3.3' => ['Animation from Interactions', ConformanceLevel::AAA],
        '2.4.1' => ['Bypass Blocks', ConformanceLevel::A],
        '2.4.2' => ['Page Titled', ConformanceLevel::A],
        '2.4.3' => ['Focus Order', ConformanceLevel::A],
        '2.4.4' => ['Link Purpose (In Context)', ConformanceLevel::A],
        '2.4.5' => ['Multiple Ways', ConformanceLevel::AA],
        '2.4.6' => ['Headings and Labels', ConformanceLevel::AA],
        '2.4.7' => ['Focus Visible', ConformanceLevel::AA],
        '2.4.8' => ['Location', ConformanceLevel::AAA],
        '2.4.9' => ['Link Purpose (Link Only)', ConformanceLevel::AAA],
        '2.4.10' => ['Section Headings', ConformanceLevel::AAA],
        '2.4.11' => ['Focus Not Obscured (Minimum)', ConformanceLevel::AA],
        '2.4.12' => ['Focus Not Obscured (Enhanced)', ConformanceLevel::AAA],
        '2.4.13' => ['Focus Appearance', ConformanceLevel::AAA],
        '2.5.1' => ['Pointer Gestures', ConformanceLevel::A],
        '2.5.2' => ['Pointer Cancellation', ConformanceLevel::A],
        '2.5.3' => ['Label in Name', ConformanceLevel::A],
        '2.5.4' => ['Motion Actuation', ConformanceLevel::A],
        '2.5.5' => ['Target Size (Enhanced)', ConformanceLevel::AAA],
        '2.5.6' => ['Concurrent Input Mechanisms', ConformanceLevel::AAA],
        '2.5.7' => ['Dragging Movements', ConformanceLevel::AA],
        '2.5.8' => ['Target Size (Minimum)', ConformanceLevel::AA],
        '3.1.1' => ['Language of Page', ConformanceLevel::A],
        '3.1.2' => ['Language of Parts', ConformanceLevel::AA],
        '3.1.3' => ['Unusual Words', ConformanceLevel::AAA],
        '3.1.4' => ['Abbreviations', ConformanceLevel::AAA],
        '3.1.5' => ['Reading Level', ConformanceLevel::AAA],
        '3.1.6' => ['Pronunciation', ConformanceLevel::AAA],
        '3.2.1' => ['On Focus', ConformanceLevel::A],
        '3.2.2' => ['On Input', ConformanceLevel::A],
        '3.2.3' => ['Consistent Navigation', ConformanceLevel::AA],
        '3.2.4' => ['Consistent Identification', ConformanceLevel::AA],
        '3.2.5' => ['Change on Request', ConformanceLevel::AAA],
        '3.2.6' => ['Consistent Help', ConformanceLevel::A],
        '3.3.1' => ['Error Identification', ConformanceLevel::A],
        '3.3.2' => ['Labels or Instructions', ConformanceLevel::A],
        '3.3.3' => ['Error Suggestion', ConformanceLevel::AA],
        '3.3.4' => ['Error Prevention (Legal, Financial, Data)', ConformanceLevel::AA],
        '3.3.5' => ['Help', ConformanceLevel::AAA],
        '3.3.6' => ['Error Prevention (All)', ConformanceLevel::AAA],
        '3.3.7' => ['Redundant Entry', ConformanceLevel::A],
        '3.3.8' => ['Accessible Authentication (Minimum)', ConformanceLevel::AA],
        '3.3.9' => ['Accessible Authentication (Enhanced)', ConformanceLevel::AAA],
        '4.1.2' => ['Name, Role, Value', ConformanceLevel::A],
        '4.1.3' => ['Status Messages', ConformanceLevel::AA],
    ];

    /**
     * The title the W3C gives the criterion, such as `Contrast (Minimum)`.
     */
    public function title(): string
    {
        return self::CRITERIA[$this->value][0];
    }

    public function level(): ConformanceLevel
    {
        return self::CRITERIA[$this->value][1];
    }

    /**
     * The page of the W3C that explains the criterion: its intent, its benefits and the techniques that meet it.
     */
    public function understandingUrl(): string
    {
        $slug = strtolower(str_replace([' ', '(', ')', ','], ['-', '', '', ''], $this->title()));

        return 'https://www.w3.org/WAI/WCAG22/Understanding/'.$slug.'.html';
    }
}
