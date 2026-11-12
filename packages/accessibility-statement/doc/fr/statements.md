<!-- phil-dev source-sha256=ce63efee4330e4cc847e1eeb4ad1c8c230a54f74a245d6a8b07a1969fee9d707 -->
# Déclarations

Un `Statement` est une version d'une déclaration d'accessibilité. Il ne change jamais une fois publié : la version
suivante est un nouveau `Statement`, numéroté un de plus.

```php
use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\ConformanceTarget;
use Phil\AccessibilityStatement\Enforcement;
use Phil\AccessibilityStatement\Evaluation;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\FeedbackContact;
use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Standard;
use Phil\AccessibilityStatement\Statement;

$statement = new Statement(
    version: 1,
    publishedAt: new DateTimeImmutable('2026-11-08'),
    target: new ConformanceTarget(Standard::Wcag22, ConformanceLevel::AA),
    status: ConformanceStatus::Partial,
    feedback: new FeedbackContact(email: 'accessibility@example.com', responseDays: 10),
    nonConformities: [
        new NonConformity(
            SuccessCriterion::CaptionsPrerecorded,
            NonConformityReason::NonCompliance,
            description: new LocalizedText(['en' => 'Videos have no captions yet.', 'fr' => 'Les vidéos n’ont pas encore de sous-titres.']),
            alternative: LocalizedText::of('A transcript is sent on request.'),
            fixBy: new DateTimeImmutable('2027-03-31'),
        ),
    ],
    alternatives: [LocalizedText::of('Call us: we read any page to you.')],
    scope: LocalizedText::of('the site example.com'),
    enforcement: new Enforcement(LocalizedText::of('If our answer does not satisfy you, you may complain to the authority.'), url: 'https://example.org/complaints'),
    evaluation: new Evaluation(new DateTimeImmutable('2026-11-01'), EvaluationMethod::SelfAssessment, pages: 24),
    entity: 'Example Org',
);
```

## Les parties

| Partie | Type | Ce qu'elle dit |
|---|---|---|
| `version` | `int` | Le numéro de la version, à partir de 1. |
| `publishedAt` | `DateTimeImmutable` | Le moment où la version a été publiée. |
| `target` | `ConformanceTarget` | La version de WCAG et le niveau revendiqués : `WCAG 2.2 AA` par défaut. |
| `status` | `ConformanceStatus` | `Full`, `Partial` ou `NonConformant`. |
| `feedback` | `FeedbackContact` | Comment un lecteur signale un obstacle : un courriel, un téléphone, une page, une adresse postale — au moins un —, et le nombre de jours dans lequel une réponse arrive (de 1 à 365). |
| `nonConformities` | `list<NonConformity>` | Les critères de succès non respectés, au plus 1 000. |
| `alternatives` | `list<LocalizedText>` | Les moyens d'accéder au contenu qui ne dépendent pas de son accessibilité, au plus 50. |
| `scope` | `?LocalizedText` | Ce que couvre la déclaration : un site, une application, une partie de l'un ou de l'autre. |
| `enforcement` | `?Enforcement` | Ce qu'un lecteur peut faire si la réponse ne le satisfait pas : la procédure, l'autorité, sa page. |
| `evaluation` | `?Evaluation` | La dernière évaluation : quand, comment (`SelfAssessment`, `ThirdParty`, `Automated`), combien de pages, son rapport. |
| `entity` | `?string` | L'organisation qui fait la déclaration, en 1 à 200 caractères. |

## La cible de conformité

`ConformanceTarget` associe un `Standard` — `Wcag20`, `Wcag21` ou `Wcag22` — à un `ConformanceLevel` de
`phil/accessibility-check`. `covers()` indique si un critère de succès est mesuré : il appartient à cette version de
WCAG et son niveau est celui de la cible ou un niveau inférieur. `criteria()` les énumère dans l'ordre de leurs
numéros : 55 pour WCAG 2.2 AA, 49 pour WCAG 2.1 AA, 37 pour WCAG 2.0 AA. `describe()` écrit `WCAG 2.2 AA`, et
`Standard::url()` donne la recommandation du W3C.

## Non-conformités

Une `NonConformity` nomme un `SuccessCriterion` et une `NonConformityReason` :

| Raison | Signification | Compte contre la conformité |
|---|---|---|
| `NonCompliance` | Le contenu ne respecte pas encore le critère. | oui |
| `DisproportionateBurden` | Le respecter imposerait une charge disproportionnée par rapport à son bénéfice, évaluée et déclarée. | oui |
| `OutOfScope` | Le contenu concerné se trouve hors de la portée de la déclaration, par exemple des archives ou du contenu de tiers. | non |

Elle peut ajouter une `description` de ce qui échoue et où — sinon, le titre du critère en tient lieu —, la solution de
rechange (`alternative`) offerte entre-temps, la date à laquelle le critère sera respecté (`fixBy`), le nombre de fois
qu'un audit l'a rencontrée (`occurrences`) et si le dernier audit l'a trouvée (`fromAudit`).

`Statement::blocking()` énumère celles qui comptent contre la conformité, `withReason()` celles d'une raison, et
`NonConformity::failedCriteria()` les critères qu'elles font échouer, une fois chacun, dans l'ordre de leurs numéros.

## Ce que refuse une déclaration

Le constructeur refuse, avec une `InvalidStatement` dont la `reason` dit pourquoi :

- une version inférieure à 1 (`version`), trop de non-conformités ou de solutions de rechange (`too_many`) ;
- une non-conformité dont la cible ne mesure pas le critère (`criterion_outside_target`) ;
- un état que les non-conformités contredisent (`status`) : `Full` alors que l'une d'elles compte contre la conformité,
  `Partial` ou `NonConformant` alors qu'aucune ne compte ;
- une évaluation postérieure à la publication (`evaluation_date`) ;
- un nom d'organisation vide ou trop long (`entity`).

Ses parties refusent de la même façon leurs propres mauvaises valeurs : un texte dans aucune langue ou sous une
étiquette qui n'en est pas une, un contact pour la rétroaction sans aucun moyen de joindre, un courriel que refuse le
filtre de PHP, un téléphone qui n'en est pas un, une adresse qui n'est ni `https://` ni un chemin du site.

## L'état que justifient les non-conformités

`ConformanceStatus::derive($target, $nonConformities)` donne `Full` lorsqu'aucune ne compte contre la conformité,
`NonConformant` lorsque plus de la moitié des critères de la cible échouent, et `Partial` sinon. Le générateur s'en
sert ; une application qui écrit une déclaration à la main peut revendiquer `Partial` ou `NonConformant` dès qu'un
critère échoue.

## Textes et adresses

`LocalizedText` contient un texte brut par étiquette de langue, jusqu'à 32 langues et 10 000 caractères chacune.
`in($locale, $fallback)` retourne le texte de la locale, sinon celui de sa langue principale (`fr` pour `fr-CA`), sinon
celui de la langue de repli, sinon le premier. Les textes ne sont jamais du HTML : une page les échappe.

Une adresse — de la page de rétroaction, du rapport, de l'autorité de recours — est vérifiée par `Address::check()` :
une adresse `https://` ou, sauf pour l'autorité, un chemin du site qui commence par un seul `/`. `javascript:`, `http:`,
`//host` et les caractères de contrôle sont refusés.

## Les titres des critères en français

`CriterionTitles::french()` donne le titre de chaque critère de succès de WCAG 2.2, par numéro, tel que le publie la
traduction autorisée en français du W3C, à partir de `data/wcag-2.2-success-criteria.fr.json`. Les titres sont copiés,
non rédigés : `data/NOTICE.md` nomme la traduction, son droit d'auteur et la licence de document du W3C sous laquelle
elle est utilisée, dont l'avis accompagne chaque copie.

```php
use Phil\AccessibilityStatement\CriterionTitles;

CriterionTitles::french()['1.4.3'];     // 'Contraste (minimum)'
```
