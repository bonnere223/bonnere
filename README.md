# # AI Recipe Generator Pro - Plugin WordPress

## 🎯 Objectif

Plugin WordPress professionnel pour générer des recettes intelligentes avec OpenAI et Replicate, puis les publier automatiquement.

## ✅ Statut : MVP Complet (Phases 1 + 2)

Le squelette complet du plugin est maintenant prêt et fonctionnel.

## 📦 Contenu du dépôt

- `ai-recipe-generator-pro.php` - Fichier principal du plugin (147 lignes)
- `includes/` - Classes PHP (889 lignes)
  - `class-argp-admin.php` - Gestion admin
  - `class-argp-settings.php` - Settings API
  - `class-argp-ajax.php` - Handlers AJAX
- `assets/` - Assets front-end (723 lignes)
  - `admin.js` - Scripts JavaScript
  - `admin.css` - Styles CSS
- `README_PLUGIN.md` - Documentation utilisateur
- `INSTALLATION_ET_TEST.md` - Guide d'installation et tests

**Total : 2264 lignes de code et documentation**

## 🚀 Installation rapide

1. Copiez tous les fichiers dans `/wp-content/plugins/ai-recipe-generator-pro/`
2. Activez le plugin dans WordPress
3. Allez dans **AI Recipe Pro → Réglages** pour configurer les clés API
4. Consultez `INSTALLATION_ET_TEST.md` pour les tests détaillés

## 📋 Fonctionnalités implémentées

### Phase 1 : Réglages & Diagnostics ✅
- Settings API complète
- Sauvegarde sécurisée des clés API (OpenAI, Replicate)
- Titres manuels préférés
- Diagnostics système avec badges visuels :
  - `allow_url_fopen`
  - Connexions externes (`wp_remote_get`)
  - Versions PHP et WordPress
  - Vérification des clés API

### Phase 2 : Interface de génération ✅
- Page "Générer" avec formulaire complet
- Champs : Sujet/Thème, Nombre de recettes, Titre
- Suggestions de titres intelligentes (basées sur les 15 derniers articles + titres manuels)
- Interface AJAX réactive
- Design moderne et responsive

## 🔒 Sécurité

- ✅ Nonces sur tous les formulaires et AJAX
- ✅ Vérification des permissions (`manage_options`)
- ✅ Sanitization de toutes les entrées
- ✅ Escaping de toutes les sorties
- ✅ Respect des WordPress Coding Standards

## 🛠️ Technologies utilisées

- WordPress Settings API
- AJAX natif WordPress
- jQuery (inclus dans WP)
- Pattern Singleton (OOP)
- Hooks WordPress (admin_menu, admin_init, admin_enqueue_scripts, wp_ajax_*)

## 📖 Documentation

Consultez les fichiers de documentation :
- **README_PLUGIN.md** : Documentation complète du plugin
- **INSTALLATION_ET_TEST.md** : Guide d'installation et 7 tests détaillés

## 🔄 Phases futures

- **Phase 3** : Intégration OpenAI (génération de contenu)
- **Phase 4** : Intégration Replicate (génération d'images)
- **Phase 5** : Publication automatique et exports

## 📄 Licence

GPL v2 or later

## 🌟 Convention de nommage

Tous les éléments du plugin utilisent le préfixe **ARGPro** ou **argp_** pour éviter les conflits.

---

**Version** : 1.0.0  
**Dernière mise à jour** : 5 février 2026  
**Branche de développement** : `cursor/argp-plugin-squelette-9fbf`
