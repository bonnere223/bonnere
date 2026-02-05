# # AI Recipe Generator Pro - Plugin WordPress

## 🎯 Objectif

Plugin WordPress professionnel pour générer des recettes intelligentes avec OpenAI et Replicate, puis les publier automatiquement.

## ✅ Statut : Phase 5 Complète - Production Ready ! 🔒⚡

Le plugin est sécurisé, optimisé et prêt pour la production !

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

### Phase 2 : Suggestions de titres avec OpenAI ✅
- Page "Générer" avec formulaire complet
- Champs : Sujet/Thème, Nombre de recettes, Titre
- **Intégration OpenAI (GPT-4o) pour suggestions intelligentes**
- Suggestions basées sur :
  - Les 15 derniers articles publiés
  - Les titres manuels préférés (réglages)
  - Le sujet/thème fourni
- Gestion d'erreurs complète (clé invalide, quota, timeout)
- Interface AJAX réactive avec spinner
- Design moderne et responsive

### Phase 3 : Génération complète d'articles ✅ ⭐
- **Architecture job/transient avec polling** (évite timeouts PHP)
- **OpenAI (GPT-4o)** : Génération JSON structuré
  - Introduction engageante
  - Recettes avec nom, ingrédients, instructions, image_prompt
  - Temperature 0.7, max 3000 tokens
- **Replicate (Flux 2 Pro)** : Génération d'images
  - Polling automatique des prédictions
  - Gestion quota/erreurs (continue sans image si échec)
- **Media Library WordPress** :
  - Téléchargement automatique des images
  - Intégration avec `media_handle_sideload()`
  - Association au post parent
- **Création d'articles** :
  - Statut draft ou publish au choix
  - Format HTML structuré (H2, H3, ul, ol)
  - Mise à jour incrémentale du contenu
- **Interface utilisateur** :
  - Barre de progression animée (0-100%)
  - Logs détaillés en temps réel avec timestamps
  - Bouton annulation
  - Lien "Modifier l'article" en fin de génération
  - Affichage des erreurs warnings
- **Système de tick** : Polling AJAX toutes les 2s
- **Sécurité renforcée** : Nonces, capabilities, transient avec expiration

### Phase 4 : Exports (ZIP & TXT) ✅ 🚀
- **Metabox sur écran d'édition** :
  - Sidebar droite avec 2 boutons
  - Design épuré avec icônes
  - Messages info et warnings
- **Export ZIP des images** :
  - Extraction intelligente des images (wp-image-* + fallback URL)
  - Renommage automatique : recette-1.jpg, recette-2.jpg...
  - Support ZipArchive + fallback PclZip
  - Streaming sécurisé (pas de fichiers publics)
- **Export TXT des recettes** :
  - Extraction via DOMDocument + fallback regex
  - Format propre : nom en majuscules + instructions numérotées
  - Sans HTML parasite
  - Encodage UTF-8
- **Sécurité** :
  - Nonces uniques par post
  - Permissions edit_post vérifiées
  - Fichiers temporaires nettoyés
  - Streaming direct avec exit
- **Gestion d'erreurs** :
  - Messages clairs si aucune image/recette
  - Fallback automatique si ZipArchive absent
  - Validation complète des inputs

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

### Phase 5 : Sécurité & Performance ✅ 🔒⚡

**Sécurité renforcée** :
- ✅ Chiffrement clés API (AES-256-CBC avec OpenSSL)
- ✅ Rate limiting (max 2 jobs, cooldown 30s)
- ✅ Protection SSRF (whitelist Replicate)
- ✅ Validations renforcées (clamp 1-10, limites 200 char)
- ✅ Échappement XSS systématique

**Performance & Fiabilité** :
- ✅ Système de reprise automatique de job
- ✅ TTL transients optimisé (30min + refresh)
- ✅ Cron nettoyage quotidien
- ✅ Timeouts optimisés (20-30s)
- ✅ Mode Debug avec logs activables

**UX & Accessibilité** :
- ✅ ARIA labels (aria-live, aria-busy)
- ✅ États disabled visuels
- ✅ Focus visible amélioré
- ✅ Échappement messages utilisateur

## 🔄 Phases optionnelles futures

- **Phase 6** : Exports avancés (PDF, schema.org, analytics)

## 📄 Licence

GPL v2 or later

## 🌟 Convention de nommage

Tous les éléments du plugin utilisent le préfixe **ARGPro** ou **argp_** pour éviter les conflits.

---

**Version** : 1.0.0  
**Dernière mise à jour** : 5 février 2026  
**Branche de développement** : `cursor/argp-plugin-squelette-9fbf`
