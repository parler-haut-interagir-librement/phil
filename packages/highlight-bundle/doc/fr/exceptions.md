<!-- phil-dev source-sha256=5fab726ebe873073bd6f14e79b17f4f44f67d029bf23b8261d1fabfe88715a48 -->
# Exceptions

Le bundle n'a aucune exception qui lui soit propre.

| Quand | Ce qui est lancé |
|---|---|
| Le conteneur est construit avec un alias, ou un nom, qui n'est pas écrit en minuscules avec des lettres, des chiffres et `_+#.-`, jusqu'à 32 caractères | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` |
| Le coloriseur est construit la première fois avec un alias vers un nom qu'aucun langage ne porte | `Phil\Highlight\Exception\UnknownLanguage`, qui liste les langages |
| Le coloriseur est construit la première fois avec un langage étiqueté dont le nom ou un alias ne peut pas être demandé | `Phil\Highlight\Exception\InvalidLanguage` |

Le coloriseur est construit à sa première utilisation : les deux dernières exceptions sont donc lancées là où il est
demandé la première fois, que ce soit un service, le filtre ou le rendu d'un document UJO. Colorer du code ne lance
jamais d'exception : un langage que le coloriseur ne connaît pas donne le code échappé.
