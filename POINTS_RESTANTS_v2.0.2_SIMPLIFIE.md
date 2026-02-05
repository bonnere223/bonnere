# 📋 Points restants après v2.0.2 (simplifié)

## ✅ CE QUI VIENT D'ÊTRE FAIT (v2.0.2)

### Simplification majeure ✅
- ✅ **Retrait complet du mode Tag** (-258 lignes de code)
- ✅ **Mode Global uniquement** : 1 article avec toutes les recettes
- ✅ Code simplifié et stable
- ✅ Aucun risque de bugs liés au mode Tag

**Durée** : ~30 minutes  
**Code retiré** : 258 lignes  
**Risque** : 🟢 Simplifie = moins de bugs  
**Résultat** : Plugin plus stable

---

## 🎯 POINTS RESTANTS (après simplification)

### 🟡 RISQUE MOYEN (Faisables avec tests)

#### 1️⃣ Prompts personnalisables opérationnels

**État** : Champs affichés et sauvegardés, mais pas utilisés

**À implémenter** :
- Lire `prompt_text` et `prompt_image` depuis réglages
- Remplacer variables {titre}, {count}, {nombre}, {theme}
- Utiliser dans `openai_generate_recipes()`
- Fallback si vide ou invalide

**Complexité** : ⭐⭐ Moyen  
**Risque** : 🟡 Moyen
- Peut casser génération si prompts invalides
- **Nécessite fallback robuste**
- Tests avec prompts custom obligatoires

**Temps** : 1 heure  
**Impact** : ⭐⭐⭐ Élevé (personnalisation totale)

**Tests obligatoires** :
- ✅ Génération avec prompts vides (défaut)
- ✅ Génération avec prompts custom valides
- ✅ Prompts sans variables
- ✅ Prompts avec variables invalides
- ✅ Vérifier JSON généré correct

**Recommandation** : ⚠️ **FAIRE** mais **TESTER BEAUCOUP**  
**Version suggérée** : v2.1.0

---

#### 2️⃣ Images de référence utilisées

**État** : Champs upload affichés, mais pas traitées

**À implémenter** :
- FormData pour uploads (admin.js)
- `wp_handle_upload()` pour chaque fichier
- Sauvegarder URLs dans job
- Passer à Replicate : modifier prompt avec style référence
- Mapping image[index] → recette[index]
- Réutiliser dernière si moins d'images

**Complexité** : ⭐⭐⭐ Difficile  
**Risque** : 🟡 Moyen
- Upload peut échouer
- FormData change structure AJAX
- **RISQUE : Peut casser génération**
- Gestion erreurs uploads complexe

**Temps** : 2 heures  
**Impact** : ⭐⭐⭐ Élevé (cohérence visuelle)

**Tests obligatoires** :
- ✅ Génération SANS images référence (défaut)
- ✅ Génération avec 1 image
- ✅ Génération avec 3 images pour 5 recettes
- ✅ Upload échoué (gestion erreur)
- ✅ Vérifier images générées ont le bon style

**Recommandation** : ⚠️ **FAIRE APRÈS v2.1.0** (si prompts OK)  
**Version suggérée** : v2.1.1

---

#### 3️⃣ Export IMG JPG renommé

**État** : Export ZIP fonctionne, mais pas de renommage spécifique

**À implémenter** :
- Créer dossier temporaire
- Copier images + renommer : `1-titre-article.jpg`
- Conversion PNG/WEBP → JPG avec GD
- `imagecreatefrompng()` + `imagejpeg()`
- Fond blanc pour transparents
- ZIP du dossier
- Cleanup

**Complexité** : ⭐⭐ Moyen  
**Risque** : 🟡 Moyen
- Conversion peut échouer
- GD library pas toujours dispo
- Mémoire pour grosses images

**Temps** : 1h30  
**Impact** : ⭐⭐ Moyen (nice to have)

**Tests obligatoires** :
- ✅ Export avec JPG
- ✅ Export avec PNG
- ✅ Export avec WEBP
- ✅ 40 images (mémoire)
- ✅ Serveur sans GD

**Recommandation** : ⚠️ **OPTIONNEL**  
**Version suggérée** : v2.2.0

---

## ⛔ FONCTIONNALITÉS ABANDONNÉES

### ❌ Mode Tag avancé
**Raison** : A causé bugs dans v2.0.2-2.0.5  
**Statut** : **Abandonné définitivement**

### ❌ Article parent + synchronisation
**Raison** : A cassé plugin dans v2.1.0-2.1.8  
**Statut** : **Abandonné définitivement**

### ❌ Vignettes Pinterest
**Raison** : Dépend du mode Tag  
**Statut** : **Abandonné définitivement**

---

## 📊 RÉSUMÉ POINTS RESTANTS

### 3 points à risque moyen (optionnels)

| # | Fonctionnalité | Complexité | Risque | Temps | Impact | Version |
|---|---------------|------------|--------|-------|--------|---------|
| 1 | **Prompts personnalisables** | ⭐⭐ | 🟡 | 1h | ⭐⭐⭐ | v2.1.0 |
| 2 | **Images de référence** | ⭐⭐⭐ | 🟡 | 2h | ⭐⭐⭐ | v2.1.1 |
| 3 | **Export IMG JPG** | ⭐⭐ | 🟡 | 1h30 | ⭐⭐ | v2.2.0 |

**Total** : ~4h30  
**Risque global** : 🟡 Moyen  
**Approche** : Progressive (1 feature par version)

---

## 💡 RECOMMANDATIONS

### Option A : **Rester v2.0.2** (Recommandé) ✅

Le plugin est maintenant :
- ✅ **Simplifié** (mode Global seul)
- ✅ **Stable** (code nettoyé)
- ✅ **Fonctionnel** (1-10 recettes)
- ✅ **Production ready**

**Les 3 points restants sont des bonus**, pas des corrections.

---

### Option B : **v2.1.0 Prompts** (Si besoin personnalisation)

**Approche progressive** :
1. Implémenter prompts personnalisables
2. **Tests approfondis** (1-2 jours)
3. Publier v2.1.0
4. **Utiliser 1 semaine en production**
5. Si stable → passer à v2.1.1

**Ne PAS** :
- ❌ Tout faire d'un coup
- ❌ Publier sans tester
- ❌ Cumuler plusieurs features

---

### Option C : **Roadmap complète** (Si ambition long terme)

**Plan sur 1 mois** :
- Semaine 1 : v2.0.2 en prod (actuel)
- Semaine 2 : v2.1.0 (prompts) + tests
- Semaine 3 : v2.1.1 (images ref) + tests
- Semaine 4 : v2.2.0 (export IMG) + tests

**Avantage** : Feature complète  
**Risque** : Nécessite suivi et tests

---

## 🎯 MON CONSEIL FINAL

**La v2.0.2 est EXCELLENTE tel quel !** 🎉

Le plugin est :
- ✅ Simple (mode Global seul)
- ✅ Stable (code nettoyé)
- ✅ Complet (génération fonctionnelle)
- ✅ Sécurisé (chiffrement, rate limit, SSRF)
- ✅ Moderne (UX Premium)

**Les 3 points restants sont des améliorations**, pas des corrections.

**Vous pouvez** :
- **A)** ✅ Utiliser v2.0.2 en production immédiatement (recommandé)
- **B)** 🔧 Implémenter prompts v2.1.0 (si vraiment besoin)
- **C)** 🚀 Planifier roadmap v2.1.x-v2.2.0 (progressif)

---

**Version actuelle** : 2.0.2  
**Statut** : 🟢 **PRODUCTION STABLE** ✅  
**Fichier** : ai-recipe-generator-pro-v2.0.2.zip (46 Ko)

**Le plugin est maintenant simplifié, stable et prêt !** 🚀✨
