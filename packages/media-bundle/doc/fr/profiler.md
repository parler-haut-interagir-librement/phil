<!-- phil-dev source-sha256=97ee9e4696e9fe1ebc8f1e6ee0e3aea60b5d05035295f043a45d60cf3091ee63 -->
# Le panneau du profileur

En mode débogage, le bundle enveloppe le stockage et le traitement des images dans `TraceableMediaStorage` et
`TraceableImageProcessor`, qui notent dans une `MediaTrace` ce qu'a fait une requête, et enregistre
`MediaDataCollector` avec son gabarit, `@PhilMedia/profiler/collector.html.twig`. Avec WebProfilerBundle, la barre
d'outils montre combien d'images la requête a rendues, et le panneau liste :

- chaque rendu : son format, son type de fichier, sa taille en octets et le temps qu'il a pris ;
- chaque opération de stockage : une lecture ou un flux (trouvé ou absent), une écriture, une suppression, avec sa clé
  et sa taille ; la taille d'un flux est celle qu'il déclare, son contenu restant non lu.

Un rendu relu depuis le stockage est un succès de cache : la barre d'outils les compte, si bien qu'une page qui rend ses
formats à chaque requête se remarque. Hors du mode débogage, rien n'est enveloppé et rien n'est noté.
