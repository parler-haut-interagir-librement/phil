<!-- phil-dev source-sha256=95445ed22fe64e0adc472a8340ad6c843b9b34848cb5ebcbcd15090bb1a5fe6a -->
# Le panneau du profileur

En mode débogage, le bundle enveloppe le stockage et le traitement des images dans `TraceableMediaStorage` et
`TraceableImageProcessor`, qui notent dans une `MediaTrace` ce qu'a fait une requête, et enregistre
`MediaDataCollector` avec son gabarit, `@PhilMedia/profiler/collector.html.twig`. Avec WebProfilerBundle, la barre
d'outils montre combien d'images la requête a rendues, et le panneau liste :

- chaque rendu : son format, son type de fichier, sa taille en octets et le temps qu'il a pris ;
- chaque opération de stockage : une lecture (trouvée ou absente), une écriture, une suppression, avec sa clé et sa
  taille.

Un rendu relu depuis le stockage est un succès de cache : la barre d'outils les compte, si bien qu'une page qui rend ses
formats à chaque requête se remarque. Hors du mode débogage, rien n'est enveloppé et rien n'est noté.
