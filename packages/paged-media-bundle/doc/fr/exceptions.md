<!-- phil-dev source-sha256=86b3b4d8af0351330ee402d1486309a5a4fe0fe629e5a13642bdff9758617b43 -->
# Exceptions

Les exceptions du bundle implémentent `Phil\PagedMedia\Exception\PagedMediaException`, comme celles de la
bibliothèque.

| Exception | Lancée quand |
|---|---|
| `NotConfigured` (`LogicException`) | Gotenberg sans `gotenberg.url`, une signature sans `signing.certificate`, un horodatage sans `signing.timestamp_url`, du HTML sans `PrintEngine` |
| `UnknownTemplate` (`InvalidArgumentException`) | Un nom de gabarit sous lequel aucun service n'est enregistré ; le message énumère les noms connus |
| `StorageFailed` (`RuntimeException`) | Un chemin qui sort du stockage, un répertoire qui ne peut pas être créé, un fichier ou une tâche qui ne peut pas être écrit, une tâche qui ne peut pas être retirée, un pool de cache qui échoue, avec son exception comme précédente |
| `InvalidTemplateParameters` (`InvalidArgumentException`) | Le paramètre d'un gabarit d'imprimé manquant ou d'un genre incorrect ; le message nomme le gabarit, le chemin de la valeur et ce qui est attendu |
| `JobUnavailable` (`RuntimeException`) | Aucune tâche gardée sous un identifiant, une tâche gardée que `JobCodec` n'a pas écrite, une tâche d'une autre sorte que son message |

Les exceptions de la bibliothèque traversent `PagedMedia` telles quelles : `InvalidStage`, `InvalidPipeline` et
`InvalidRequest` avant l'envoi d'une requête, les `GotenbergFailure` après, `CertificateUnavailable` venant d'un
coffre. Dans un *worker*, `PagedMediaHandler` les enveloppe pour Messenger ([Messenger](messenger.md)).

Une configuration que Symfony refuse — un certificat sans ce qu'exige son type, un délai inférieur à zéro — lance
`InvalidConfigurationException` à la construction du conteneur.
