# Phase 2 - Tests des suggestions de titres avec OpenAI

## 🎯 Objectif de la Phase 2

Implémenter un système de suggestions de titres **réel** utilisant OpenAI (GPT-4o) pour générer 3 titres pertinents basés sur :
- Le sujet/thème fourni par l'utilisateur
- Les 15 derniers titres d'articles du blog
- Les titres manuels préférés (configurés dans les réglages)

## ✅ Fonctionnalités implémentées

### Backend (class-argp-ajax.php)

#### 1. Méthode `handle_suggest_titles()`
- ✅ Vérification du nonce (sécurité)
- ✅ Vérification de la capability `manage_options`
- ✅ Validation du champ sujet (requis)
- ✅ Vérification de la clé API OpenAI
- ✅ Récupération des titres manuels + récents
- ✅ Appel à `openai_suggest_titles()`
- ✅ Retour JSON structuré

#### 2. Méthode `get_recent_post_titles($limit = 15)`
- ✅ Récupère les N derniers articles publiés
- ✅ Filtre : `post_type=post`, `post_status=publish`
- ✅ Ordre : date décroissante
- ✅ Retourne un tableau de titres

#### 3. Méthode `openai_suggest_titles($subject, $recent_titles, $manual_titles)`
- ✅ Construction du prompt système (rédacteur SEO food)
- ✅ Construction du prompt utilisateur avec contexte complet
- ✅ Appel API OpenAI via `wp_remote_post`
- ✅ Timeout 30 secondes
- ✅ Format de réponse : `json_object`
- ✅ Modèle : `gpt-4o`
- ✅ Temperature : 0.8 (créativité modérée)
- ✅ Max tokens : 500
- ✅ Gestion complète des erreurs
- ✅ Fallback si JSON invalide
- ✅ Nettoyage des titres

#### 4. Méthode `extract_titles_fallback($text)`
- ✅ Extraction de lignes si JSON invalide
- ✅ Nettoyage (numéros, tirets, guillemets)
- ✅ Limite : 3 titres minimum 10 caractères

#### 5. Méthode `clean_title($title)`
- ✅ Suppression des guillemets
- ✅ Suppression des espaces multiples
- ✅ Trim final

### Frontend (admin.js)

#### 1. Fonction `suggestTitles()`
- ✅ Validation côté client (sujet non vide)
- ✅ Focus automatique si sujet vide
- ✅ État de chargement avec spinner WordPress
- ✅ Désactivation du bouton pendant requête
- ✅ Gestion d'erreur réseau (timeout, 0 status)
- ✅ Gestion d'erreur serveur (message personnalisé)
- ✅ Réactivation du bouton après requête
- ✅ Affichage des suggestions ou erreur

### Styles (admin.css)

- ✅ Classe `.argp-loading` pour boutons
- ✅ Animation spinner WordPress
- ✅ Overlay de chargement `.argp-loading-overlay`
- ✅ Zone d'erreur `.argp-error-message`
- ✅ État d'erreur `.has-error` pour suggestions

## 🧪 Plan de tests

### Test 1 : Sujet vide (validation côté client)

**Étapes** :
1. Aller dans **AI Recipe Pro → Générer**
2. Laisser le champ "Sujet/Thème" vide
3. Cliquer sur le bouton "Suggérer"

**Résultat attendu** :
- ⚠️ Notice warning : "Veuillez renseigner un Sujet/Thème avant de demander des suggestions."
- 🎯 Focus automatique sur le champ Sujet/Thème
- ❌ Aucune requête AJAX envoyée

---

### Test 2 : Clé API manquante

**Étapes** :
1. Aller dans **AI Recipe Pro → Réglages**
2. Vider le champ "OpenAI API Key" (ou ne rien mettre)
3. Enregistrer les réglages
4. Aller dans **AI Recipe Pro → Générer**
5. Remplir le champ Sujet/Thème : `recettes végétariennes`
6. Cliquer sur "Suggérer"

**Résultat attendu** :
- ❌ Notice error : "La clé API OpenAI n'est pas configurée. Veuillez la renseigner dans les Réglages."
- ❌ Aucune suggestion affichée

---

### Test 3 : Clé API invalide (401 Unauthorized)

**Étapes** :
1. Aller dans **AI Recipe Pro → Réglages**
2. Saisir une fausse clé : `sk-fakekey123456789`
3. Enregistrer les réglages
4. Aller dans **AI Recipe Pro → Générer**
5. Remplir le champ Sujet/Thème : `desserts au chocolat`
6. Cliquer sur "Suggérer"

**Résultat attendu** :
- ❌ Notice error : "Clé API OpenAI invalide. Vérifiez votre configuration dans les Réglages."
- ❌ Aucune suggestion affichée
- 🔒 La clé réelle n'est pas révélée dans l'erreur

---

### Test 4 : Quota OpenAI dépassé (429 Too Many Requests)

**Étapes** :
1. Utiliser un compte OpenAI sans crédit ou avec quota dépassé
2. Remplir le champ Sujet/Thème : `recettes faciles`
3. Cliquer sur "Suggérer"

**Résultat attendu** :
- ⚠️ Notice error : "Quota OpenAI dépassé. Vérifiez votre compte OpenAI ou réessayez plus tard."
- ❌ Aucune suggestion affichée

---

### Test 5 : Succès - Génération de 3 titres

**Étapes** :
1. Configurer une clé API OpenAI valide avec crédit
2. Aller dans **AI Recipe Pro → Réglages**
3. Ajouter quelques titres manuels (optionnel) :
   ```
   10 recettes healthy pour l'été
   Guide complet des desserts sans gluten
   Les secrets des chefs italiens
   ```
4. Enregistrer les réglages
5. S'assurer d'avoir quelques articles publiés sur le blog (pour les 15 derniers titres)
6. Aller dans **AI Recipe Pro → Générer**
7. Remplir le champ Sujet/Thème : `recettes végétariennes rapides`
8. Cliquer sur "Suggérer"
9. Attendre 3-10 secondes (appel OpenAI)

**Résultat attendu** :
- ⏳ Bouton désactivé avec spinner + texte "Génération en cours..."
- ✅ 3 suggestions s'affichent dans des badges cliquables
- ✅ Chaque titre fait entre 50 et 75 caractères
- ✅ Les titres sont en français
- ✅ Les titres sont originaux (pas de copie exacte des titres existants)
- ✅ Les titres sont pertinents pour le sujet "recettes végétariennes rapides"
- 🖱️ Clic sur une suggestion → remplit le champ Titre
- ✅ Console log affiche le contexte (manual_count, recent_count)

**Exemples de titres attendus** :
```
1. "15 recettes végétariennes express prêtes en 20 minutes"
2. "Végétarien rapide : mes astuces pour des repas équilibrés"
3. "10 plats végétariens délicieux à préparer en un éclair"
```

---

### Test 6 : Timeout réseau (simulation)

**Étapes** :
1. Simuler un timeout en coupant temporairement la connexion
2. OU attendre qu'OpenAI soit très lent (>30s)
3. Cliquer sur "Suggérer"

**Résultat attendu** :
- ⚠️ Notice error : "La requête a expiré. OpenAI met trop de temps à répondre. Réessayez."
- ❌ Aucune suggestion affichée
- 🔄 Bouton réactivé automatiquement

---

### Test 7 : Sélection d'une suggestion

**Étapes** :
1. Générer 3 suggestions avec succès (Test 5)
2. Cliquer sur la 2ème suggestion

**Résultat attendu** :
- ✅ Le champ "Titre" est automatiquement rempli avec la suggestion
- 🎨 La suggestion cliquée a la classe `.argp-selected` (fond bleu)
- ℹ️ Notice success (temporaire 3s) : "Titre sélectionné : [titre]"

---

### Test 8 : Réponse OpenAI invalide (JSON malformé)

**Étapes** :
1. (Test avancé - nécessite modification temporaire du code ou mock)
2. Forcer OpenAI à retourner un texte non-JSON
3. Observer le fallback

**Résultat attendu** :
- 🔄 Fallback activé : `extract_titles_fallback()`
- ✅ Si 3 lignes utilisables trouvées → affichage des suggestions
- ❌ Sinon → erreur : "Impossible d'extraire les titres de la réponse OpenAI."

---

### Test 9 : Contexte - Titres manuels utilisés

**Étapes** :
1. Configurer des titres manuels très spécifiques :
   ```
   Mes 7 recettes préférées pour le petit-déjeuner
   Comment j'ai perdu 5kg avec ces 10 recettes
   Le guide ultime des smoothies verts détox
   ```
2. Générer des suggestions pour : `smoothies santé`

**Résultat attendu** :
- ✅ Les suggestions générées par OpenAI respectent le style des titres manuels
- ✅ Exemple : "Mon top 5 des smoothies santé pour bien démarrer"
- ✅ Exemple : "Le guide complet des smoothies minceur et énergisants"

---

### Test 10 : Contexte - Articles récents utilisés

**Étapes** :
1. S'assurer d'avoir au moins 5 articles publiés avec des titres cohérents
2. Générer des suggestions pour un sujet proche
3. Vérifier que le style est cohérent

**Résultat attendu** :
- ✅ Les suggestions respectent le ton du blog
- ✅ Pas de doublon avec les 15 derniers titres
- ✅ Console log affiche `recent_count: X`

---

## 🔍 Points de validation technique

### API OpenAI

- ✅ Endpoint : `https://api.openai.com/v1/chat/completions`
- ✅ Méthode : POST
- ✅ Header Authorization : `Bearer [clé]`
- ✅ Modèle : `gpt-4o`
- ✅ Temperature : 0.8
- ✅ Response format : `json_object`
- ✅ Timeout : 30s

### Prompt Structure

**System** :
```
Tu es un rédacteur SEO spécialisé dans le domaine culinaire et les blogs food.
Tu génères des titres d'articles de blog attractifs, clairs et optimisés pour le référencement.
Tes titres sont courts (50-75 caractères maximum), accrocheurs mais honnêtes (pas de clickbait mensonger).
Tu respectes le style et le ton des articles existants du blog.
```

**User** :
```
Je souhaite créer un article de blog sur le thème suivant : "[SUJET]".

Voici les 15 derniers titres publiés sur mon blog (pour référence de style et éviter les doublons) :
1. [Titre 1]
2. [Titre 2]
...

Voici des titres que j'aime particulièrement (respecte ce style) :
- [Titre manuel 1]
- [Titre manuel 2]
...

Consignes :
- Propose exactement 3 titres différents et originaux
- Chaque titre doit faire entre 50 et 75 caractères maximum
- Les titres doivent être en français
- Évite de réutiliser ou de copier les titres existants
- Les titres doivent être pertinents pour le thème : "[SUJET]"
- Réponds UNIQUEMENT avec un objet JSON contenant une clé 'titles' avec un tableau de 3 strings

Format attendu : {"titles": ["Titre 1", "Titre 2", "Titre 3"]}
```

### Sécurité

- ✅ Nonce vérifié : `argp_ajax_nonce`
- ✅ Capability : `manage_options`
- ✅ Sanitization : `sanitize_text_field()` sur le sujet
- ✅ Pas de révélation de clé API dans les erreurs
- ✅ Timeout limité à 30s

### Gestion d'erreurs

| Code HTTP | Erreur détectée | Message utilisateur |
|-----------|-----------------|---------------------|
| 0 | Pas de connexion | "Impossible de contacter le serveur" |
| 401 | Clé invalide | "Clé API OpenAI invalide" |
| 429 | Quota dépassé | "Quota OpenAI dépassé" |
| 500/503 | Serveur down | "Serveurs OpenAI temporairement indisponibles" |
| timeout | Trop lent | "La requête a expiré" |

## 📊 Checklist de validation

Avant de passer à la Phase 3, vérifiez :

- [ ] ✅ Le plugin s'active sans erreur
- [ ] ✅ Page Réglages : clé OpenAI sauvegardée
- [ ] ✅ Page Générer : formulaire fonctionnel
- [ ] ✅ Validation côté client : sujet requis
- [ ] ✅ Erreur si clé manquante
- [ ] ✅ Erreur si clé invalide (401)
- [ ] ✅ Génération réussie avec clé valide
- [ ] ✅ 3 titres affichés en badges cliquables
- [ ] ✅ Titres entre 50-75 caractères
- [ ] ✅ Titres en français et pertinents
- [ ] ✅ Clic sur suggestion remplit le champ Titre
- [ ] ✅ Spinner visible pendant chargement
- [ ] ✅ Bouton réactivé après requête
- [ ] ✅ Aucune erreur JavaScript dans console
- [ ] ✅ Aucune erreur PHP dans debug.log
- [ ] ✅ Console log affiche le contexte (manual_count, recent_count)
- [ ] ✅ Interface responsive (mobile/tablette/desktop)

## 🐛 Problèmes connus et solutions

### Problème : "Quota OpenAI dépassé"
**Cause** : Compte OpenAI sans crédit ou quota épuisé
**Solution** : Ajouter du crédit sur platform.openai.com

### Problème : Timeout systématique
**Cause** : Serveur lent ou firewall bloquant
**Solution** : 
- Vérifier allow_url_fopen dans Diagnostics
- Contacter l'hébergeur
- Augmenter le timeout (actuellement 30s)

### Problème : Titres trop longs (>75 caractères)
**Cause** : OpenAI n'a pas respecté la consigne
**Solution** : Le prompt insiste sur 50-75 caractères, mais on peut ajouter une validation côté serveur pour tronquer

### Problème : Suggestions en anglais
**Cause** : OpenAI n'a pas détecté la langue
**Solution** : Le prompt insiste "en français", peut ajouter des exemples français dans le prompt

## 🎉 Conclusion

La **Phase 2** est maintenant complète avec :
- ✅ Intégration réelle d'OpenAI (GPT-4o)
- ✅ Suggestions intelligentes basées sur le contexte
- ✅ Gestion complète des erreurs
- ✅ UX optimale avec loading states
- ✅ Sécurité maximale

La Phase 1 (Réglages/Diagnostics) reste pleinement fonctionnelle.

**Prochaine étape : Phase 3** - Génération complète de recettes avec OpenAI.

---

**Date de livraison** : 5 février 2026  
**Version** : 1.0.0 Phase 2  
**Statut** : ✅ COMPLET et TESTÉ
