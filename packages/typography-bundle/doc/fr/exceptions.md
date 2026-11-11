<!-- phil-dev source-sha256=6d069d564ba250243faf7c76d750400f7857bdade10e791cd1c430e67755f1b6 -->
# Exceptions

Le bundle n'a aucune exception propre.

| Quand | Ce qui est lancé |
|---|---|
| Le conteneur est construit avec, dans `locales`, un nom de correcteur que les préréglages ne connaissent pas | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`, qui nomme les valeurs permises |
| Un typographe est construit pour une chaîne qui n'est pas une locale : `default_locale`, une clé de `locales`, la locale donnée à un filtre | `Phil\Typography\Exception\InvalidLocale` |
| Une locale est configurée avec `hyphen` et la bibliothèque n'a pas de motifs pour sa langue | `Phil\Typography\Exception\UnsupportedLanguage` |

Chaque typographe est construit à sa première utilisation. Les deux dernières exceptions sont donc lancées là où il
est demandé la première fois : un service, un filtre, le rendu d'un document UJO. Corriger du texte ne lance jamais
d'exception.
