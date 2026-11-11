<!-- phil-dev source-sha256=c535401e5c68455d0d1fc781e40e20a8b86677fe6895d7f1bd79f16edbb6ea1c -->
# La page

`ContactFormController` affiche le formulaire et, une fois qu'un message est valide et envoyé, redirige vers la même
page avec `?sent=1` — `303 See Other` — afin que recharger la page n'envoie rien deux fois.

| Réponse | Quand |
|---|---|
| `200` | La page, ou la page après le départ d'un message (`sent` est vrai) |
| `303` | Le message est parti |
| `422` | Un champ manque ou est incorrect, ou la protection a retenu la soumission |
| `503` | Le transport de courriel a refusé le message : le formulaire reste rempli et invite à réessayer ; le refus est journalisé, jamais le message |

## Le formulaire

`ContactFormType`, vers un `ContactMessage` :

| Champ | Vérification |
|---|---|
| `name` | obligatoire, 100 caractères au plus |
| `email` | obligatoire, une adresse selon la RFC 5322 sans avertissement, telle que Symfony Mime l'écrit dans `Reply-To`, 254 caractères au plus |
| `subject` | facultatif, 150 caractères au plus |
| `message` | obligatoire, de 10 à 5000 caractères |

Il porte la protection de `phil/spam-protection-bundle`, avec la portée `contact_form`, et son sujet et son message sont
lus par les règles de contenu : une soumission retenue reçoit l'unique erreur de la protection, qui n'en dit jamais la
raison.

## Les gabarits

`@PhilContactForm/contact.html.twig` est une page autonome. Une application donne la sienne avec la clé `template`, en
étendant sa mise en page et en incluant le formulaire :

```twig
{% extends 'base.html.twig' %}
{% block body %}
    <h1>{{ 'contact_form.title'|trans({}, 'phil_contact_form') }}</h1>
    {% include '@PhilContactForm/_form.html.twig' %}
{% endblock %}
```

`_form.html.twig` indique si le message est parti ou a échoué, et produit le rendu du formulaire. Les libellés sont dans
le domaine de traduction `phil_contact_form`.
