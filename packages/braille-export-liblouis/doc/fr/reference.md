<!-- phil-dev source-sha256=0a81c428e7349a771afd6081aaff102d38e2f1b860487c5c961bf77e284bb819 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `LiblouisTranslator` | Le `Translator` de `phil/braille-export` qui exécute `lou_translate` ; `MINIMUM_VERSION`, le plus ancien liblouis qu'il exécute par défaut. |
| `LiblouisTables` | La table liblouis de chaque langue et contraction. |
| `Exception\LiblouisException` | Chaque exception du traducteur, avec le code de sortie et la sortie d'erreur. |
| `Exception\ExecutableNotFound` | Le programme manque. |
| `Exception\TableNotFound` | Une table manque. |
| `Exception\OutdatedLiblouis` | Le programme annonce un liblouis antérieur à `LiblouisTranslator::MINIMUM_VERSION`. |
| `Exception\LiblouisFailed` | Tout autre échec. |
