# AI Recipe Generator Pro - Documentation

## 📦 Installation

1. **Télécharger le plugin** : Copiez tous les fichiers dans le dossier `/wp-content/plugins/ai-recipe-generator-pro/`
2. **Activer le plugin** : Dans l'admin WordPress, allez dans Extensions → Extensions installées → Activer "AI Recipe Generator Pro"
3. **Vérifier l'activation** : Un nouveau menu "AI Recipe Pro" devrait apparaître dans la barre latérale admin

## 🏗️ Structure des fichiers

```
ai-recipe-generator-pro/
├── ai-recipe-generator-pro.php    # Fichier principal (bootstrap)
├── README_PLUGIN.md               # Cette documentation
├── includes/
│   ├── class-argp-admin.php       # Gestion des menus et pages admin
│   ├── class-argp-settings.php    # Settings API (réglages)
│   └── class-argp-ajax.php        # Handlers AJAX (diagnostics, suggestions)
└── assets/
    ├── admin.js                   # Scripts JS pour l'interface admin
    └── admin.css                  # Styles CSS pour l'interface admin
```

## ⚙️ Configuration initiale

### 1. Accéder aux Réglages

Dans l'admin WordPress, allez dans **AI Recipe Pro → Réglages**

### 2. Configurer les clés API

#### OpenAI API Key
- Rendez-vous sur [OpenAI Platform](https://platform.openai.com/api-keys)
- Créez une nouvelle clé API
- Copiez la clé (commence par `sk-...`)
- Collez-la dans le champ "OpenAI API Key"

#### Replicate API Key
- Rendez-vous sur [Replicate Account](https://replicate.com/account/api-tokens)
- Créez un token API
- Copiez le token (commence par `r8_...`)
- Collez-le dans le champ "Replicate API Key"

### 3. Ajouter des titres manuels (optionnel)

Dans la section "Titres manuels préférés", vous pouvez ajouter une liste de titres d'articles que vous aimez (un par ligne). Ces titres seront utilisés comme référence pour générer des suggestions pertinentes.

Exemple :
```
10 recettes végétariennes faciles pour l'été
Guide complet des desserts au chocolat
Les secrets des chefs pour des pâtes parfaites
```

### 4. Lancer le diagnostic

Cliquez sur le bouton **"Lancer le test"** pour vérifier que votre serveur est correctement configuré.

Le diagnostic vérifie :
- ✅ `allow_url_fopen` activé
- ✅ Connexions externes avec `wp_remote_get`
- ✅ Version PHP (7.4+ requis)
- ✅ Version WordPress (5.8+ recommandé)
- ✅ Clés API configurées

## 🚀 Utilisation

### Page "Générer"

1. **Accéder à la page** : AI Recipe Pro → Générer
2. **Remplir le formulaire** :
   - **Sujet/Thème** : Le thème principal des recettes (ex: "recettes végétariennes")
   - **Nombre de recettes** : Choisir entre 1 et 10 recettes
   - **Titre** : Laisser vide pour génération automatique, ou cliquer sur "Suggérer"

### Suggestions de titres

1. Cliquez sur le bouton **"Suggérer"** à droite du champ "Titre"
2. Le système génère 3 suggestions basées sur :
   - Vos 15 derniers articles publiés
   - Les titres manuels configurés dans les réglages
   - Le sujet/thème que vous avez saisi
3. Cliquez sur une suggestion pour la sélectionner
4. Le titre est automatiquement rempli dans le champ

### Génération complète d'articles (Phase 3) ⭐

#### Étapes

1. **Accéder à la page** : AI Recipe Pro → Générer

2. **Remplir le formulaire** :
   - **Sujet/Thème** (requis) : Ex. "recettes végétariennes rapides"
   - **Nombre de recettes** : De 1 à 10
   - **Titre** : Laisser vide pour utiliser le sujet, ou utiliser "Suggérer"
   - **Statut de l'article** :
     - **Brouillon (draft)** : Recommandé pour relire avant publication
     - **Publié (publish)** : Publication immédiate

3. **Cliquer sur "Générer l'article complet"**

4. **Observer la progression** :
   - Barre de progression animée (0-100%)
   - Logs en temps réel :
     - ✓ Génération démarrée
     - ✓ Contenu généré avec succès
     - ✓ Article créé (ID: XXX)
     - ✓ Génération de l'image 1/3...
     - ✓ Recette 1/3 ajoutée avec image
     - ✓ Génération terminée !
   - Bouton "Annuler" disponible pendant la génération

5. **Résultats** :
   - Message de succès
   - Lien "Modifier l'article" pour éditer dans WordPress
   - Warnings si certaines images n'ont pas pu être générées
   - Bouton "Générer un autre article"

#### Ce qui est généré

L'article contient :
- **Introduction** : Paragraphe engageant généré par OpenAI
- **Pour chaque recette** :
  - Titre (H2)
  - Image culinaire réaliste (générée par Replicate)
  - Liste des ingrédients (H3 + liste à puces)
  - Instructions étape par étape (H3 + liste numérotée)

#### Temps de génération

Approximatif (dépend des API) :
- 1 recette : ~45-60 secondes
- 3 recettes : ~1m30-2m
- 10 recettes : ~5-8 minutes

#### En cas de problème

- **Images manquantes** : L'article est créé malgré tout, un warning s'affiche
- **Timeout OpenAI** : Réessayez après quelques minutes
- **Quota dépassé** : Vérifiez vos crédits sur OpenAI/Replicate

## 🔒 Sécurité

Le plugin respecte toutes les bonnes pratiques WordPress :

- **Nonces** : Tous les formulaires et requêtes AJAX sont protégés par des nonces
- **Capabilities** : Seuls les utilisateurs avec la permission `manage_options` peuvent accéder aux fonctionnalités
- **Sanitization** : Toutes les entrées utilisateur sont nettoyées (sanitize)
- **Escaping** : Toutes les sorties sont échappées pour éviter les injections XSS

## 📋 Phases de développement

### ✅ Phase 1 (Complété)
- Interface admin complète
- Page Réglages avec Settings API
- Diagnostics système
- Sauvegarde sécurisée des clés API

### ✅ Phase 2 (Complété)
- Page Générer avec formulaire
- **Intégration OpenAI (GPT-4o) pour suggestions de titres**
- Suggestions intelligentes basées sur :
  - Les 15 derniers titres du blog
  - Les titres manuels préférés
  - Le sujet/thème fourni
- Gestion complète des erreurs (401, 429, timeout, etc.)
- UX optimale avec spinner et messages clairs

### ✅ Phase 3 (Complété) ⭐
- **Génération complète d'articles WordPress** avec texte + images
- Architecture job/transient pour éviter les timeouts
- **OpenAI (GPT-4o)** : Génération de contenu structuré (JSON)
- **Replicate (Flux 2 Pro)** : Génération d'images culinaires
- Téléchargement automatique des images dans la Media Library
- Création d'articles en draft ou publish
- Barre de progression en temps réel
- Logs détaillés de chaque étape
- Gestion d'erreurs robuste (continue sans image si échec Replicate)

### 🔄 Phase 4-5 (À venir)
- Exports (PDF, JSON, schema.org)
- Optimisations performances

### 🔄 Phase 4 (À venir)
- Intégration Replicate pour génération d'images
- Téléchargement automatique dans la bibliothèque WP
- Association des images aux recettes

### 🔄 Phase 5 (À venir)
- Publication automatique des articles
- Gestion des featured images
- Support des catégories et tags
- Export des recettes

## 🛠️ Support technique

### Problèmes courants

**Le plugin ne s'active pas**
- Vérifiez que vous utilisez PHP 7.4 ou supérieur
- Vérifiez que WordPress est en version 5.8 ou supérieur

**Les diagnostics échouent**
- Vérifiez que `allow_url_fopen` est activé dans votre php.ini
- Vérifiez que votre serveur peut faire des requêtes HTTP externes
- Contactez votre hébergeur si nécessaire

**Les suggestions ne fonctionnent pas**
- Vérifiez que vous avez des articles publiés sur votre blog
- Vérifiez que JavaScript est activé dans votre navigateur
- Ouvrez la console du navigateur (F12) pour voir les erreurs

### Logs de debug

Pour activer le mode debug WordPress, ajoutez dans `wp-config.php` :

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Les logs seront enregistrés dans `/wp-content/debug.log`

## 📝 Conventions de code

Le plugin suit les [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) :

- **Préfixe** : Tous les noms de fonctions/classes utilisent le préfixe `argp_` ou `ARGP_`
- **Hooks** : Utilisation extensive des hooks WordPress (actions et filtres)
- **Internationalisation** : Textes prêts pour la traduction (text domain : `ai-recipe-generator-pro`)
- **Architecture OOP** : Classes avec pattern Singleton
- **Sécurité** : Nonces, sanitization, escaping

## 🌍 Internationalisation

Le plugin est prêt pour la traduction. Pour créer une traduction :

1. Créer un dossier `/languages/` à la racine du plugin
2. Utiliser un outil comme Poedit pour créer les fichiers `.po` et `.mo`
3. Nom du fichier : `ai-recipe-generator-pro-fr_FR.po` (exemple pour le français)

## 📄 Licence

GPL v2 or later

## 👨‍💻 Développé avec

- WordPress Settings API
- jQuery (inclus dans WordPress)
- Pattern Singleton pour les classes
- AJAX natif WordPress
- WP_Filesystem (préparé pour Phase 4)

---

**Version** : 1.0.0  
**Auteur** : Votre Nom  
**Dernière mise à jour** : 2026-02-05
