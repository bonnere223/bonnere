<?php
/**
 * Gestion de l'interface admin
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ARGP_Admin
 * Gère les menus et pages d'administration
 */
class ARGP_Admin {

	/**
	 * Instance unique (singleton)
	 *
	 * @var ARGP_Admin
	 */
	private static $instance = null;

	/**
	 * Slug du menu principal
	 *
	 * @var string
	 */
	private $menu_slug = 'argp-main';

	/**
	 * Récupère l'instance unique
	 *
	 * @return ARGP_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_argp_clear_cache', array( $this, 'handle_clear_cache' ) );
		
		// Hook pour synchroniser statut parent → enfants
		add_action( 'transition_post_status', array( $this, 'sync_parent_children_status' ), 10, 3 );
	}

	/**
	 * Enregistre les menus admin
	 */
	public function register_menus() {
		// Menu principal
		add_menu_page(
			__( 'AI Recipe Generator Pro', 'ai-recipe-generator-pro' ),
			__( 'AI Recipe Pro', 'ai-recipe-generator-pro' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_generate_page' ),
			'dashicons-food',
			30
		);

		// Sous-menu : Générer (par défaut, même page que le parent)
		add_submenu_page(
			$this->menu_slug,
			__( 'Générer des recettes', 'ai-recipe-generator-pro' ),
			__( 'Générer', 'ai-recipe-generator-pro' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_generate_page' )
		);

		// Sous-menu : Réglages
		add_submenu_page(
			$this->menu_slug,
			__( 'Réglages & Diagnostics', 'ai-recipe-generator-pro' ),
			__( 'Réglages', 'ai-recipe-generator-pro' ),
			'manage_options',
			'argp-settings',
			array( $this, 'render_settings_page' )
		);

		// Sous-menu : Outils (Cache et maintenance)
		add_submenu_page(
			$this->menu_slug,
			__( 'Outils & Maintenance', 'ai-recipe-generator-pro' ),
			__( 'Outils', 'ai-recipe-generator-pro' ),
			'manage_options',
			'argp-tools',
			array( $this, 'render_tools_page' )
		);
	}

	/**
	 * Enqueue les assets (CSS et JS)
	 *
	 * @param string $hook Hook de la page actuelle.
	 */
	public function enqueue_assets( $hook ) {
		// Charger uniquement sur nos pages
		if ( strpos( $hook, 'argp' ) === false && strpos( $hook, 'ai-recipe-pro' ) === false ) {
			return;
		}

		// CSS admin
		wp_enqueue_style(
			'argp-admin-css',
			ARGP_PLUGIN_URL . 'assets/admin.css',
			array(),
			ARGP_VERSION,
			'all'
		);

		// JS admin
		wp_enqueue_script(
			'argp-admin-js',
			ARGP_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			ARGP_VERSION,
			true
		);

		// Localiser le script pour AJAX
		wp_localize_script(
			'argp-admin-js',
			'argpAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'argp_ajax_nonce' ),
				'costs'         => array(
					'openai_per_recipe' => 0.03,
					'replicate_per_image' => 0.04,
				),
				'strings'       => array(
					'testing'          => __( 'Test en cours...', 'ai-recipe-generator-pro' ),
					'testComplete'     => __( 'Test terminé', 'ai-recipe-generator-pro' ),
					'generating'       => __( 'Génération en cours...', 'ai-recipe-generator-pro' ),
					'error'            => __( 'Erreur', 'ai-recipe-generator-pro' ),
					'success'          => __( 'Succès', 'ai-recipe-generator-pro' ),
					'clickToSelect'    => __( 'Cliquez pour sélectionner', 'ai-recipe-generator-pro' ),
				),
			)
		);
	}

	/**
	 * Affiche la page "Générer" - REFONTE UX PREMIUM
	 */
	public function render_generate_page() {
		// Vérifier les permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		?>
		<div class="wrap argp-admin-page argp-premium-ui">
			<h1 class="argp-page-title">
				<span class="dashicons dashicons-food"></span>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<p class="argp-page-subtitle">
				<?php esc_html_e( 'Générez des articles de recettes complets avec l\'intelligence artificielle', 'ai-recipe-generator-pro' ); ?>
			</p>

			<div class="argp-layout-wrapper">
				<!-- COLONNE PRINCIPALE -->
				<div class="argp-main-column">
					<!-- CARTE: CONTENU -->
					<div class="argp-card argp-card-content">
						<div class="argp-card-header">
							<h2><?php esc_html_e( '📝 Contenu de l\'article', 'ai-recipe-generator-pro' ); ?></h2>
						</div>
						<div class="argp-card-body">
							<form id="argp-generate-form" method="post" class="argp-form-premium">
								<?php wp_nonce_field( 'argp_generate_action', 'argp_generate_nonce' ); ?>

								<!-- Sujet/Thème -->
								<div class="argp-field-group">
									<label for="argp_subject" class="argp-label">
										<?php esc_html_e( 'Sujet / Thème', 'ai-recipe-generator-pro' ); ?>
									</label>
									<input 
										type="text" 
										id="argp_subject" 
										name="argp_subject" 
										class="argp-input argp-input-large" 
										placeholder="<?php esc_attr_e( 'Ex: recettes végétariennes, desserts au chocolat...', 'ai-recipe-generator-pro' ); ?>"
									/>
									<p class="argp-field-description">
										<?php esc_html_e( 'Le thème principal des recettes à générer. Si vide, le titre sera utilisé comme thème.', 'ai-recipe-generator-pro' ); ?>
									</p>
								</div>

								<!-- Titre + Suggestions -->
								<div class="argp-field-group">
									<label for="argp_title" class="argp-label">
										<?php esc_html_e( 'Titre de l\'album / article', 'ai-recipe-generator-pro' ); ?>
									</label>
									<div class="argp-title-wrapper">
										<input 
											type="text" 
											id="argp_title" 
											name="argp_title" 
											class="argp-input argp-input-large argp-title-input" 
											placeholder="<?php esc_attr_e( 'Ex: 10 recettes végétariennes faciles...', 'ai-recipe-generator-pro' ); ?>"
										/>
										<div class="argp-title-loading-bar" style="display: none;"></div>
									</div>
									
									<div class="argp-title-actions">
										<button 
											type="button" 
											id="argp-suggest-title" 
											class="argp-btn argp-btn-secondary"
										>
											<span class="dashicons dashicons-lightbulb"></span>
											<?php esc_html_e( 'Suggérer', 'ai-recipe-generator-pro' ); ?>
										</button>
										<button 
											type="button" 
											id="argp-new-theme" 
											class="argp-btn argp-btn-secondary"
										>
											<span class="dashicons dashicons-admin-site-alt3"></span>
											<?php esc_html_e( 'Nouveau thème', 'ai-recipe-generator-pro' ); ?>
										</button>
									</div>

									<!-- Nombre détecté -->
									<div id="argp-detected-count" class="argp-detected-count" style="display: none;">
										<span class="dashicons dashicons-yes-alt"></span>
										<span id="argp-detected-count-text"></span>
									</div>
									<input type="hidden" id="argp_count" name="argp_count" value="1" />

									<p class="argp-field-description">
										<?php esc_html_e( 'Le nombre de recettes est détecté automatiquement depuis le titre (ex: "10 recettes" → 10).', 'ai-recipe-generator-pro' ); ?>
									</p>

									<!-- Zone suggestions -->
									<div id="argp-suggestions-container" class="argp-suggestions-container" style="display: none;">
										<div id="argp-suggestions-list" class="argp-suggestions-list"></div>
									</div>

									<!-- Zone nouveau thème -->
									<div id="argp-new-themes-container" class="argp-new-themes-container" style="display: none;">
										<p class="argp-new-themes-label">
											<span class="dashicons dashicons-star-filled"></span>
											<?php esc_html_e( 'Idées de thèmes inédits :', 'ai-recipe-generator-pro' ); ?>
										</p>
										<div id="argp-new-themes-list" class="argp-new-themes-list"></div>
									</div>
								</div>

								<!-- Format de publication -->
								<div class="argp-field-group">
									<label class="argp-label">
										<?php esc_html_e( 'Format de publication', 'ai-recipe-generator-pro' ); ?>
									</label>
									
									<div class="argp-radio-card">
										<label class="argp-radio-option">
											<input 
												type="radio" 
												name="argp_format" 
												id="argp_format_global" 
												value="global"
												checked
											/>
											<div class="argp-radio-content">
												<strong><?php esc_html_e( '1 Article Global', 'ai-recipe-generator-pro' ); ?></strong>
												<p><?php esc_html_e( 'Toutes les recettes dans un seul article avec intro', 'ai-recipe-generator-pro' ); ?></p>
											</div>
										</label>

										<label class="argp-radio-option">
											<input 
												type="radio" 
												name="argp_format" 
												id="argp_format_tag" 
												value="tag"
											/>
											<div class="argp-radio-content">
												<strong>📌 <?php esc_html_e( '1 Article par Recette + Tag', 'ai-recipe-generator-pro' ); ?></strong>
												<p><?php esc_html_e( 'Crée un tag et un article séparé pour chaque recette avec liens croisés', 'ai-recipe-generator-pro' ); ?></p>
											</div>
										</label>
									</div>
								</div>

								<!-- Statut publication -->
								<div class="argp-field-group">
									<label for="argp_status" class="argp-label">
										<?php esc_html_e( 'Statut de publication', 'ai-recipe-generator-pro' ); ?>
									</label>
									<select id="argp_status" name="argp_status" class="argp-select">
										<option value="draft" selected><?php esc_html_e( '📝 Brouillon', 'ai-recipe-generator-pro' ); ?></option>
										<option value="publish"><?php esc_html_e( '🚀 Publié', 'ai-recipe-generator-pro' ); ?></option>
									</select>
									<p class="argp-field-description">
										<?php esc_html_e( 'Recommandation : commencez par "Brouillon" pour relire.', 'ai-recipe-generator-pro' ); ?>
									</p>
								</div>
							</form>
						</div>
					</div>

					<!-- CARTE: IMAGES DE RÉFÉRENCE -->
					<div class="argp-card argp-card-images">
						<div class="argp-card-header">
							<h2><?php esc_html_e( '🖼️ Style visuel des images', 'ai-recipe-generator-pro' ); ?></h2>
							<p class="argp-card-subtitle"><?php esc_html_e( 'Optionnel : uploadez des images de référence pour chaque recette', 'ai-recipe-generator-pro' ); ?></p>
						</div>
						<div class="argp-card-body">
							<div id="argp-reference-images-container" class="argp-reference-images-container">
								<p class="argp-info-text">
									<?php esc_html_e( 'Le nombre de champs apparaîtra automatiquement selon le titre détecté.', 'ai-recipe-generator-pro' ); ?>
								</p>
							</div>

							<div class="argp-upload-options">
								<button type="button" id="argp-upload-zip" class="argp-btn argp-btn-outline">
									<span class="dashicons dashicons-media-archive"></span>
									<?php esc_html_e( 'Uploader un ZIP/RAR', 'ai-recipe-generator-pro' ); ?>
								</button>
								<input type="file" id="argp-zip-input" accept=".zip,.rar" style="display: none;" />
							</div>
						</div>
					</div>

					<!-- CARTE: OPTIONS IMAGE AVANCÉES -->
					<div class="argp-card argp-card-image-settings">
						<div class="argp-card-header argp-collapsible" data-target="argp-advanced-image-settings">
							<h2>
								<span class="dashicons dashicons-admin-settings"></span>
								<?php esc_html_e( 'Options image avancées', 'ai-recipe-generator-pro' ); ?>
								<span class="argp-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
							</h2>
						</div>
						<div class="argp-card-body" id="argp-advanced-image-settings" style="display: none;">
							<div class="argp-field-group">
								<label class="argp-label">
									<input type="checkbox" id="argp_high_quality" name="argp_high_quality" value="1" />
									<?php esc_html_e( 'Qualité maximale', 'ai-recipe-generator-pro' ); ?>
									<span class="argp-tooltip" data-tip="<?php esc_attr_e( 'Génère des images en haute résolution (coût +20%)', 'ai-recipe-generator-pro' ); ?>">?</span>
								</label>
							</div>
							<div class="argp-field-group">
								<label class="argp-label">
									<input type="checkbox" id="argp_realistic_style" name="argp_realistic_style" value="1" checked />
									<?php esc_html_e( 'Style photo réaliste', 'ai-recipe-generator-pro' ); ?>
									<span class="argp-tooltip" data-tip="<?php esc_attr_e( 'Photos professionnelles type magazine culinaire', 'ai-recipe-generator-pro' ); ?>">?</span>
								</label>
							</div>
							<div class="argp-field-group">
								<label class="argp-label">
									<input type="checkbox" id="argp_top_view" name="argp_top_view" value="1" />
									<?php esc_html_e( 'Vue de dessus', 'ai-recipe-generator-pro' ); ?>
									<span class="argp-tooltip" data-tip="<?php esc_attr_e( 'Plat photographié d\'en haut (flat lay)', 'ai-recipe-generator-pro' ); ?>">?</span>
								</label>
							</div>
						</div>
					</div>

					<!-- BOUTON GÉNÉRATION -->
					<div class="argp-generate-action">
						<button type="submit" form="argp-generate-form" class="argp-btn argp-btn-primary argp-btn-large" id="argp-generate-submit">
							<span class="dashicons dashicons-admin-post"></span>
							<?php esc_html_e( 'Générer l\'article complet', 'ai-recipe-generator-pro' ); ?>
						</button>
					</div>

					<!-- ZONE PROGRESSION -->
					<div id="argp-progress-container" class="argp-card argp-card-progress" style="display: none;">
						<div class="argp-card-header">
							<h2><?php esc_html_e( '⚙️ Génération en cours...', 'ai-recipe-generator-pro' ); ?></h2>
						</div>
						<div class="argp-card-body">
							<div class="argp-progress-bar-wrapper">
								<div class="argp-progress-bar">
									<div id="argp-progress-bar-fill" class="argp-progress-bar-fill" style="width: 0%;">
										<span id="argp-progress-percent" class="argp-progress-percent">0%</span>
									</div>
								</div>
							</div>

							<div id="argp-progress-status" class="argp-progress-status">
								<?php esc_html_e( 'Initialisation...', 'ai-recipe-generator-pro' ); ?>
							</div>

							<div id="argp-progress-logs" class="argp-progress-logs" aria-live="polite"></div>

							<p class="argp-cancel-wrapper">
								<button type="button" id="argp-cancel-generation" class="argp-btn argp-btn-secondary">
									<span class="dashicons dashicons-no"></span>
									<?php esc_html_e( 'Annuler', 'ai-recipe-generator-pro' ); ?>
								</button>
							</p>
						</div>
					</div>

					<!-- ZONE RÉSULTATS -->
					<div id="argp-results-container" class="argp-card argp-card-results" style="display: none;">
						<div class="argp-card-header">
							<h2><?php esc_html_e( '✅ Génération terminée !', 'ai-recipe-generator-pro' ); ?></h2>
						</div>
						<div class="argp-card-body">
							<div id="argp-results-content"></div>
						</div>
					</div>
				</div>

				<!-- SIDEBAR ESTIMATION -->
				<div class="argp-sidebar-column">
					<div class="argp-sidebar-sticky">
						<!-- CARTE ESTIMATION -->
						<div class="argp-card argp-card-estimation">
							<div class="argp-card-header">
								<h3><?php esc_html_e( '📊 Estimation', 'ai-recipe-generator-pro' ); ?></h3>
							</div>
							<div class="argp-card-body">
								<div class="argp-estimation-item">
									<div class="argp-estimation-icon">🍽️</div>
									<div class="argp-estimation-content">
										<div class="argp-estimation-label"><?php esc_html_e( 'Recettes', 'ai-recipe-generator-pro' ); ?></div>
										<div class="argp-estimation-value" id="argp-est-recipes">–</div>
									</div>
								</div>

								<div class="argp-estimation-item">
									<div class="argp-estimation-icon">💰</div>
									<div class="argp-estimation-content">
										<div class="argp-estimation-label"><?php esc_html_e( 'Coût estimé', 'ai-recipe-generator-pro' ); ?></div>
										<div class="argp-estimation-value" id="argp-est-cost">$0.00</div>
									</div>
								</div>

								<div class="argp-estimation-item">
									<div class="argp-estimation-icon">⏱️</div>
									<div class="argp-estimation-content">
										<div class="argp-estimation-label"><?php esc_html_e( 'Temps estimé', 'ai-recipe-generator-pro' ); ?></div>
										<div class="argp-estimation-value" id="argp-est-time">0 min</div>
									</div>
								</div>

								<div class="argp-estimation-footer">
									<p><?php esc_html_e( 'Estimation basée sur vos paramètres actuels', 'ai-recipe-generator-pro' ); ?></p>
								</div>
							</div>
						</div>

						<!-- CARTE AIDE -->
						<div class="argp-card argp-card-help">
							<div class="argp-card-header">
								<h3><?php esc_html_e( '💡 Aide rapide', 'ai-recipe-generator-pro' ); ?></h3>
							</div>
							<div class="argp-card-body">
								<ul class="argp-help-list">
									<li><?php esc_html_e( 'Le nombre de recettes est détecté automatiquement depuis le titre', 'ai-recipe-generator-pro' ); ?></li>
									<li><?php esc_html_e( 'Utilisez "Suggérer" pour des titres basés sur votre blog', 'ai-recipe-generator-pro' ); ?></li>
									<li><?php esc_html_e( 'Utilisez "Nouveau thème" pour découvrir des tendances', 'ai-recipe-generator-pro' ); ?></li>
									<li><?php esc_html_e( 'Les images de référence sont optionnelles', 'ai-recipe-generator-pro' ); ?></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Affiche la page "Réglages"
	 */
	public function render_settings_page() {
		// Vérifier les permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		?>
		<div class="wrap argp-admin-page argp-premium-ui">
			<h1 class="argp-page-title">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>

			<div class="argp-settings-layout">
				<!-- Formulaire Settings API -->
				<div class="argp-card">
					<div class="argp-card-body">
						<form method="post" action="options.php">
							<?php
							settings_fields( 'argp_settings_group' );
							do_settings_sections( 'argp-settings' );
							submit_button( __( 'Enregistrer les réglages', 'ai-recipe-generator-pro' ) );
							?>
						</form>
					</div>
				</div>

				<!-- Section Diagnostics -->
				<div class="argp-card">
					<div class="argp-card-header">
						<h2><?php esc_html_e( '🔧 Diagnostics système', 'ai-recipe-generator-pro' ); ?></h2>
					</div>
					<div class="argp-card-body">
						<p class="description">
							<?php esc_html_e( 'Vérifiez que votre serveur est correctement configuré pour utiliser le plugin.', 'ai-recipe-generator-pro' ); ?>
						</p>

						<button type="button" id="argp-run-diagnostics" class="button button-secondary">
							<?php esc_html_e( 'Lancer le test', 'ai-recipe-generator-pro' ); ?>
						</button>

						<div id="argp-diagnostics-results" class="argp-diagnostics-results" style="display: none;"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Affiche la page "Outils & Maintenance"
	 */
	public function render_tools_page() {
		// Vérifier les permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		// Afficher message de confirmation si cache vidé
		if ( isset( $_GET['cache_cleared'] ) && '1' === $_GET['cache_cleared'] ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Cache vidé avec succès !', 'ai-recipe-generator-pro' ) . '</strong> ' . esc_html__( 'Tous les transients et fichiers temporaires ont été supprimés.', 'ai-recipe-generator-pro' ) . '</p></div>';
		}

		$clear_cache_url = add_query_arg(
			array(
				'action'   => 'argp_clear_cache',
				'_wpnonce' => wp_create_nonce( 'argp_clear_cache' ),
			),
			admin_url( 'admin-post.php' )
		);

		?>
		<div class="wrap argp-admin-page argp-premium-ui">
			<h1 class="argp-page-title">
				<span class="dashicons dashicons-admin-tools"></span>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<p class="argp-page-subtitle">
				<?php esc_html_e( 'Outils de maintenance et nettoyage du plugin', 'ai-recipe-generator-pro' ); ?>
			</p>

			<div class="argp-layout-wrapper" style="grid-template-columns: 1fr;">
				<!-- CARTE: NETTOYAGE CACHE -->
				<div class="argp-card">
					<div class="argp-card-header">
						<h2><?php esc_html_e( '🧹 Nettoyage du cache', 'ai-recipe-generator-pro' ); ?></h2>
						<p class="argp-card-subtitle"><?php esc_html_e( 'Utilisez cet outil après une mise à jour du plugin ou si vous rencontrez des problèmes', 'ai-recipe-generator-pro' ); ?></p>
					</div>
					<div class="argp-card-body">
						<p><?php esc_html_e( 'Cette action va supprimer :', 'ai-recipe-generator-pro' ); ?></p>
						<ul style="margin-left: 20px; margin-bottom: 20px;">
							<li><?php esc_html_e( 'Tous les transients du plugin (jobs, rate limiting)', 'ai-recipe-generator-pro' ); ?></li>
							<li><?php esc_html_e( 'Tous les fichiers temporaires (ZIP, images)', 'ai-recipe-generator-pro' ); ?></li>
							<li><?php esc_html_e( 'Le cache des suggestions de titres', 'ai-recipe-generator-pro' ); ?></li>
						</ul>
						<p><strong><?php esc_html_e( '⚠️ Note :', 'ai-recipe-generator-pro' ); ?></strong> <?php esc_html_e( 'Les générations en cours seront annulées. Vos réglages et articles ne seront pas affectés.', 'ai-recipe-generator-pro' ); ?></p>

						<p style="margin-top: 30px;">
							<a href="<?php echo esc_url( $clear_cache_url ); ?>" class="button button-primary button-large" onclick="return confirm('<?php echo esc_js( __( 'Êtes-vous sûr de vouloir vider le cache ? Les générations en cours seront annulées.', 'ai-recipe-generator-pro' ) ); ?>');">
								<span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
								<?php esc_html_e( 'Vider le cache maintenant', 'ai-recipe-generator-pro' ); ?>
							</a>
						</p>
					</div>
				</div>

				<!-- CARTE: INFORMATIONS VERSION -->
				<div class="argp-card">
					<div class="argp-card-header">
						<h2><?php esc_html_e( 'ℹ️ Informations du plugin', 'ai-recipe-generator-pro' ); ?></h2>
					</div>
					<div class="argp-card-body">
						<table class="widefat">
							<tbody>
								<tr>
									<th style="width: 200px;"><?php esc_html_e( 'Version', 'ai-recipe-generator-pro' ); ?></th>
									<td><strong><?php echo esc_html( ARGP_VERSION ); ?></strong></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Dossier plugin', 'ai-recipe-generator-pro' ); ?></th>
									<td><code><?php echo esc_html( ARGP_PLUGIN_DIR ); ?></code></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Transients actifs', 'ai-recipe-generator-pro' ); ?></th>
									<td><?php echo esc_html( $this->count_plugin_transients() ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Fichiers temporaires', 'ai-recipe-generator-pro' ); ?></th>
									<td><?php echo esc_html( $this->count_temp_files() ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- CARTE: APRÈS MISE À JOUR -->
				<div class="argp-card" style="border-left: 4px solid #f0b849; background: #fffbf0;">
					<div class="argp-card-header">
						<h2><?php esc_html_e( '🔄 Après une mise à jour', 'ai-recipe-generator-pro' ); ?></h2>
					</div>
					<div class="argp-card-body">
						<p><strong><?php esc_html_e( 'Recommandation :', 'ai-recipe-generator-pro' ); ?></strong></p>
						<ol style="margin-left: 20px;">
							<li><?php esc_html_e( 'Désactiver l\'ancienne version', 'ai-recipe-generator-pro' ); ?></li>
							<li><?php esc_html_e( 'Supprimer l\'ancienne extension', 'ai-recipe-generator-pro' ); ?></li>
							<li><?php esc_html_e( 'Uploader et activer la nouvelle version', 'ai-recipe-generator-pro' ); ?></li>
							<li><strong><?php esc_html_e( 'Vider le cache (bouton ci-dessus)', 'ai-recipe-generator-pro' ); ?></strong></li>
							<li><?php esc_html_e( 'Tester avec 1 recette en mode draft', 'ai-recipe-generator-pro' ); ?></li>
						</ol>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handler : Vider le cache du plugin
	 */
	public function handle_clear_cache() {
		// Vérifier le nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'argp_clear_cache' ) ) {
			wp_die( esc_html__( 'Erreur de sécurité.', 'ai-recipe-generator-pro' ) );
		}

		// Vérifier les permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		global $wpdb;

		// Supprimer tous les transients du plugin
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_argp_%' 
			OR option_name LIKE '_transient_timeout_argp_%'"
		);

		// Nettoyer les fichiers temporaires
		$temp_dir = get_temp_dir();
		$patterns = array( 'argp-images-*', 'argp-recettes-*', 'argp-*' );

		foreach ( $patterns as $pattern ) {
			$files = glob( $temp_dir . $pattern );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						@unlink( $file );
					}
				}
			}
		}

		// Rediriger vers la page Outils avec message de succès
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'argp-tools',
					'cache_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Compte les transients actifs du plugin
	 *
	 * @return int Nombre de transients.
	 */
	private function count_plugin_transients() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_argp_%' 
			AND option_name NOT LIKE '_transient_timeout_%'"
		);

		return (int) $count;
	}

	/**
	 * Compte les fichiers temporaires du plugin
	 *
	 * @return int Nombre de fichiers.
	 */
	private function count_temp_files() {
		$temp_dir = get_temp_dir();
		$count    = 0;
		$patterns = array( 'argp-images-*', 'argp-recettes-*' );

		foreach ( $patterns as $pattern ) {
			$files = glob( $temp_dir . $pattern );
			if ( is_array( $files ) ) {
				$count += count( $files );
			}
		}

		return $count;
	}

	/**
	 * Synchronise le statut de publication parent → enfants
	 * Si l'article parent est publié, publie tous les enfants
	 *
	 * @param string  $new_status Nouveau statut.
	 * @param string  $old_status Ancien statut.
	 * @param WP_Post $post       Article.
	 */
	public function sync_parent_children_status( $new_status, $old_status, $post ) {
		// Vérifier si c'est un post
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// Vérifier si ce post a des enfants
		$children = get_posts(
			array(
				'post_parent'    => $post->ID,
				'post_type'      => 'post',
				'post_status'    => 'any',
				'numberposts'    => -1,
			)
		);

		if ( empty( $children ) ) {
			return;
		}

		// Si le parent passe à "publish", publier tous les enfants
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			foreach ( $children as $child ) {
				wp_update_post(
					array(
						'ID'          => $child->ID,
						'post_status' => 'publish',
					)
				);
			}
		}

		// Si le parent passe à "draft", mettre les enfants en draft
		if ( 'draft' === $new_status && 'draft' !== $old_status ) {
			foreach ( $children as $child ) {
				if ( 'publish' === $child->post_status ) {
					wp_update_post(
						array(
							'ID'          => $child->ID,
							'post_status' => 'draft',
						)
					);
				}
			}
		}
	}
}
