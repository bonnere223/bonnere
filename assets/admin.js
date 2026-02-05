/**
 * Scripts Admin pour AI Recipe Generator Pro
 *
 * @package AI_Recipe_Generator_Pro
 */

(function($) {
	'use strict';

	/**
	 * Objet principal ARGP Admin
	 */
	const ARGPAdmin = {
		/**
		 * Job ID actuel
		 */
		currentJobId: null,

		/**
		 * Interval pour le tick
		 */
		tickInterval: null,

		/**
		 * Initialisation
		 */
		init: function() {
			this.bindEvents();
			this.initToggleVisibility();
		},

		/**
		 * Lie les événements
		 */
		bindEvents: function() {
			// Bouton "Lancer le test" (diagnostics)
			$('#argp-run-diagnostics').on('click', this.runDiagnostics);

			// Bouton "Suggérer" (titres)
			$('#argp-suggest-title').on('click', this.suggestTitles);

			// Clic sur une suggestion
			$(document).on('click', '.argp-suggestion-item', this.selectSuggestion);

			// PHASE 3: Soumettre le formulaire de génération
			$('#argp-generate-form').on('submit', this.handleGenerateSubmit);

			// PHASE 3: Annuler la génération
			$('#argp-cancel-generation').on('click', this.handleCancelGeneration);
		},

		/**
		 * Initialise les boutons "Afficher/Masquer" pour les clés API
		 */
		initToggleVisibility: function() {
			$('.argp-toggle-visibility').on('click', function(e) {
				e.preventDefault();
				const targetId = $(this).data('target');
				const $input = $('#' + targetId);
				
				if ($input.attr('type') === 'password') {
					$input.attr('type', 'text');
					$(this).text(argpAdmin.strings.hide || 'Masquer');
				} else {
					$input.attr('type', 'password');
					$(this).text(argpAdmin.strings.show || 'Afficher');
				}
			});
		},

		/**
		 * Lance les diagnostics système (AJAX)
		 */
		runDiagnostics: function(e) {
			e.preventDefault();

			const $button = $(this);
			const $resultsContainer = $('#argp-diagnostics-results');
			const originalText = $button.text();

			// Désactiver le bouton et afficher un loader
			$button.prop('disabled', true).text(argpAdmin.strings.testing);

			// Requête AJAX
			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_run_diagnostics',
					nonce: argpAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data.results) {
						ARGPAdmin.displayDiagnosticsResults(response.data.results);
					} else {
						ARGPAdmin.showError($resultsContainer, response.data.message || argpAdmin.strings.error);
					}
				},
				error: function(xhr, status, error) {
					ARGPAdmin.showError($resultsContainer, 'Erreur AJAX : ' + error);
				},
				complete: function() {
					// Réactiver le bouton
					$button.prop('disabled', false).text(originalText);
				}
			});
		},

		/**
		 * Affiche les résultats des diagnostics
		 *
		 * @param {Object} results Résultats des tests
		 */
		displayDiagnosticsResults: function(results) {
			const $container = $('#argp-diagnostics-results');
			let html = '<div class="argp-diagnostics-badges">';

			// Parcourir chaque résultat
			$.each(results, function(key, result) {
				const statusClass = 'argp-badge-' + result.status;
				const icon = result.status === 'success' ? '✓' : (result.status === 'error' ? '✗' : '⚠');

				html += '<div class="argp-diagnostic-item">';
				html += '<div class="argp-badge ' + statusClass + '">';
				html += '<span class="argp-badge-icon">' + icon + '</span>';
				html += '<span class="argp-badge-label">' + result.label + '</span>';
				html += '</div>';
				html += '<div class="argp-diagnostic-message">' + result.message + '</div>';
				
				// Afficher les détails si disponibles
				if (result.details) {
					html += '<div class="argp-diagnostic-details">';
					html += '<small>' + JSON.stringify(result.details, null, 2) + '</small>';
					html += '</div>';
				}
				
				html += '</div>';
			});

			html += '</div>';

			$container.html(html).fadeIn();
		},

		/**
		 * Suggère des titres (AJAX)
		 */
		suggestTitles: function(e) {
			e.preventDefault();

			const $button = $(this);
			const $suggestionsContainer = $('#argp-suggestions-container');
			const $suggestionsList = $('#argp-suggestions-list');
			const subject = $('#argp_subject').val().trim();
			const originalText = $button.text();

			// Validation : vérifier que le sujet n'est pas vide
			if (!subject) {
				ARGPAdmin.showNotice('warning', 'Veuillez renseigner un Sujet/Thème avant de demander des suggestions.');
				$('#argp_subject').focus();
				return;
			}

			// Cacher les anciennes suggestions
			$suggestionsContainer.hide();
			$suggestionsList.empty();

			// Désactiver le bouton et afficher l'état de chargement
			$button.prop('disabled', true).addClass('argp-loading');
			$button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + argpAdmin.strings.generating);

			// Requête AJAX
			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_suggest_titles',
					nonce: argpAdmin.nonce,
					subject: subject
				},
				success: function(response) {
					if (response.success && response.data.suggestions) {
						ARGPAdmin.displaySuggestions(response.data.suggestions);
						
						// Message de succès discret
						if (response.data.context) {
							const ctx = response.data.context;
							console.log('Suggestions générées avec:', ctx);
						}
					} else {
						// Erreur retournée par le serveur
						const errorMsg = response.data && response.data.message 
							? response.data.message 
							: 'Erreur lors de la génération des suggestions.';
						ARGPAdmin.showNotice('error', errorMsg);
						$suggestionsContainer.hide();
					}
				},
				error: function(xhr, status, error) {
					// Erreur réseau ou timeout
					let errorMsg = 'Erreur de connexion : ' + error;
					
					if (status === 'timeout') {
						errorMsg = 'La requête a expiré. OpenAI met trop de temps à répondre. Réessayez.';
					} else if (xhr.status === 0) {
						errorMsg = 'Impossible de contacter le serveur. Vérifiez votre connexion.';
					}
					
					ARGPAdmin.showNotice('error', errorMsg);
					$suggestionsContainer.hide();
				},
				complete: function() {
					// Réactiver le bouton et retirer l'état de chargement
					$button.prop('disabled', false).removeClass('argp-loading');
					$button.html(originalText);
				}
			});
		},

		/**
		 * Affiche les suggestions de titres
		 *
		 * @param {Array} suggestions Liste des suggestions
		 */
		displaySuggestions: function(suggestions) {
			const $container = $('#argp-suggestions-container');
			const $list = $('#argp-suggestions-list');

			let html = '';

			suggestions.forEach(function(suggestion, index) {
				html += '<div class="argp-suggestion-item" data-title="' + ARGPAdmin.escapeHtml(suggestion) + '">';
				html += '<span class="argp-suggestion-number">' + (index + 1) + '</span>';
				html += '<span class="argp-suggestion-text">' + ARGPAdmin.escapeHtml(suggestion) + '</span>';
				html += '</div>';
			});

			$list.html(html);
			$container.fadeIn();
		},

		/**
		 * Sélectionne une suggestion et remplit le champ titre
		 */
		selectSuggestion: function() {
			const title = $(this).data('title');
			$('#argp_title').val(title);

			// Ajouter une classe "selected" temporaire
			$('.argp-suggestion-item').removeClass('argp-selected');
			$(this).addClass('argp-selected');

			// Feedback visuel
			ARGPAdmin.showNotice('success', 'Titre sélectionné : ' + title);
		},

		/* ========================================
		   PHASE 3: GÉNÉRATION COMPLÈTE
		   ======================================== */

		/**
		 * Handler pour soumettre le formulaire de génération
		 */
		handleGenerateSubmit: function(e) {
			e.preventDefault();

			// Récupérer les valeurs du formulaire
			const subject = $('#argp_subject').val().trim();
			const count = $('#argp_count').val();
			const title = $('#argp_title').val().trim();
			const status = $('#argp_status').val();

			// Validation
			if (!subject) {
				ARGPAdmin.showNotice('warning', 'Le champ Sujet/Thème est requis.');
				$('#argp_subject').focus();
				return;
			}

			// Désactiver le formulaire
			$('#argp-generate-form').hide();

			// Afficher la zone de progression
			$('#argp-progress-container').show();
			$('#argp-results-container').hide();

			// Réinitialiser la progression
			ARGPAdmin.updateProgress(0, 'Initialisation...');
			$('#argp-progress-logs').empty();

			// Démarrer la génération
			ARGPAdmin.startGeneration(subject, count, title, status);
		},

		/**
		 * Démarre la génération (appel AJAX start_generation)
		 */
		startGeneration: function(subject, count, title, status) {
			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_start_generation',
					nonce: argpAdmin.nonce,
					subject: subject,
					count: count,
					title: title,
					status: status
				},
				success: function(response) {
					if (response.success && response.data.job_id) {
						ARGPAdmin.currentJobId = response.data.job_id;
						ARGPAdmin.addLog('✓ Génération démarrée', 'success');
						
						// Démarrer le tick loop
						ARGPAdmin.startTickLoop();
					} else {
						const errorMsg = response.data && response.data.message 
							? response.data.message 
							: 'Erreur lors du démarrage de la génération.';
						ARGPAdmin.handleGenerationError(errorMsg);
					}
				},
				error: function(xhr, status, error) {
					ARGPAdmin.handleGenerationError('Erreur réseau : ' + error);
				}
			});
		},

		/**
		 * Démarre le tick loop (polling)
		 */
		startTickLoop: function() {
			// Tick toutes les 2 secondes
			ARGPAdmin.tickInterval = setInterval(function() {
				ARGPAdmin.tick();
			}, 2000);

			// Faire le premier tick immédiatement
			ARGPAdmin.tick();
		},

		/**
		 * Exécute un tick (avance le job d'une étape)
		 */
		tick: function() {
			if (!ARGPAdmin.currentJobId) {
				ARGPAdmin.stopTickLoop();
				return;
			}

			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_generation_tick',
					nonce: argpAdmin.nonce,
					job_id: ARGPAdmin.currentJobId
				},
				success: function(response) {
					if (response.success && response.data) {
						const data = response.data;

						// Mettre à jour la progression
						ARGPAdmin.updateProgress(data.progress, data.message);
						ARGPAdmin.addLog(data.message, data.error ? 'error' : 'info');

						// Si le job est terminé
						if (data.done) {
							ARGPAdmin.stopTickLoop();
							ARGPAdmin.handleGenerationComplete(data);
						}
					} else {
						const errorMsg = response.data && response.data.message 
							? response.data.message 
							: 'Erreur lors du tick.';
						ARGPAdmin.stopTickLoop();
						ARGPAdmin.handleGenerationError(errorMsg);
					}
				},
				error: function(xhr, status, error) {
					ARGPAdmin.stopTickLoop();
					ARGPAdmin.handleGenerationError('Erreur réseau lors du tick : ' + error);
				}
			});
		},

		/**
		 * Arrête le tick loop
		 */
		stopTickLoop: function() {
			if (ARGPAdmin.tickInterval) {
				clearInterval(ARGPAdmin.tickInterval);
				ARGPAdmin.tickInterval = null;
			}
		},

		/**
		 * Met à jour la barre de progression
		 *
		 * @param {number} percent Pourcentage (0-100)
		 * @param {string} message Message de statut
		 */
		updateProgress: function(percent, message) {
			$('#argp-progress-bar-fill').css('width', percent + '%');
			$('#argp-progress-percent').text(Math.round(percent) + '%');
			$('#argp-progress-status').text(message);
		},

		/**
		 * Ajoute un log
		 *
		 * @param {string} message Message du log
		 * @param {string} type    Type : success, error, info
		 */
		addLog: function(message, type) {
			type = type || 'info';
			const timestamp = new Date().toLocaleTimeString();
			const iconClass = type === 'success' ? 'dashicons-yes' : (type === 'error' ? 'dashicons-no' : 'dashicons-info');
			
			const $log = $('<div class="argp-log-entry argp-log-' + type + '">' +
				'<span class="dashicons ' + iconClass + '"></span>' +
				'<span class="argp-log-time">' + timestamp + '</span>' +
				'<span class="argp-log-message">' + ARGPAdmin.escapeHtml(message) + '</span>' +
				'</div>');

			$('#argp-progress-logs').append($log);

			// Scroll automatique vers le bas
			const $logs = $('#argp-progress-logs');
			$logs.scrollTop($logs[0].scrollHeight);
		},

		/**
		 * Gère la fin de la génération (succès)
		 *
		 * @param {Object} data Données de résultat
		 */
		handleGenerationComplete: function(data) {
			ARGPAdmin.addLog('✓ Génération terminée avec succès !', 'success');

			// Masquer la zone de progression
			$('#argp-progress-container').hide();

			// Afficher la zone de résultats
			$('#argp-results-container').show();

			let html = '<div class="notice notice-success inline"><p><strong>Article créé avec succès !</strong></p></div>';

			if (data.post_id) {
				html += '<p><strong>ID de l\'article :</strong> ' + data.post_id + '</p>';
			}

			if (data.edit_link) {
				html += '<p class="argp-result-actions">';
				html += '<a href="' + data.edit_link + '" class="button button-primary button-large">';
				html += '<span class="dashicons dashicons-edit" style="margin-top: 4px;"></span> ';
				html += 'Modifier l\'article';
				html += '</a>';
				html += '</p>';
			}

			// Afficher les erreurs éventuelles
			if (data.errors && data.errors.length > 0) {
				html += '<div class="notice notice-warning inline" style="margin-top: 20px;">';
				html += '<p><strong>Attention :</strong> Certaines étapes ont rencontré des problèmes :</p>';
				html += '<ul style="margin-left: 20px;">';
				data.errors.forEach(function(error) {
					html += '<li>' + ARGPAdmin.escapeHtml(error) + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			}

			// Bouton pour recommencer
			html += '<p class="argp-result-actions" style="margin-top: 20px;">';
			html += '<button type="button" id="argp-generate-another" class="button button-secondary">';
			html += '<span class="dashicons dashicons-plus" style="margin-top: 4px;"></span> ';
			html += 'Générer un autre article';
			html += '</button>';
			html += '</p>';

			$('#argp-results-content').html(html);

			// Bind du bouton "Générer un autre"
			$('#argp-generate-another').on('click', function() {
				location.reload();
			});

			// Réinitialiser
			ARGPAdmin.currentJobId = null;
		},

		/**
		 * Gère une erreur de génération
		 *
		 * @param {string} errorMessage Message d'erreur
		 */
		handleGenerationError: function(errorMessage) {
			ARGPAdmin.addLog('✗ Erreur : ' + errorMessage, 'error');
			ARGPAdmin.updateProgress(100, 'Erreur lors de la génération');

			// Afficher le bouton pour réessayer
			const $cancelBtn = $('#argp-cancel-generation');
			$cancelBtn.html('<span class="dashicons dashicons-redo" style="margin-top: 4px;"></span> Réessayer');
			$cancelBtn.off('click').on('click', function() {
				location.reload();
			});

			ARGPAdmin.showNotice('error', errorMessage);
		},

		/**
		 * Handler pour annuler la génération
		 */
		handleCancelGeneration: function(e) {
			e.preventDefault();

			if (!ARGPAdmin.currentJobId) {
				return;
			}

			if (!confirm('Êtes-vous sûr de vouloir annuler la génération en cours ?')) {
				return;
			}

			// Arrêter le tick loop
			ARGPAdmin.stopTickLoop();

			// Appel AJAX pour annuler
			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_cancel_generation',
					nonce: argpAdmin.nonce,
					job_id: ARGPAdmin.currentJobId
				},
				success: function(response) {
					ARGPAdmin.addLog('Génération annulée par l\'utilisateur', 'info');
					ARGPAdmin.currentJobId = null;

					// Afficher le bouton pour recommencer
					$('#argp-cancel-generation').hide();
					ARGPAdmin.showNotice('info', 'Génération annulée. Rechargez la page pour recommencer.');
				},
				error: function() {
					ARGPAdmin.showNotice('warning', 'Impossible d\'annuler la génération.');
				}
			});
		},

		/* ========================================
		   UTILITAIRES
		   ======================================== */

		/**
		 * Affiche une erreur
		 *
		 * @param {jQuery} $container Conteneur
		 * @param {string} message    Message d'erreur
		 */
		showError: function($container, message) {
			const html = '<div class="notice notice-error inline"><p>' + message + '</p></div>';
			$container.html(html).fadeIn();
		},

		/**
		 * Affiche une notice temporaire
		 *
		 * @param {string} type    Type (success, error, warning, info)
		 * @param {string} message Message
		 */
		showNotice: function(type, message) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wrap').prepend($notice);

			// Auto-dismiss après 5 secondes
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Échappe le HTML pour éviter les injections XSS
		 *
		 * @param {string} text Texte à échapper
		 * @return {string} Texte échappé
		 */
		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	/**
	 * Initialisation au chargement du DOM
	 */
	$(document).ready(function() {
		ARGPAdmin.init();
	});

})(jQuery);
