<!-- phil-dev source-sha256=4a1c3c7fdaf0cb2bd8d194462d5988c2d6c329b634d585545c343bda6145586e -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Domain\ValueObject\SynonymSet` | valeur | Une règle, d'équivalence ou à sens unique ; `normalize()`, la façon de comparer une expression ; `MAX_PHRASES`, `MAX_LENGTH`, `MAX_WORDS` |
| `Domain\ValueObject\SynonymList` | valeur | Les règles d'une langue ; `language()`, la sous-étiquette primaire d'une étiquette de langue ; `MAX_SETS` |
| `Domain\ValueObject\Violation` | valeur | Ce que le validateur a relevé ; `OVERLAP`, `CHAIN` |
| `Domain\Exception\InvalidSynonyms` | exception | Une règle, une liste, un fichier ou un document refusé ; `fileLine` |
| `Application\SynonymValidator` | service | Ce qu'une liste contient de probablement erroné |
| `Application\Contract\SynonymStore` | interface | Où les listes sont gardées |
| `Infrastructure\Format\SolrSynonymsReader`, `SolrSynonymsWriter` | services | Le format de synonymes de Solr ; `MAX_BYTES`, `MAX_LINE` |
| `Infrastructure\Format\JsonSynonyms` | service | Le document JSON ; `MAX_BYTES` |
| `Infrastructure\Store\FileSynonymStore` | magasin | Un fichier par langue ; `EXTENSION` |
| `Infrastructure\Store\InMemorySynonymStore` | magasin | Des listes en mémoire |
