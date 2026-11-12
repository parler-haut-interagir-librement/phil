<!-- phil-dev source-sha256=d076878d307d065a3af2876ccd92a698a6dad96f5d7d6a48d053e9bf0a52786f -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\AccessibilityStatementException` : attrapez-la pour les
attraper toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `Exception\InvalidStatement` | `InvalidArgumentException` | Une déclaration ou l'une de ses parties ne peut pas être publiée, ou des données ne se lisent pas comme une déclaration. Sa `reason` est une clé courte : `version`, `too_many`, `criterion_outside_target`, `status`, `evaluation_date`, `entity`, `text_locales`, `text_locale`, `text_value`, `address`, `feedback_channel`, `feedback_email`, `feedback_phone`, `feedback_response_days`, `occurrences`, `evaluation_pages`, `audit_page`, `audit_pages`, `unknown_key`, `invalid_value`, `json`. |
| `Exception\InvalidReport` | `InvalidArgumentException` | Le JSON d'un rapport ne peut pas être relu : ce n'est pas du JSON, il est trop grand, trop profond ou n'a pas la forme d'un rapport. |
