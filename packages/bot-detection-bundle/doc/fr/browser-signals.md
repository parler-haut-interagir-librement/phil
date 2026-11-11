<!-- phil-dev source-sha256=85a2969ba7f4dffafd72cde33dbc433793e2a6d45d7d90e5a88450be5c096e96 -->
# Signaux du navigateur

Le code du navigateur est un contrôleur Stimulus, `verdict` (`assets/src/verdict_controller.js`), et ses détecteurs
(`assets/src/detectors.js`), écrits de zéro. Il ne laisse sortir du navigateur qu'un mot : `human` ou `robot`.

| Pièce | État |
|---|---|
| Les détecteurs du navigateur | Implémentés, testés avec Node ; aucun code tiers inclus |
| Le format transmis (`human` ou `robot`) | Décidé, et lu par `Verdict::fromSubmitted()` sur le serveur |
| Le côté serveur (verdict, friction, compteur) | Implémenté et testé |

## Placer le contrôleur

Avec StimulusBundle, le contrôleur est trouvé grâce au `assets/package.json` du bundle. Sur l'élément racine, il laisse
le verdict dans `data-phil-bot-verdict`, où le script de collecte de `phil/analytics-bundle` le lit. Sur un champ caché,
il écrit aussi le verdict dans le champ, pour que le formulaire le soumette :

```twig
<html data-controller="phil--bot-detection-bundle--verdict">
…
<input type="hidden" name="verdict" data-controller="phil--bot-detection-bundle--verdict">
```

## Des signaux, pas une empreinte

Le code du navigateur évalue des **signaux d'automatisation indépendants**, chacun répondant à une question (« ce
navigateur est-il piloté par un automate ? »), et les combine en un seul verdict **sur place**. Une empreinte répond à
une autre question (« quel est ce navigateur ? ») et exige une combinaison stable de nombreux attributs. Rien ici n'est
conçu pour y répondre, et rien de ce qui y répondrait n'est calculé, haché ou envoyé.

## Les détecteurs

Chaque détecteur répond `true` (automatisation), `false`, ou `undefined` quand il ne peut pas trancher ; un seul `true`
rend le verdict `robot`. Les détecteurs écrits sont :

| Détecteur | Lit | Automatisation quand |
|---|---|---|
| `webdriver` | `navigator.webdriver` | il vaut `true` |
| `automationTraces` | les propriétés de la fenêtre et du document | une variable globale de Selenium, PhantomJS, Nightmare ou WebDriver est présente, ou une propriété `cdc_` de ChromeDriver |
| `nodeRuntime` | `window.process.versions.node` | la page s'exécute dans Node.js |
| `headlessChrome` | l'agent utilisateur et les marques des indications d'agent utilisateur | l'un ou l'autre nomme `HeadlessChrome` |
| `noLanguages` | `navigator.languages` | c'est une liste vide |
| `zeroSizeWindow` | `window.outerWidth`, `window.outerHeight` | les deux valent 0 |
| `platformMismatch` | `navigator.userAgentData.platform` comparé à `navigator.platform` | Windows, macOS et Linux ne concordent pas |
| `automationStack` | la pile d'une `Error` | un cadre de Puppeteer, PhantomJS, Selenium, Playwright ou WebDriver |
| `softwareRenderer` | le moteur de rendu WebGL, quand le navigateur l'indique | SwiftShader, llvmpipe, softpipe ou un rastériseur logiciel |

Les signaux de la conception qui ne sont pas encore écrits — fonctions natives réécrites, fenêtre comparée à un
*worker*, permissions, voix de synthèse, WebRTC, cohérence du matériel et de l'écran — figurent ci-dessous dans la
conception complète.

| Signal | Ce qu'il détecte |
|---|---|
| `navigator.webdriver` | Un navigateur sous le contrôle de WebDriver |
| Variables globales propres aux moteurs d'automatisation (Selenium, PhantomJS, Nightmare) | Les propriétés que ces moteurs injectent dans la page |
| Traces d'automatisation dans les piles d'appels | Des cadres que seuls les cadriciels d'automatisation produisent |
| Un objet `process` exposé | Une page qui s'exécute dans un environnement basé sur Node.js |
| Incohérences entre l'agent utilisateur et la plateforme | Un agent utilisateur qui prétend ce que la plateforme contredit (`platform` comparé à `userAgentData`) |
| Incohérences des langues, des modules d'extension, des types MIME et des permissions | Des valeurs qu'un vrai navigateur ne combinerait pas |
| Fonctions natives réécrites ou mandatées | Des API natives modifiées pour cacher l'automatisation |
| Comparaison de la fenêtre avec un *worker* | Des valeurs falsifiées dans la fenêtre, mais pas dans un *worker* |
| Indices de Chrome sans interface | Des traits propres au mode sans interface |
| Un moteur de rendu WebGL logiciel, le fournisseur et le moteur de rendu WebGL | Un rendu sans GPU, typique des serveurs sans interface |
| Une fenêtre de taille nulle | Une fenêtre que personne ne regarde |
| Signaux de cohérence secondaires : permissions, voix de synthèse vocale, WebRTC, matériel (`hardwareConcurrency`), écran | Des entrées pour les vérifications de cohérence seulement |

**Chaque signal est lu localement puis abandonné.** Seul le verdict qui en découle quitte le navigateur, avec la requête
qu'il tranche.

## Ce qui n'est délibérément jamais collecté ni envoyé

- **Aucun identifiant de visiteur et aucune empreinte** : tout calcul d'empreinte et d'identifiant de visiteur trouvé
  dans les références de conception est laissé de côté.
- **Aucun signal brut et aucun détail de détecteur** : le serveur reçoit `human` ou `robot`, rien d'autre. Une charge
  utile plus longue est lue comme `Unknown` ([verdict.md](verdict.md)).
- **Aucun appel à un tiers** : aucun des appels de « surveillance » ou d'échantillonnage que font les références de leur
  propre initiative (vers un point de terminaison d'un fournisseur, ou par le chargement d'un script distant) n'est
  reproduit. Le script est servi par l'application, depuis sa propre origine.
- **Aucun stockage** : aucun témoin, aucun stockage local, aucun état persistant dans le navigateur.

## Un navigateur qui résiste à la prise d'empreinte n'est pas un robot

Les navigateurs axés sur la vie privée rendent aléatoires ou vident certains des attributs mêmes listés plus haut. **Un
détecteur ne doit pas traiter des signaux aléatoires ou vidés comme de l'automatisation.** C'est une exigence posée à
chaque détecteur, pas à la politique du serveur : quand un verdict atteint le serveur, il est déjà robot ou humain.

## Références de conception

Les détecteurs sont **écrits de zéro**. Les projets ci-dessous sont lus détecteur par détecteur pour comprendre ce que
mesure un signal, et seul ce qui sert un verdict robot ou humain inspire un détecteur. **Aucune ligne de leur code n'est
copiée.** Une source qui n'est pas libre n'est pas lue du tout : réécrire du code sous une licence qui interdit la
redistribution produirait une œuvre dérivée. Les licences sont celles des fichiers lus le 2026-10-30.

| Référence | Licence lue | Version lue | Ce qui inspire un détecteur | Laissé de côté |
|---|---|---|---|---|
| BotD (`fingerprintjs/BotD`) | MIT | v2.0.0 | **La référence principale** : un verdict `{ bot, botKind }` sans identifiant, et des détecteurs indépendants avec leurs tests (webdriver, variables globales d'automatisation, piles d'appels, incohérences de langues, de modules d'extension, de types MIME et de permissions, `process` exposé, moteur de rendu WebGL logiciel, fenêtre de taille nulle) | `monitor()`, qui appelle un point de terminaison du fournisseur ; distinguer les bons robots des mauvais, ce qui se fait côté serveur avec une liste de robots |
| CreepJS (`abrahamjuliot/creepjs`) | MIT ; son nom est une marque de commerce, il n'est donc jamais utilisé comme nom ou marque de ce bundle (une mention factuelle de l'origine est permise) | Aucune version publiée ; dernier commit lu le 2026-06-11 | Ce que BotD couvre mal : fonctions natives réécrites ou mandatées, comparaison de la fenêtre et d'un *worker*, indices de Chrome sans interface | La partie de prise d'empreinte et le module `prediction` |
| ThumbmarkJS (`thumbmarkjs/thumbmarkjs`) | MIT | v1.12.0 | Signaux de cohérence secondaires : permissions, voix de synthèse vocale, WebRTC, matériel | Le calcul d'empreintes, la journalisation échantillonnée vers un point de terminaison du fournisseur, et le script distant, dont la licence n'est pas lue |
| FingerprintJS (`fingerprintjs/fingerprintjs`) | MIT jusqu'à v3.4.2 et à partir de v5.0.0 ; Business Source License 1.1 de v4.0.0 à v4.6.2 | v5.3.0 | Des techniques de collecte robustes comme entrées de cohérence : `platform` comparé à `userAgentData`, modules d'extension, fournisseur et moteur de rendu WebGL, `hardwareConcurrency`, écran | **Tout le code v4**, qui n'est pas libre et n'est pas lu ; le `visitorId` et tout calcul d'empreinte ; l'appel de surveillance |

**Jamais lu** : Benny the Doorman (paquet npm `doorman-benny`), sous une « Source-Available Non-Redistribution License »
qui interdit la redistribution, même modifiée.

Les détecteurs ont été écrits le 2026-11-04 à partir de ce que mesure chaque signal, après la lecture de ces références.

## Le pendant côté serveur

Les robots qui se déclarent (les robots d'indexation qui annoncent leur nom dans l'agent utilisateur) ne relèvent pas du
navigateur : ils sont reconnus sur le serveur à partir de l'agent utilisateur, avec une liste de robots comme
`jaybizzle/crawler-detect`, hors de ce bundle.
