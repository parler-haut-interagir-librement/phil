<!-- phil-dev source-sha256=578a360ed70bd4fe3edd023d8ff11396c70edf160b948d2339e4c8ac13d65c66 -->
# Séquences de courriel

Une séquence de courriel est une automatisation comme une autre : un déclencheur du paquet de l'infolettre, des actions
qui envoient un message ou modifient un abonnement, des délais entre elles, des filtres et des embranchements sur les
identifiants de l'abonné.

```json
{
    "id": "onboarding",
    "version": 1,
    "name": "Onboarding",
    "trigger": {"type": "newsletter.confirmed", "options": {"list": "members"}},
    "steps": [
        {"id": "welcome", "type": "action", "action": "newsletter.send", "options": {"template": "welcome"}, "retries": 3},
        {"id": "wait-3-days", "type": "delay", "seconds": 259200},
        {"id": "tips", "type": "action", "action": "newsletter.send", "options": {"template": "tips"}, "retries": 3},
        {"id": "wait-a-week", "type": "delay", "seconds": 604800},
        {"id": "events", "type": "action", "action": "newsletter.subscribe", "options": {"list": "events"}}
    ]
}
```

Le paquet de l'infolettre enregistre ces déclencheurs et ces actions quand cette bibliothèque est installée ; un autre
paquet peut offrir les siens.

## Ce qu'une séquence ne fait jamais

Une séquence ne démarre, n'attend ni n'embranche jamais sur l'ouverture d'un courriel. L'ouverture n'est pas un signal
fiable — les clients de courriel chargent les images avant le lecteur, et les protections de la vie privée la cachent —
et elle exige un pixel espion que le paquet de l'infolettre n'envoie pas. Une séquence réagit à ce que la personne fait
sur le site : un abonnement confirmé, un formulaire rempli, une commande payée, un lien cliqué quand le suivi des clics
par destinataire a été choisi.

Une séquence cesse d'envoyer à une personne qui s'est désabonnée ou qui a mis son abonnement en pause : l'action
d'envoi vérifie l'abonnement à chaque étape, de sorte qu'une exécution qui attend trois semaines n'écrit jamais à
quelqu'un qui est parti entre-temps.
