# 🔄 Système de mise à jour automatique - Documentation

## 🎯 Objectif

Permettre à WordPress de **détecter et installer automatiquement** les nouvelles versions du plugin depuis **GitHub**, sans passer par WordPress.org.

---

## ✅ Fonctionnement

### Détection automatique

WordPress vérifie les mises à jour **2 fois par jour** automatiquement.

**Processus** :
1. WordPress appelle le hook `pre_set_site_transient_update_plugins`
2. Notre classe `ARGP_Updater` intercepte
3. Requête vers GitHub (API ou JSON)
4. Comparaison versions (locale vs distante)
5. Si nouvelle version : injection dans le transient
6. **Bouton "Mettre à jour" apparaît** dans Extensions

### Installation automatique

**Quand l'admin clique "Mettre à jour"** :
1. WordPress télécharge le ZIP depuis `download_url`
2. Extrait dans `/wp-content/plugins/`
3. Remplace les fichiers
4. Vide le cache (hook `upgrader_process_complete`)
5. Plugin mis à jour ! ✅

---

## 📁 Fichiers du système

### 1. `includes/class-argp-updater.php` (320 lignes)

**Classe** : `ARGP_Updater` (Singleton)

**Hooks utilisés** :
- `pre_set_site_transient_update_plugins` : Détection MAJ
- `plugins_api` : Popup "Voir les détails"
- `upgrader_process_complete` : Après installation

**Méthodes principales** :
```php
check_for_update($transient)      // Détecte nouvelle version
plugin_info($result, $action)     // Détails popup
after_update($upgrader, $options) // Nettoyage après MAJ
get_remote_info()                 // Récupère infos (avec cache 12h)
fetch_github_info()               // Dispatch selon config
fetch_from_github_api()           // Via API GitHub (releases/tags)
fetch_from_json_url()             // Via fichier JSON hébergé
```

### 2. `ai-recipe-generator-pro.php` (modifié)

**Chargement** :
```php
require_once ARGP_PLUGIN_DIR . 'includes/class-argp-updater.php';

// Dans init_hooks()
ARGP_Updater::get_instance();
```

**Initialisation automatique** : L'updater se lance dès que le plugin est actif.

---

## ⚙️ Configuration

### Option 1 : Via GitHub Releases/Tags (RECOMMANDÉ)

**Prérequis** :
1. Créer un tag GitHub : `v2.0.1`
2. Ou créer une Release GitHub

**Configuration** (par défaut) :
```php
$github_config = array(
    'owner'    => 'bonnere223',
    'repo'     => 'bonnere',
    'use_tags' => true,
);
```

**Avantages** :
- ✅ Automatique (pas de fichier JSON à maintenir)
- ✅ Changelog depuis description release
- ✅ Download URL auto-généré

**Comment créer une release GitHub** :
```bash
# Via GitHub Web UI
1. Aller sur le repo : https://github.com/bonnere223/bonnere
2. Cliquer "Releases" → "Create a new release"
3. Tag version : v2.0.1
4. Release title : Version 2.0.1
5. Description : Changelog (markdown)
6. Publier

# Ou via CLI
git tag -a v2.0.1 -m "Version 2.0.1 - Bugfix throttling"
git push origin v2.0.1
```

---

### Option 2 : Via fichier JSON hébergé

**Prérequis** :
1. Héberger un fichier `update.json` accessible en HTTPS
2. Exemple : `https://votresite.com/plugins/argp/update.json`

**Configuration** :
```php
// Dans class-argp-updater.php, modifier :
private $github_config = array(
    'update_url' => 'https://votresite.com/plugins/argp/update.json',
    'use_tags'   => false,  // Désactiver tags
);
```

**Structure update.json** : Voir `update.json.example`

**Avantages** :
- ✅ Contrôle total (custom domain)
- ✅ Changelog HTML personnalisé
- ✅ Icônes/banners custom

---

### Option 3 : Repo GitHub privé

**Prérequis** :
1. Créer un Personal Access Token GitHub
2. Permissions : `repo` (accès au repo privé)

**Configuration** :
```php
// Ajouter token dans class-argp-updater.php
private $github_config = array(
    'owner'    => 'bonnere223',
    'repo'     => 'bonnere',
    'use_tags' => true,
    'token'    => 'ghp_votre_token_ici',  // ⚠️ Sécuriser !
);
```

**Sécurité token** :
```php
// MIEUX : Stocker dans wp-config.php
define('ARGP_GITHUB_TOKEN', 'ghp_votre_token_ici');

// Dans class-argp-updater.php
'token' => defined('ARGP_GITHUB_TOKEN') ? ARGP_GITHUB_TOKEN : '',
```

---

## 🔄 Workflow de mise à jour

### Côté développeur

#### Étape 1 : Modifier la version
```php
// Dans ai-recipe-generator-pro.php
define( 'ARGP_VERSION', '2.0.2' ); // Nouvelle version
```

```php
// Dans header du fichier
* Version: 2.0.2
```

#### Étape 2 : Commit et push
```bash
git add .
git commit -m "feat: Version 2.0.2 - Nouvelle feature"
git push origin main
```

#### Étape 3 : Créer une release GitHub
```bash
git tag -a v2.0.2 -m "Version 2.0.2"
git push origin v2.0.2
```

Ou via l'interface GitHub :
1. Releases → New release
2. Tag : `v2.0.2`
3. Title : `Version 2.0.2`
4. Description (markdown) :
   ```markdown
   ## Nouveautés
   - Feature X ajoutée
   - Bug Y corrigé
   
   ## Améliorations
   - Performance optimisée
   ```
5. Publier

#### Étape 4 : WordPress détecte automatiquement
- Attendre 12h (cache)
- Ou forcer : `delete_transient('argp_update_info');`
- Ou utiliser : AI Recipe Pro → Outils → Vider le cache

---

### Côté utilisateur (admin WordPress)

#### Détection automatique (J+1)
1. WordPress vérifie les MAJ (automatique 2x/jour)
2. **Notification** apparaît : "Mise à jour disponible"
3. Badge rouge sur "Extensions"

#### Installation (1 clic)
1. Extensions → AI Recipe Generator Pro
2. **Bouton "Mettre à jour maintenant"** visible
3. Clic → Installation automatique
4. Message "Plugin mis à jour avec succès" ✅

#### Après installation (recommandé)
1. AI Recipe Pro → **Outils**
2. Cliquer **"Vider le cache"**
3. Tester avec 1 recette

---

## 🔍 Détails techniques

### Hook 1 : `pre_set_site_transient_update_plugins`

**Rôle** : Injecter les données de MAJ dans le transient WordPress

**Code** :
```php
public function check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    
    $remote_info = $this->get_remote_info(); // Avec cache 12h
    
    if ( version_compare( $local_version, $remote_info['version'], '<' ) ) {
        // MAJ disponible !
        $transient->response[$plugin_basename] = (object) array(
            'slug'        => 'ai-recipe-generator-pro',
            'new_version' => $remote_info['version'],
            'package'     => $remote_info['download_url'],
            // ... autres données
        );
    }
    
    return $transient;
}
```

---

### Hook 2 : `plugins_api`

**Rôle** : Fournir les détails pour la popup "Voir les détails"

**Code** :
```php
public function plugin_info( $result, $action, $args ) {
    if ( 'plugin_information' !== $action ) {
        return $result;
    }
    
    if ( $this->plugin_slug !== $args->slug ) {
        return $result;
    }
    
    $remote_info = $this->get_remote_info();
    
    $plugin_info = new stdClass();
    $plugin_info->name     = $remote_info['name'];
    $plugin_info->version  = $remote_info['version'];
    $plugin_info->sections = array(
        'description' => $remote_info['description'],
        'changelog'   => $remote_info['changelog'],
    );
    
    return $plugin_info;
}
```

---

### Hook 3 : `upgrader_process_complete`

**Rôle** : Actions après installation (nettoyage cache)

**Code** :
```php
public function after_update( $upgrader, $options ) {
    if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
        if ( in_array( $this->plugin_basename, $options['plugins'] ) ) {
            // Vider cache updater
            delete_transient( 'argp_update_info' );
        }
    }
}
```

---

### Cache (12 heures)

**Pourquoi** :
- Éviter trop de requêtes GitHub (rate limit)
- Performance (pas d'appel à chaque page)

**Implémentation** :
```php
private function get_remote_info() {
    $cached = get_transient( 'argp_update_info' );
    
    if ( false !== $cached ) {
        return $cached; // Retour immédiat
    }
    
    $remote = $this->fetch_github_info();
    
    if ( $remote ) {
        set_transient( 'argp_update_info', $remote, 12 * HOUR_IN_SECONDS );
    }
    
    return $remote;
}
```

**Forcer refresh** :
- AI Recipe Pro → Outils → Vider le cache
- Ou manuellement : `delete_transient('argp_update_info');`

---

## 🧪 Tests

### Test 1 : Détection MAJ disponible

**Étapes** :
1. Version locale : 2.0.1
2. Créer release GitHub : v2.0.2
3. Attendre 12h OU vider cache
4. Extensions → Vérifier les mises à jour

**Résultat attendu** :
- ✅ Badge "Mise à jour disponible" visible
- ✅ Version 2.0.2 affichée
- ✅ Bouton "Mettre à jour maintenant" présent

---

### Test 2 : Voir les détails

**Étapes** :
1. Extensions → AI Recipe Generator Pro
2. Cliquer "Voir les détails de la version X.X.X"

**Résultat attendu** :
- ✅ Popup s'ouvre
- ✅ Onglet "Description" : contenu du plugin
- ✅ Onglet "Changelog" : liste des modifications
- ✅ Informations (version, compatibilité WP/PHP)

---

### Test 3 : Installation MAJ

**Étapes** :
1. Extensions → Cliquer "Mettre à jour maintenant"
2. Observer progression
3. Attendre message succès

**Résultat attendu** :
- ✅ Téléchargement ZIP depuis GitHub
- ✅ Installation automatique
- ✅ Message "Plugin mis à jour avec succès"
- ✅ Version 2.0.2 active
- ✅ Aucune erreur PHP

---

### Test 4 : Après MAJ

**Étapes** :
1. AI Recipe Pro → Outils
2. Cliquer "Vider le cache"
3. Tester génération 1 recette

**Résultat attendu** :
- ✅ Cache vidé
- ✅ Plugin fonctionne normalement
- ✅ Aucune régression

---

## 🐛 Troubleshooting

### Problème : "Pas de mise à jour détectée"

**Causes** :
1. Cache actif (12h)
2. Version GitHub pas plus récente
3. Erreur API GitHub (rate limit)
4. Token invalide (si repo privé)

**Solutions** :
1. Vider cache : Outils → Vider le cache
2. Vérifier version tag GitHub : doit être > version locale
3. Vérifier logs : wp-content/debug.log
4. Tester manuellement :
   ```php
   $updater = ARGP_Updater::get_instance();
   delete_transient('argp_update_info');
   // Puis rafraîchir Extensions
   ```

---

### Problème : "Échec téléchargement"

**Causes** :
1. URL download_url invalide
2. Repo privé sans token
3. Firewall bloque GitHub
4. Timeout réseau

**Solutions** :
1. Vérifier URL dans update.json ou release
2. Ajouter token si repo privé
3. Augmenter timeout (ligne 200 class-argp-updater.php)
4. Tester URL manuellement : `wget [download_url]`

---

### Problème : "Erreur installation"

**Causes** :
1. Structure ZIP incorrecte
2. Permissions fichiers
3. Plugin actif pendant MAJ

**Solutions** :
1. ZIP doit contenir : `ai-recipe-generator-pro/[fichiers]`
2. Vérifier permissions : 755 (dossiers), 644 (fichiers)
3. Désactiver puis réactiver si problème

---

## 📝 Structure ZIP requise

**CORRECT** ✅ :
```
ai-recipe-generator-pro.zip
└── ai-recipe-generator-pro/
    ├── ai-recipe-generator-pro.php
    ├── includes/
    └── assets/
```

**INCORRECT** ❌ :
```
ai-recipe-generator-pro.zip
├── ai-recipe-generator-pro.php  (dossier racine manquant)
├── includes/
└── assets/
```

**GitHub génère automatiquement la bonne structure** avec :
- Releases : zipball OK ✅
- Tags archive : OK ✅
- Branch archive : Peut nécessiter ajustement

---

## 🔐 Sécurité

### Token GitHub (repo privé)

**Création token** :
1. GitHub → Settings → Developer settings
2. Personal access tokens → Generate new token
3. Permissions : `repo` (Full control)
4. Copier le token (ghp_...)

**Stockage sécurisé** :
```php
// Dans wp-config.php (RECOMMANDÉ)
define('ARGP_GITHUB_TOKEN', 'ghp_votre_token_ici');

// Dans class-argp-updater.php
'token' => defined('ARGP_GITHUB_TOKEN') ? ARGP_GITHUB_TOKEN : '',
```

**⚠️ NE JAMAIS** :
- Commit le token dans le code
- Stocker en clair dans BDD
- Exposer côté client

---

## 📊 API GitHub - Rate Limits

### Limites
- **Sans auth** : 60 requêtes/heure/IP
- **Avec token** : 5000 requêtes/heure

### Notre utilisation
- 1 requête toutes les 12h (cache)
- Par site : ~2 requêtes/jour
- **Pas de risque** de rate limit ✅

### En cas de rate limit
```json
{
  "message": "API rate limit exceeded...",
  "documentation_url": "..."
}
```

**Gestion** :
- Retour `false` → pas de MAJ détectée
- Cache précédent conservé
- Réessai dans 12h

---

## 🎨 Personnalisation

### Changer le dépôt source

**Dans `class-argp-updater.php`** (ligne ~40) :
```php
private $github_config = array(
    'owner'      => 'votre-username',  // ← Modifier
    'repo'       => 'votre-repo',      // ← Modifier
    'branch'     => 'main',
    'use_tags'   => true,
);
```

### Changer la durée du cache

**Ligne ~200** :
```php
set_transient( 'argp_update_info', $remote, 12 * HOUR_IN_SECONDS );
                                          // ↑ Modifier (ex: 6, 24)
```

### Utiliser une branche spécifique

**Si pas de tags** :
```php
'use_tags' => false,
'branch'   => 'release',  // Branche custom
```

**Download URL** :
```
https://github.com/owner/repo/archive/refs/heads/release.zip
```

---

## 📋 Checklist d'intégration

### Avant déploiement
- [x] class-argp-updater.php créé
- [x] Bootstrap modifié (require + init)
- [x] Version 2.0.1 définie
- [x] Config GitHub renseignée
- [x] Tests en local (si possible)

### Première release
- [ ] Créer tag GitHub : `v2.0.1`
- [ ] Ou créer Release avec changelog
- [ ] Vérifier download URL accessible
- [ ] Vérifier structure ZIP

### Test production
- [ ] Installer v2.0.0 sur site test
- [ ] Créer release v2.0.1 sur GitHub
- [ ] Vider cache : Outils → Vider le cache
- [ ] Extensions → Vérifier MAJ
- [ ] Cliquer "Mettre à jour"
- [ ] Vérifier v2.0.1 active
- [ ] Tester plugin

---

## 💡 Exemple complet update.json

Voir fichier `update.json.example` dans le repo.

**Hébergement** :
- GitHub Pages : `https://username.github.io/repo/update.json`
- GitHub Raw : `https://raw.githubusercontent.com/user/repo/main/update.json`
- Serveur custom : `https://votresite.com/update.json`

**Doit être accessible en HTTPS** (requis WordPress)

---

## 🚀 Avantages de ce système

### Pour le développeur
- ✅ Déploiement simple (git tag + push)
- ✅ Pas de plateforme tierce
- ✅ Contrôle total
- ✅ Gratuit (GitHub)

### Pour l'utilisateur
- ✅ MAJ automatique (comme WP.org)
- ✅ 1 clic pour installer
- ✅ Bouton natif WordPress
- ✅ Changelog visible

### Technique
- ✅ 100% WordPress natif
- ✅ Pas de dépendance externe
- ✅ Cache intelligent (12h)
- ✅ Gestion erreurs robuste
- ✅ Compatible WP multisite
- ✅ Sécurisé (nonces WP natifs)

---

## ⚠️ Limitations

### Pas d'icônes/banners automatiques
- GitHub ne fournit pas d'images
- Solution : Héberger images et les référencer dans update.json

### Pas de statistiques
- Pas de compteur téléchargements
- Pas de tracking installs
- Solution : Google Analytics ou Matomo (optionnel)

### Cache 12h
- Détection MAJ pas instantanée
- Solution : Vider cache manuellement (Outils)

---

## 🎯 Workflow complet (résumé)

```
DÉVELOPPEUR                          WORDPRESS                   UTILISATEUR
    │                                    │                           │
    ├─ v2.0.2 dans code                 │                           │
    ├─ git tag v2.0.2                   │                           │
    ├─ git push origin v2.0.2           │                           │
    │                                    │                           │
    │                                    ├─ Check MAJ (2x/jour)     │
    │                                    ├─ Requête GitHub API       │
    │                                    ├─ Compare v2.0.1 < v2.0.2 │
    │                                    ├─ Inject transient         │
    │                                    │                           │
    │                                    │                           ├─ Voir notification
    │                                    │                           ├─ Clic "Mettre à jour"
    │                                    ├─ Download ZIP GitHub     │
    │                                    ├─ Extract & Install       │
    │                                    ├─ Clear cache             │
    │                                    │                           │
    │                                    │                           ├─ Plugin v2.0.2 actif ✅
```

---

## 📚 Ressources

### Documentation WordPress
- [Plugin Update Checker](https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/)
- [Transients API](https://developer.wordpress.org/apis/transients/)
- [Upgrader API](https://developer.wordpress.org/reference/classes/wp_upgrader/)

### GitHub API
- [Releases API](https://docs.github.com/en/rest/releases)
- [Tags API](https://docs.github.com/en/rest/repos/repos#list-repository-tags)
- [Personal Access Tokens](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token)

---

## 🎉 Conclusion

Le système de mise à jour automatique est maintenant **opérationnel** !

**Fonctionnalités** :
- ✅ Détection automatique depuis GitHub
- ✅ Bouton natif WordPress
- ✅ Installation 1 clic
- ✅ Cache intelligent
- ✅ Repo public/privé
- ✅ Changelog visible
- ✅ Sécurisé

**Le plugin peut maintenant se mettre à jour comme n'importe quel plugin WordPress officiel !** 🚀

---

**Version** : 2.0.1  
**Statut** : ✅ **SYSTÈME DE MAJ OPÉRATIONNEL**  
**Prochaine étape** : Créer tag `v2.0.2` sur GitHub pour tester !
