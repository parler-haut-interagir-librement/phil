# Exceptions

Every exception of the library implements `Exception\AccessibilityStatementException`: catch it to catch them all.

| Exception | Extends | Thrown when |
|---|---|---|
| `Exception\InvalidStatement` | `InvalidArgumentException` | A statement or one of its parts cannot be published, or data does not read as a statement. Its `reason` is a short key: `version`, `too_many`, `criterion_outside_target`, `status`, `evaluation_date`, `entity`, `text_locales`, `text_locale`, `text_value`, `address`, `feedback_channel`, `feedback_email`, `feedback_phone`, `feedback_response_days`, `occurrences`, `evaluation_pages`, `audit_page`, `audit_pages`, `unknown_key`, `invalid_value`, `json`. |
| `Exception\InvalidReport` | `InvalidArgumentException` | The JSON of a report cannot be read back: not JSON, too large, too deep, or not shaped as a report. |
