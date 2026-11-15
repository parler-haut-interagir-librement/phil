<!-- phil-dev source-sha256=c12baed0f1cee405aa85bd3db536492dad502171a6b7c502bf662ea52f897e57 -->
# Vie privée et accessibilité

## Ce qui atteint une plateforme

Rien, tant que le spectateur n'appuie pas sur le bouton d'une vidéo liée. La façade est l'image et le texte du site ; le
lecteur de la plateforme se charge ensuite depuis l'adresse qui n'écrit aucun témoin, laquelle atteint tout de même la
plateforme avec l'adresse du spectateur — la phrase sous la vidéo le dit avant l'appui. Le cadre a une politique de
référent stricte et un bac à sable. Un spectateur sans JavaScript suit un lien vers la plateforme, en connaissance de
cause.

Une vidéo hébergée vient du site, ou d'hôtes que le site liste pour `image_origin()`.

## Ce qui est compté

Seulement avec le consentement du spectateur, demandé au gestionnaire de consentement de l'application quand la page est
dessinée et de nouveau quand un visionnement arrive ; avec le suivi désactivé, rien. Ce qui est conservé est une carte de
chaleur agrégée : cent compteurs par vidéo, et le nombre de visionnements. Aucun visionnement n'est conservé, aucun
spectateur nommé ; `VideoProgressed` dit ce qui a été regardé, pas par qui.

## Ce que le lecteur doit à ses spectateurs

- Les commandes sont celles du navigateur, atteintes au clavier ; rien ne joue avant que le spectateur le demande
  (WCAG 1.4.2, 2.2.2).
- Les sous-titres pour malentendants en premier parmi les pistes, et la piste par défaut affichée aussitôt
  (WCAG 1.2.2) ; des descriptions pour ce qui n'est que montré (WCAG 1.2.5).
- Une transcription à côté de la vidéo, dans sa langue (WCAG 1.2.8), et les chapitres comme table des matières, tous deux
  atteints au clavier et, pour une vidéo hébergée, des liens vers leurs instants sans JavaScript.
- Le bouton d'une façade dit ce qu'il joue et ce qu'il charge ; le cadre qu'il ouvre porte le titre de la vidéo.
- Le chapitre et le repère courants sont marqués par `aria-current`, pas par la couleur seule.
