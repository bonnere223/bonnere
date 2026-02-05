/**
 * Scripts Admin pour AI Recipe Generator Pro - UX Premium
 *
 * @package AI_Recipe_Generator_Pro
 */

(function($) {
	'use strict';

	const ARGPAdmin = {
		currentJobId: null,
		tickInterval: null,
		detectedCount: 1,

		init: function() {
			this.bindEvents();
			this.initToggleVisibility();
			this.checkForExistingJob();
			this.autoSuggestTitle(); // Suggestion auto au chargement
			this.initCollapsibles();
			this.updateEstimation(); // Estimation initiale
		},

		bindEvents: function() {
			// Diagnostics
			$('#argp-run-diagnostics').on('click', this.runDiagnostics);

			// Suggestions
			$('#argp-suggest-title').on('click', this.suggestTitles);
			$('#argp-new-theme').on('click', this.suggestNewTheme);
			$(document).on('click', '.argp-suggestion-item, .argp-theme-item', this.selectSuggestion);

			// Génération
			$('#argp-generate-form').on('submit', this.handleGenerateSubmit);
			$('#argp-cancel-generation').on('click', this.handleCancelGeneration);

			// Détection nombre recettes dans titre
			$('#argp_title').on('input', this.detectRecipeCount);
			$('#argp_subject').on('input', this.updateEstimation.bind(this));

			// Test API
			$('.argp-test-api').on('click', this.testAPI);

			// Upload ZIP
			$('#argp-upload-zip').on('click', function() {
				$('#argp-zip-input').click();
			});
			$('#argp-zip-input').on('change', this.handleZipUpload);
		},

		initCollapsibles: function() {
			$('.argp-collapsible').on('click', function() {
				const target = $(this).data('target');
				$('#' + target).slideToggle();
				$(this).find('.argp-toggle-icon').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
			});
		},

		initToggleVisibility: function() {
			$('.argp-toggle-visibility').on('click', function(e) {
				e.preventDefault();
				const targetId = $(this).data('target');
				const $input = $('#' + targetId);
				
				if ($input.attr('type') === 'password') {
					$input.attr('type', 'text');
					$(this).text('Masquer');
				} else {
					$input.attr('type', 'password');
					$(this).text('Afficher');
				}
			});
		},

		/* ========================================
		   DÉTECTION AUTO NOMBRE RECETTES
		   ======================================== */

		detectRecipeCount: function() {
			const title = $('#argp_title').val();
			
			// Regex pour détecter nombres
			const matches = title.match(/(\d+)\s*(recettes?|plats?|desserts?|entrées?)/i);
			
			if (matches) {
				let detectedNumber = parseInt(matches[1], 10);
				let count = Math.max(1, Math.min(40, detectedNumber)); // Clamp 1-40
				
				ARGPAdmin.detectedCount = count;
				$('#argp_count').val(count);
				
				// Afficher le nombre détecté ET clamped si différent
				if (detectedNumber > 40) {
					$('#argp-detected-count-text').html(count + ' recette(s) détectée(s) <em>(limité à 40 max)</em>');
				} else {
					$('#argp-detected-count-text').text(count + ' recette(s) détectée(s)');
				}
				$('#argp-detected-count').fadeIn();
				
				// Mettre à jour estimation
				ARGPAdmin.updateEstimation();
				
				// Générer les champs d'upload images
				ARGPAdmin.generateImageUploadFields(count);
			} else {
				ARGPAdmin.detectedCount = 1;
				$('#argp_count').val(1);
				$('#argp-detected-count').fadeOut();
				ARGPAdmin.updateEstimation();
				ARGPAdmin.generateImageUploadFields(1);
			}
		},

		generateImageUploadFields: function(count) {
			const $container = $('#argp-reference-images-container');
			let html = '';

			for (let i = 1; i <= count; i++) {
				html += '<div class="argp-image-upload-field">';
				html += '<label>Recette ' + i + '</label>';
				html += '<input type="file" name="argp_ref_image_' + i + '" accept="image/*" class="argp-image-input" />';
				html += '</div>';
			}

			$container.html(html);
		},

		handleZipUpload: function() {
			const file = this.files[0];
			if (file) {
				ARGPAdmin.showNotice('info', 'ZIP uploadé : ' + file.name + '. Les images seront extraites lors de la génération.');
			}
		},

		/* ========================================
		   ESTIMATION TEMPS RÉEL
		   ======================================== */

		updateEstimation: function() {
			const count = ARGPAdmin.detectedCount || 1;
			const hasImages = true; // Toujours avec images pour l'instant

			// Calcul coût
			const costOpenAI = count * (argpAdmin.costs.openai_per_recipe || 0.03);
			const costReplicate = hasImages ? count * (argpAdmin.costs.replicate_per_image || 0.04) : 0;
			const totalCost = costOpenAI + costReplicate;

			// Calcul temps (estimations approximatives)
			const timeOpenAI = 15; // 15 secondes
			const timePost = 1; // 1 seconde
			const timePerImage = 30; // 30 secondes par image
			const totalTime = timeOpenAI + timePost + (hasImages ? count * timePerImage : 0);
			const timeMinutes = Math.ceil(totalTime / 60);

			// Mise à jour UI
			$('#argp-est-recipes').text(count);
			$('#argp-est-cost').text('$' + totalCost.toFixed(2));
			$('#argp-est-time').text(timeMinutes + ' min');
		},

		/* ========================================
		   SUGGESTION AUTO AU CHARGEMENT
		   ======================================== */

		autoSuggestTitle: function() {
			// Vérifier qu'on est sur la page Générer
			if ($('#argp-generate-form').length === 0) {
				return;
			}

			// Si le titre est déjà rempli, ne pas écraser
			if ($('#argp_title').val().trim()) {
				ARGPAdmin.detectRecipeCount();
				return;
			}

			const subject = $('#argp_subject').val().trim() || 'recettes';

			// Activer loading state
			ARGPAdmin.setTitleLoading(true);

			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_auto_suggest_title',
					nonce: argpAdmin.nonce,
					subject: subject
				},
				success: function(response) {
					if (response.success && response.data.title) {
						$('#argp_title').val(response.data.title);
						ARGPAdmin.detectRecipeCount();
					}
				},
				complete: function() {
					ARGPAdmin.setTitleLoading(false);
				}
			});
		},

		/* ========================================
		   LOADING STATE DU TITRE
		   ======================================== */

		setTitleLoading: function(loading) {
			const $input = $('#argp_title');
			const $loader = $('.argp-title-loading-bar');

			if (loading) {
				$input.prop('readonly', true).addClass('argp-title-loading');
				$loader.fadeIn();
			} else {
				$input.prop('readonly', false).removeClass('argp-title-loading');
				$loader.fadeOut();
			}
		},

		/* ========================================
		   SUGGESTIONS CLASSIQUES
		   ======================================== */

		suggestTitles: function(e) {
			e.preventDefault();

			const $button = $(this);
			const subject = $('#argp_subject').val().trim();
			const originalText = $button.html();

			if (!subject) {
				ARGPAdmin.showNotice('warning', 'Veuillez renseigner un Sujet/Thème.');
				$('#argp_subject').focus();
				return;
			}

			ARGPAdmin.setTitleLoading(true);
			$('#argp-suggestions-container').hide();
			$button.prop('disabled', true);

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
						ARGPAdmin.displaySuggestions(response.data.suggestions, 'suggestions');
					} else {
						ARGPAdmin.showNotice('error', response.data.message || 'Erreur');
					}
				},
				error: function() {
					ARGPAdmin.showNotice('error', 'Erreur réseau');
				},
				complete: function() {
					ARGPAdmin.setTitleLoading(false);
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		/* ========================================
		   NOUVEAU THÈME
		   ======================================== */

		suggestNewTheme: function(e) {
			e.preventDefault();

			const $button = $(this);
			const originalText = $button.html();

			ARGPAdmin.setTitleLoading(true);
			$('#argp-new-themes-container').hide();
			$button.prop('disabled', true);

			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_new_theme_suggest',
					nonce: argpAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data.themes) {
						ARGPAdmin.displaySuggestions(response.data.themes, 'themes');
					} else {
						ARGPAdmin.showNotice('error', response.data.message || 'Erreur');
					}
				},
				error: function() {
					ARGPAdmin.showNotice('error', 'Erreur réseau');
				},
				complete: function() {
					ARGPAdmin.setTitleLoading(false);
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		displaySuggestions: function(items, type) {
			const isTheme = type === 'themes';
			const $container = isTheme ? $('#argp-new-themes-container') : $('#argp-suggestions-container');
			const $list = isTheme ? $('#argp-new-themes-list') : $('#argp-suggestions-list');
			const itemClass = isTheme ? 'argp-theme-item' : 'argp-suggestion-item';

			let html = '';
			items.forEach(function(item, index) {
				html += '<button type="button" class="' + itemClass + '" data-title="' + ARGPAdmin.escapeHtml(item) + '">';
				html += '<span class="argp-item-number">' + (index + 1) + '</span>';
				html += '<span class="argp-item-text">' + ARGPAdmin.escapeHtml(item) + '</span>';
				html += '</button>';
			});

			$list.html(html);
			$container.fadeIn();
		},

		selectSuggestion: function() {
			const title = $(this).data('title');
			$('#argp_title').val(title);
			$('.argp-suggestion-item, .argp-theme-item').removeClass('argp-selected');
			$(this).addClass('argp-selected');
			ARGPAdmin.detectRecipeCount();
			ARGPAdmin.showNotice('success', 'Titre sélectionné');
		},

		/* ========================================
		   TEST API
		   ======================================== */

		testAPI: function() {
			const $button = $(this);
			const apiName = $button.data('api');
			const $result = $('#argp-' + apiName + '-test-result');
			const originalText = $button.text();

			$button.prop('disabled', true).text('Test...');
			$result.hide();

			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_test_api',
					nonce: argpAdmin.nonce,
					api: apiName
				},
				success: function(response) {
					if (response.success) {
						$result.html('<span class="argp-api-success">' + response.data.message + '</span>').fadeIn();
					} else {
						$result.html('<span class="argp-api-error">' + response.data.message + '</span>').fadeIn();
					}
				},
				error: function() {
					$result.html('<span class="argp-api-error">⚠️ Erreur réseau</span>').fadeIn();
				},
				complete: function() {
					$button.prop('disabled', false).text(originalText);
					setTimeout(function() {
						$result.fadeOut();
					}, 5000);
				}
			});
		},

		/* ========================================
		   DIAGNOSTICS (CONSERVÉ)
		   ======================================== */

		runDiagnostics: function(e) {
			e.preventDefault();
			const $button = $(this);
			const originalText = $button.text();
			$button.prop('disabled', true).text('Test en cours...');

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
					}
				},
				complete: function() {
					$button.prop('disabled', false).text(originalText);
				}
			});
		},

		displayDiagnosticsResults: function(results) {
			const $container = $('#argp-diagnostics-results');
			let html = '<div class="argp-diagnostics-badges">';

			$.each(results, function(key, result) {
				const statusClass = 'argp-badge-' + result.status;
				const icon = result.status === 'success' ? '✓' : (result.status === 'error' ? '✗' : '⚠');

				html += '<div class="argp-diagnostic-item">';
				html += '<div class="argp-badge ' + statusClass + '">';
				html += '<span class="argp-badge-icon">' + icon + '</span>';
				html += '<span class="argp-badge-label">' + result.label + '</span>';
				html += '</div>';
				html += '<div class="argp-diagnostic-message">' + result.message + '</div>';
				html += '</div>';
			});

			html += '</div>';
			$container.html(html).fadeIn();
		},

		/* ========================================
		   GÉNÉRATION (CONSERVÉ + AMÉLIORATIONS)
		   ======================================== */

		handleGenerateSubmit: function(e) {
			e.preventDefault();

			let subject = $('#argp_subject').val().trim();
			const count = $('#argp_count').val();
			const title = $('#argp_title').val().trim();
			const status = $('#argp_status').val();

			// Si titre rempli mais pas sujet, utiliser le titre comme sujet
			if (!subject && title) {
				subject = title;
				$('#argp_subject').val(title);
			}

			// Validation : au moins l'un des deux doit être rempli
			if (!subject && !title) {
				ARGPAdmin.showNotice('warning', 'Veuillez renseigner au moins un titre ou un thème.');
				$('#argp_title').focus();
				return;
			}

			$('#argp-generate-form').parent().hide();
			$('#argp-progress-container').show();
			$('#argp-results-container').hide();

			ARGPAdmin.updateProgress(0, 'Initialisation...');
			$('#argp-progress-logs').empty();

			ARGPAdmin.startGeneration(subject, count, title, status);
		},

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
						ARGPAdmin.startTickLoop();
					} else {
						ARGPAdmin.handleGenerationError(response.data.message || 'Erreur');
					}
				},
				error: function(xhr, status, error) {
					ARGPAdmin.handleGenerationError('Erreur réseau : ' + error);
				}
			});
		},

		startTickLoop: function() {
			ARGPAdmin.tickInterval = setInterval(function() {
				ARGPAdmin.tick();
			}, 2000);
			ARGPAdmin.tick();
		},

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
						ARGPAdmin.updateProgress(data.progress, data.message);
						ARGPAdmin.addLog(data.message, data.error ? 'error' : 'info');

						if (data.done) {
							ARGPAdmin.stopTickLoop();
							ARGPAdmin.handleGenerationComplete(data);
						}
					} else {
						ARGPAdmin.stopTickLoop();
						ARGPAdmin.handleGenerationError(response.data.message || 'Erreur');
					}
				},
				error: function() {
					ARGPAdmin.stopTickLoop();
					ARGPAdmin.handleGenerationError('Erreur réseau');
				}
			});
		},

		stopTickLoop: function() {
			if (ARGPAdmin.tickInterval) {
				clearInterval(ARGPAdmin.tickInterval);
				ARGPAdmin.tickInterval = null;
			}
		},

		updateProgress: function(percent, message) {
			percent = Math.max(0, Math.min(100, percent));
			$('#argp-progress-bar-fill').css('width', percent + '%');
			$('#argp-progress-percent').text(Math.round(percent) + '%');
			$('#argp-progress-status').text(ARGPAdmin.escapeHtml(message));
		},

		addLog: function(message, type) {
			type = type || 'info';
			const timestamp = new Date().toLocaleTimeString();
			const iconClass = type === 'success' ? 'dashicons-yes' : (type === 'error' ? 'dashicons-no' : 'dashicons-info');
			
			const $log = $('<div class="argp-log-entry argp-log-' + type + '">' +
				'<span class="dashicons ' + iconClass + '"></span>' +
				'<span class="argp-log-time">' + ARGPAdmin.escapeHtml(timestamp) + '</span>' +
				'<span class="argp-log-message">' + ARGPAdmin.escapeHtml(message) + '</span>' +
				'</div>');

			$('#argp-progress-logs').append($log).scrollTop($('#argp-progress-logs')[0].scrollHeight);
		},

		handleGenerationComplete: function(data) {
			ARGPAdmin.addLog('✓ Génération terminée !', 'success');
			$('#argp-progress-container').hide();
			$('#argp-results-container').show();

			let html = '<div class="notice notice-success inline"><p><strong>Article(s) créé(s) avec succès !</strong></p></div>';

			if (data.post_id) {
				html += '<p><strong>ID article(s) :</strong> ' + data.post_id + '</p>';
			}

			if (data.edit_link) {
				html += '<p class="argp-result-actions">';
				html += '<a href="' + data.edit_link + '" class="button button-primary button-large">';
				html += '<span class="dashicons dashicons-edit"></span> Modifier l\'article';
				html += '</a></p>';
			}

			// Boutons exports ZIP/TXT
			if (data.post_id) {
				const postId = Array.isArray(data.post_id) ? data.post_id[0] : data.post_id;
				const exportZipUrl = ajaxurl.replace('admin-ajax.php', 'admin-post.php') + 
					'?action=argp_export_zip&post_id=' + postId + '&_wpnonce=' + 
					(data.export_nonce || '');
				const exportTxtUrl = ajaxurl.replace('admin-ajax.php', 'admin-post.php') + 
					'?action=argp_export_txt&post_id=' + postId + '&_wpnonce=' + 
					(data.export_nonce || '');

				html += '<div class="argp-export-actions" style="margin-top: 20px; padding: 20px; background: #f6f7f7; border-radius: 8px;">';
				html += '<h3 style="margin: 0 0 15px 0; font-size: 16px;">📦 Exporter les recettes</h3>';
				html += '<p class="argp-result-actions">';
				html += '<a href="' + exportZipUrl + '" class="button button-secondary">';
				html += '<span class="dashicons dashicons-download"></span> Télécharger ZIP des images</a> ';
				html += '<a href="' + exportTxtUrl + '" class="button button-secondary">';
				html += '<span class="dashicons dashicons-media-text"></span> Télécharger TXT des recettes</a>';
				html += '</p>';
				html += '</div>';
			}

			if (data.errors && data.errors.length > 0) {
				html += '<div class="notice notice-warning inline" style="margin-top: 20px;"><p><strong>Attention :</strong> Certaines étapes ont rencontré des problèmes :</p><ul>';
				data.errors.forEach(function(error) {
					html += '<li>' + ARGPAdmin.escapeHtml(error) + '</li>';
				});
				html += '</ul></div>';
			}

			html += '<p class="argp-result-actions" style="margin-top: 20px;"><button type="button" id="argp-generate-another" class="button button-secondary">';
			html += '<span class="dashicons dashicons-plus"></span> Générer un autre article</button></p>';

			$('#argp-results-content').html(html);

			$('#argp-generate-another').on('click', function() {
				location.reload();
			});

			ARGPAdmin.currentJobId = null;
		},

		handleGenerationError: function(errorMessage) {
			ARGPAdmin.addLog('✗ Erreur : ' + errorMessage, 'error');
			ARGPAdmin.showNotice('error', errorMessage);
		},

		handleCancelGeneration: function(e) {
			e.preventDefault();

			if (!ARGPAdmin.currentJobId || !confirm('Annuler la génération en cours ?')) {
				return;
			}

			ARGPAdmin.stopTickLoop();

			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_cancel_generation',
					nonce: argpAdmin.nonce,
					job_id: ARGPAdmin.currentJobId
				},
				success: function() {
					ARGPAdmin.addLog('Génération annulée', 'info');
					ARGPAdmin.currentJobId = null;
					ARGPAdmin.showNotice('info', 'Génération annulée.');
				}
			});
		},

		/* ========================================
		   REPRISE JOB (PHASE 5)
		   ======================================== */

		checkForExistingJob: function() {
			if ($('#argp-generate-form').length === 0) {
				return;
			}

			$.ajax({
				url: argpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'argp_get_current_job',
					nonce: argpAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data.has_job) {
						const data = response.data;
						if (confirm('Une génération est en cours (' + data.count + ' recette(s)). Reprendre ?')) {
							ARGPAdmin.showNotice('info', 'Reprise...');
							$('#argp-generate-form').parent().hide();
							$('#argp-progress-container').show();
							ARGPAdmin.currentJobId = data.job_id;
							ARGPAdmin.startTickLoop();
						}
					}
				}
			});
		},

		/* ========================================
		   UTILITAIRES
		   ======================================== */

		showNotice: function(type, message) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.argp-admin-page').prepend($notice);

			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	$(document).ready(function() {
		ARGPAdmin.init();
	});

})(jQuery);
