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
	 * Affiche la page "Générer"
	 */
	public function render_generate_page() {
		// Vérifier les permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		?>
		<div class="wrap argp-admin-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Générez des recettes intelligentes avec l\'IA et publiez-les automatiquement sur votre blog.', 'ai-recipe-generator-pro' ); ?>
			</p>

			<div class="argp-generate-form-container">
				<form id="argp-generate-form" method="post" class="argp-form">
					<?php wp_nonce_field( 'argp_generate_action', 'argp_generate_nonce' ); ?>

					<table class="form-table">
						<tbody>
							<!-- Sujet/Thème -->
							<tr>
								<th scope="row">
									<label for="argp_subject">
										<?php esc_html_e( 'Sujet/Thème', 'ai-recipe-generator-pro' ); ?>
										<span class="required">*</span>
									</label>
								</th>
								<td>
									<input 
										type="text" 
										id="argp_subject" 
										name="argp_subject" 
										class="regular-text" 
										placeholder="<?php esc_attr_e( 'Ex: recettes végétariennes, desserts au chocolat...', 'ai-recipe-generator-pro' ); ?>"
										required
									/>
									<p class="description">
										<?php esc_html_e( 'Le thème principal des recettes à générer.', 'ai-recipe-generator-pro' ); ?>
									</p>
								</td>
							</tr>

							<!-- Nombre de recettes -->
							<tr>
								<th scope="row">
									<label for="argp_count">
										<?php esc_html_e( 'Nombre de recettes', 'ai-recipe-generator-pro' ); ?>
									</label>
								</th>
								<td>
									<select id="argp_count" name="argp_count">
										<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
											<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, 3 ); ?>>
												<?php echo esc_html( $i ); ?>
											</option>
										<?php endfor; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Nombre de recettes à générer (1 à 10).', 'ai-recipe-generator-pro' ); ?>
									</p>
								</td>
							</tr>

							<!-- Titre -->
							<tr>
								<th scope="row">
									<label for="argp_title">
										<?php esc_html_e( 'Titre de l\'article', 'ai-recipe-generator-pro' ); ?>
									</label>
								</th>
								<td>
									<div class="argp-title-field-wrapper">
										<input 
											type="text" 
											id="argp_title" 
											name="argp_title" 
											class="regular-text" 
											placeholder="<?php esc_attr_e( 'Laissez vide pour utiliser le sujet', 'ai-recipe-generator-pro' ); ?>"
										/>
										<button 
											type="button" 
											id="argp-suggest-title" 
											class="button button-secondary"
										>
											<?php esc_html_e( 'Suggérer', 'ai-recipe-generator-pro' ); ?>
										</button>
									</div>
									<p class="description">
										<?php esc_html_e( 'Titre de l\'article WordPress. Cliquez sur "Suggérer" pour obtenir des propositions basées sur vos articles récents.', 'ai-recipe-generator-pro' ); ?>
									</p>
									
									<!-- Zone de suggestions -->
									<div id="argp-suggestions-container" class="argp-suggestions-container" style="display: none;">
										<p class="argp-suggestions-label">
											<?php esc_html_e( 'Cliquez sur une suggestion pour l\'utiliser :', 'ai-recipe-generator-pro' ); ?>
										</p>
										<div id="argp-suggestions-list" class="argp-suggestions-list">
											<!-- Les suggestions seront insérées ici par JS -->
										</div>
									</div>
								</td>
							</tr>

							<!-- Statut de l'article (NOUVEAU PHASE 3) -->
							<tr>
								<th scope="row">
									<label for="argp_status">
										<?php esc_html_e( 'Statut de l\'article', 'ai-recipe-generator-pro' ); ?>
									</label>
								</th>
								<td>
									<select id="argp_status" name="argp_status">
										<option value="draft" selected>
											<?php esc_html_e( 'Brouillon (draft)', 'ai-recipe-generator-pro' ); ?>
										</option>
										<option value="publish">
											<?php esc_html_e( 'Publié (publish)', 'ai-recipe-generator-pro' ); ?>
										</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'Statut de publication de l\'article généré. Recommandation : commencez par "Brouillon" pour relire avant publication.', 'ai-recipe-generator-pro' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large" id="argp-generate-submit">
							<span class="dashicons dashicons-admin-post" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Générer l\'article complet', 'ai-recipe-generator-pro' ); ?>
						</button>
					</p>
				</form>

				<!-- PHASE 3: Zone de progression -->
				<div id="argp-progress-container" class="argp-progress-container" style="display: none;">
					<h2><?php esc_html_e( 'Génération en cours...', 'ai-recipe-generator-pro' ); ?></h2>
					
					<!-- Barre de progression -->
					<div class="argp-progress-bar-wrapper">
						<div class="argp-progress-bar">
							<div id="argp-progress-bar-fill" class="argp-progress-bar-fill" style="width: 0%;">
								<span id="argp-progress-percent" class="argp-progress-percent">0%</span>
							</div>
						</div>
					</div>

					<!-- Message de statut -->
					<div id="argp-progress-status" class="argp-progress-status">
						<?php esc_html_e( 'Initialisation...', 'ai-recipe-generator-pro' ); ?>
					</div>

					<!-- Logs détaillés -->
					<div id="argp-progress-logs" class="argp-progress-logs">
						<!-- Les logs seront ajoutés ici par JS -->
					</div>

					<!-- Bouton d'annulation -->
					<p class="submit">
						<button type="button" id="argp-cancel-generation" class="button button-secondary">
							<span class="dashicons dashicons-no" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Annuler', 'ai-recipe-generator-pro' ); ?>
						</button>
					</p>
				</div>

				<!-- PHASE 3: Zone de résultats -->
				<div id="argp-results-container" class="argp-results-container" style="display: none;">
					<h2><?php esc_html_e( 'Génération terminée !', 'ai-recipe-generator-pro' ); ?></h2>
					<div id="argp-results-content">
						<!-- Résultats AJAX ici -->
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
		<div class="wrap argp-admin-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="argp-settings-container">
				<!-- Formulaire Settings API -->
				<form method="post" action="options.php">
					<?php
					settings_fields( 'argp_settings_group' );
					do_settings_sections( 'argp-settings' );
					submit_button( __( 'Enregistrer les réglages', 'ai-recipe-generator-pro' ) );
					?>
				</form>

				<!-- Section Diagnostics -->
				<div class="argp-diagnostics-section">
					<h2><?php esc_html_e( 'Diagnostics système', 'ai-recipe-generator-pro' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Vérifiez que votre serveur est correctement configuré pour utiliser le plugin.', 'ai-recipe-generator-pro' ); ?>
					</p>

					<button type="button" id="argp-run-diagnostics" class="button button-secondary">
						<?php esc_html_e( 'Lancer le test', 'ai-recipe-generator-pro' ); ?>
					</button>

					<div id="argp-diagnostics-results" class="argp-diagnostics-results" style="display: none;">
						<!-- Les résultats seront insérés ici par AJAX -->
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
