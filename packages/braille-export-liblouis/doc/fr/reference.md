<!-- phil-dev source-sha256=c2483e18dfe66ccd67e451d972a0ffb4695c0ed7b483227e3e28f8e0fb948328 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `LiblouisTranslator` | Le `Translator` de `phil/braille-export` qui exécute `lou_translate`. |
| `LiblouisTables` | La table liblouis de chaque langue et contraction. |
| `Exception\LiblouisException` | Chaque exception du traducteur, avec le code de sortie et la sortie d'erreur. |
| `Exception\ExecutableNotFound` | Le programme manque. |
| `Exception\TableNotFound` | Une table manque. |
| `Exception\LiblouisFailed` | Tout autre échec. |
