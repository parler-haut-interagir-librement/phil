<!-- phil-dev source-sha256=034bda070732da608cb99a59ab809e7a7e7cbf58e3b9956a4d25a3ae138999c6 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare la bibliothèque et sur lequel elle est testée |
| `ext-json` | toute version | Les définitions de catalogue dans des fichiers JSON |
| `ext-mbstring` | toute version | Les libellés et les notes mesurés en caractères |

```sh
composer require phil/content-warnings
```

La bibliothèque ne déclare aucun service et ne lit aucune configuration : une application construit son catalogue une
fois, le conserve et le transmet là où les avertissements sont comparés. Dans une application Symfony,
`phil/content-warnings-bundle` s'en charge à partir de sa configuration.
