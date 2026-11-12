<!-- phil-dev source-sha256=6d82158e90bc97d89e53304c275636aa8805a081712e0d61c00431420432a425 -->
# Référence

| Type | Rôle |
|---|---|
| `Statement` | Une version d'une déclaration : cible, état, rétroaction, non-conformités, solutions de rechange, portée, recours, évaluation, organisation ; `blocking()`, `withReason()`, `lastEvaluatedAt()`, `toArray()`. |
| `StatementReader` | Lit une déclaration à partir de données simples ou de JSON, chaque clé vérifiée. |
| `StatementGenerator` | Écrit la version suivante à partir de la version actuelle et d'un audit. |
| `ConformanceTarget` | Une version de WCAG et un niveau ; `covers()`, `criteria()`, `describe()`. |
| `Standard` | `Wcag20`, `Wcag21`, `Wcag22` ; `title()`, `url()`, `includes()`. |
| `ConformanceStatus` | `Full`, `Partial`, `NonConformant` ; `derive()`. |
| `NonConformity` | Un critère de succès non respecté, avec sa raison, sa description, sa solution de rechange, sa date de correction, ses occurrences et son origine ; `failedCriteria()`. |
| `NonConformityReason` | `NonCompliance`, `DisproportionateBurden`, `OutOfScope` ; `countsAgainstConformance()`. |
| `FeedbackContact` | Courriel, téléphone, page, adresse postale, délai de réponse en jours ; `phoneUri()`. |
| `Enforcement` | La procédure, l'autorité et sa page. |
| `Evaluation` | Le moment, la méthode, les pages et le rapport de la dernière évaluation. |
| `EvaluationMethod` | `SelfAssessment`, `ThirdParty`, `Automated`. |
| `LocalizedText` | Un texte brut par étiquette de langue ; `of()`, `in()`, `has()`, `locales()`. |
| `Address` | La vérification des adresses vers lesquelles pointe une déclaration. |
| `CriterionTitles` | `french()` : les titres des critères de succès dans la traduction autorisée de WCAG 2.2 en français du W3C, par numéro. |
| `Audit\AuditResult` | Un audit : moment, pages, méthode, rapport. |
| `Audit\AuditedPage` | Une page d'un audit et son `Report`. |
| `Audit\ReportReader` | Relit le JSON d'un rapport de `phil/accessibility-check`. |
| `Contract\AuditSource` | Le port qui donne le dernier audit. |
| `Exception\AccessibilityStatementException` | Ce qu'implémente chaque exception de la bibliothèque. |
| `Exception\InvalidStatement` | Une déclaration, une partie ou des données qui ne peuvent être publiées ou lues. |
| `Exception\InvalidReport` | Un rapport qui ne peut pas être relu. |
