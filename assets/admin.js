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

			// TODO Phase 3-5: Soumettre le formulaire de génération
			// $('#argp-generate-form').on('submit', this.handleGenerateSubmit);
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
			const subject = $('#argp_subject').val();
			const originalText = $button.text();

			// Désactiver le bouton
			$button.prop('disabled', true).text(argpAdmin.strings.generating);

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
					} else {
						ARGPAdmin.showError($suggestionsList, response.data.message || argpAdmin.strings.error);
					}
				},
				error: function(xhr, status, error) {
					ARGPAdmin.showError($suggestionsList, 'Erreur AJAX : ' + error);
				},
				complete: function() {
					// Réactiver le bouton
					$button.prop('disabled', false).text(originalText);
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

			// Auto-dismiss après 3 secondes
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 3000);
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

		/**
		 * TODO Phase 3-5: Handler pour soumettre le formulaire de génération
		 *
		 * Cette fonction devra:
		 * - Valider les champs
		 * - Appeler l'endpoint AJAX argp_generate_recipes
		 * - Afficher une barre de progression
		 * - Afficher les résultats dans #argp-results-container
		 * - Permettre de prévisualiser et publier les articles
		 */
		// handleGenerateSubmit: function(e) { }
	};

	/**
	 * Initialisation au chargement du DOM
	 */
	$(document).ready(function() {
		ARGPAdmin.init();
	});

})(jQuery);
