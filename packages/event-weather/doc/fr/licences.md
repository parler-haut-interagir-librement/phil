<!-- phil-dev source-sha256=937126803d3ffaa1bcf62127c3fe3a78cd248ec9d3f010bee7a349a211dd0fdd -->
# Licences et mentions de source

Les deux fournisseurs publient des données ouvertes sous des licences qui permettent l'usage commercial et exigent une
mention de source. Chaque `Forecast` porte l'`Attribution` de son fournisseur, et chaque `EventForecast` les mentions
de chaque fournisseur dont il contient des données : affichez-les à côté des données, avec un lien vers la licence.

## Environnement et Changement climatique Canada

| | |
|---|---|
| Données | Prévisions des pages de villes et alertes météo publiques, de l'API OGC MSC GeoMet (`api.weather.gc.ca`) |
| Licence | Environment and Climate Change Canada Data Servers End-use Licence — <https://eccc-msc.github.io/open-data/licence/readme_en/> |
| Usage | Mondial, libre de redevances, perpétuel et non exclusif, usage commercial compris |
| Mention de source | « Data Source: Environment and Climate Change Canada » ou, quand plusieurs mentions sont peu pratiques, « Contains information licenced under the Data Server End-use Licence of Environment and Climate Change Canada » |
| Conditions | Aucun usage qui suggère un statut officiel ou un appui ; veilles et avertissements reproduits sans en altérer le contenu ni l'intention ; données fournies telles quelles |
| Politique d'utilisation | <https://eccc-msc.github.io/open-data/usage-policy/readme_en/> : un `User-Agent` significatif, seulement les données utilisées, environ une requête par seconde au plus sans parler d'abord au SMC |

`Attribution::environmentCanada()` donne la ligne de crédit `Data Source: Environment and Climate Change Canada`, la
licence et son adresse, et `https://weather.gc.ca/` comme source.

La bibliothèque garde les textes d'une alerte tels qu'émis et ne les raccourcit jamais ; une page qui affiche une
alerte suit la règle ci-dessous.

## Afficher les alertes

**Une alerte n'est jamais résumée ni traduite automatiquement : son texte est affiché tel qu'émis, dans la langue que
donne la source.**

La licence d'Environnement et Changement climatique Canada demande que les veilles et les avertissements soient
reproduits sans en altérer le contenu ni l'intention (voir ses conditions,
<https://eccc-msc.github.io/open-data/licence/readme_en/>). Un résumé laisse tomber ce que le prévisionniste a choisi
de dire ; une traduction automatique peut changer le sens d'un avertissement, son ton ou sa consigne, justement ce sur
quoi les gens agissent. Un site qui affiche une `WeatherAlert` donc :

- affiche son `event`, son `headline`, sa `description` et son `instruction` tels quels, sans les raccourcir, les
  reformuler ni les réécrire par un modèle de langage, avec leur `severity`, `effective` et `expires` ;
- affiche l'alerte dans la langue où sa source l'a émise, son `language`. Environnement Canada émet chaque alerte en
  anglais et en français : demandez à `CapReader` ou à la source d'alertes la langue de la page, et affichez le texte de
  l'autre langue, tel qu'émis, quand la page n'est dans aucune des deux ;
- peut mettre en page, styler et ordonner les alertes, afficher sa propre étiquette à côté d'une alerte — un badge de sa
  sévérité, « Alerte météo » dans la langue de la page — et pointer vers la page `web` de l'alerte ou vers
  <https://weather.gc.ca/>, tant que l'étiquette reste séparée du texte de l'alerte et ne le remplace pas ;
- ne suggère jamais que l'alerte, ou la page, est approuvée par Environnement et Changement climatique Canada, et
  affiche la mention de source à côté d'elle.

La même règle vaut pour toute source d'alertes qu'une application ajoute : un avertissement officiel est cité, jamais
paraphrasé.

## MET Norway

| | |
|---|---|
| Données | Locationforecast 2.0 (`api.met.no`) |
| Licence | Norwegian Licence for Open Government Data 2.0 (<https://data.norge.no/nlod/en/2.0/>) et Creative Commons Attribution 4.0 International (<https://creativecommons.org/licenses/by/4.0/>) — <https://api.met.no/doc/License> |
| Mention de source | Créditer MET Norway comme source, par exemple « Data from MET Norway », avec un lien vers la source si possible |
| Conditions | Aucune suggestion d'appui ; le nom « Yr » et son logo ne sont pas utilisés ; les icônes météo sont une œuvre distincte sous licence MIT |
| Conditions d'utilisation | <https://api.met.no/doc/TermsOfService> : un `User-Agent` d'identification avec un contact, jamais inventé ; aucune nouvelle requête avant l'`Expires` d'une réponse, `If-Modified-Since` après ; moins de 20 requêtes par seconde ; coordonnées coupées à quatre décimales ; HTTPS seulement |

`Attribution::metNorway()` donne la ligne de crédit `Data from MET Norway`, les licences, l'adresse de CC BY 4.0 et
`https://api.met.no/` comme source.

Les codes de symboles de Locationforecast sont lus dans une `Condition` et gardés dans le `summary` de la période ; la
bibliothèque ne fournit aucune icône. Une application qui affiche les icônes météo de MET Norway suit leur propre
licence.

## Ce que fait la bibliothèque pour les conditions

| Condition | Comment |
|---|---|
| Identification | Le `Contact` que donne l'application est le `User-Agent` de chaque requête |
| `Expires` et `If-Modified-Since` | Par le `CachingHttpClient` dans lequel l'application enveloppe son client — voir [Installation](installation.md) |
| Quatre décimales | `MetNorway\LocationforecastProvider` coupe les coordonnées avant de demander |
| Seulement les données utilisées | Une requête par prévision, autour de l'événement ; les alertes du lieu de l'événement seulement |
| HTTPS | Les deux points de terminaison sont des adresses `https` |
