<!-- phil-dev source-sha256=bcd377c5adf1a4c1f957a3a5cc381eb7d61c86744438d7418586a0002a73a0c8 -->
# Vie privée et accessibilité

## Le consentement des personnes entendues

Un enregistrement contient des voix de personnes, et ce qu'elles disent : des renseignements personnels. L'envoyer à un
service d'une autre entreprise, c'est l'envoyer hors du site, ce que la Loi 25 du Québec permet avec leur consentement et
une évaluation du service. La bibliothèque fait du consentement une partie de la requête :

- `RecordingConsent::LocalOnly`, la valeur par défaut : seul un transcripteur dont le traitement est `Local` peut le
  transcrire ;
- `RecordingConsent::Cloud` : les personnes entendues ont accepté qu'il quitte le site.

Un transcripteur infonuagique refuse une requête sans ce consentement avant d'ouvrir le fichier, et `Transcription` la
refuse avant qu'aucun transcripteur ne la voie. Consigner le consentement — qui a accepté, quand, pour quel service —
revient à l'application.

## Ce qui quitte le site

| Transcripteur | Ce qui part |
|---|---|
| `WhisperCppTranscriber` | Rien : le serveur s'exécute sur les machines du site |
| `OpenAiTranscriber` | L'enregistrement, la suggestion, la langue, vers OpenAI |
| `DeepgramTranscriber` | L'enregistrement, la suggestion, la langue, vers Deepgram |

La réponse d'un service qui échoue n'est jamais citée dans une exception, de sorte qu'un journal ne garde aucune
transcription par accident. Les clés sont `#[SensitiveParameter]`.

## Une relecture avant la publication

Une machine entend mal les noms, les accents et les termes. Une transcription n'est publiée qu'une fois relue par une
personne, afin qu'un sous-titre ou une transcription ne mette pas des mots dans la bouche d'un locuteur.

## Accessibilité

Les sous-titres servent ceux qui n'entendent pas le son (WCAG 1.2.2) ; une transcription sert ceux qui ne le voient ni
ne l'entendent (WCAG 1.2.1 et 1.2.8). La langue déclarée — `fr-CA` plutôt qu'une supposition — est le `lang` de la
transcription qu'une page montre, afin qu'un lecteur d'écran la lise avec la bonne voix.
