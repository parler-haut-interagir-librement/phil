<!-- phil-dev source-sha256=2f4e544dced95cc560cac524c13761f17989e09ad4786619c1956d6811e24dcc -->
# Installation

```sh
composer require phil/transcription
```

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou une version plus récente, avec `ext-sodium` | Classes en lecture seule ; la frontière aléatoire d'un corps multipart, écrite sans fuite par le temps d'exécution |
| `phil/timed-text` | Une transcription devient une `Track`, écrite dans tous les formats de la bibliothèque |
| `symfony/http-client-contracts` | Les transcripteurs envoient leurs requêtes par n'importe quel `HttpClientInterface` |

Un client HTTP implémente les contrats : `symfony/http-client`, ou un client de l'application.

## Un serveur whisper.cpp

whisper.cpp (MIT) exécute les modèles Whisper sur le processeur ou le GPU d'une machine du site, et répond en HTTP :

```sh
./build/bin/whisper-server --model models/ggml-large-v3-turbo.bin --host 0.0.0.0 --port 8080
```

`WhisperCppTranscriber` envoie sa requête à son point de terminaison `/inference`. L'adresse est à l'opérateur de la
fixer — un nom interne comme `http://whisper:8080`, ou une adresse du réseau privé — ; ce n'est jamais une adresse que
donne un visiteur.

## Services infonuagiques

`OpenAiTranscriber` et `DeepgramTranscriber` prennent la clé du compte, que l'application garde comme secret. Ils sont
refusés pour un enregistrement sans le consentement des personnes qu'on y entend.
