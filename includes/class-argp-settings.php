<?php
/**
 * Gestion des réglages via Settings API
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ARGP_Settings
 * Gère l'enregistrement et l'affichage des paramètres via Settings API
 */
class ARGP_Settings {

	/**
	 * Instance unique (singleton)
	 *
	 * @var ARGP_Settings
	 */
	private static $instance = null;

	/**
	 * Nom de l'option dans la base de données
	 *
	 * @var string
	 */
	private $option_name = 'argp_settings';

	/**
	 * Récupère l'instance unique
	 *
	 * @return ARGP_Settings
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Enregistre les réglages via Settings API
	 */
	public function register_settings() {
		// Enregistrer le groupe d'options
		register_setting(
			'argp_settings_group',
			$this->option_name,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Section : Clés API
		add_settings_section(
			'argp_api_keys_section',
			__( 'Clés API', 'ai-recipe-generator-pro' ),
			array( $this, 'render_api_keys_section_callback' ),
			'argp-settings'
		);

		// Champ : OpenAI API Key
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'ai-recipe-generator-pro' ),
			array( $this, 'render_openai_api_key_field' ),
			'argp-settings',
			'argp_api_keys_section'
		);

		// Champ : Modèle OpenAI
		add_settings_field(
			'openai_model',
			__( 'Modèle OpenAI', 'ai-recipe-generator-pro' ),
			array( $this, 'render_openai_model_field' ),
			'argp-settings',
			'argp_api_keys_section'
		);

		// Champ : Replicate API Key
		add_settings_field(
			'replicate_api_key',
			__( 'Replicate API Key', 'ai-recipe-generator-pro' ),
			array( $this, 'render_replicate_api_key_field' ),
			'argp-settings',
			'argp_api_keys_section'
		);

		// Section : Préférences
		add_settings_section(
			'argp_preferences_section',
			__( 'Préférences', 'ai-recipe-generator-pro' ),
			array( $this, 'render_preferences_section_callback' ),
			'argp-settings'
		);

		// Champ : Titres manuels préférés
		add_settings_field(
			'manual_titles',
			__( 'Titres manuels préférés', 'ai-recipe-generator-pro' ),
			array( $this, 'render_manual_titles_field' ),
			'argp-settings',
			'argp_preferences_section'
		);

		// Section : Processus de Génération
		add_settings_section(
			'argp_process_section',
			__( 'Processus de Génération', 'ai-recipe-generator-pro' ),
			array( $this, 'render_process_section_callback' ),
			'argp-settings'
		);

		// Champ : Ordre de génération
		add_settings_field(
			'generation_order',
			__( 'Ordre de génération', 'ai-recipe-generator-pro' ),
			array( $this, 'render_generation_order_field' ),
			'argp-settings',
			'argp_process_section'
		);

		// Champ : Arrêt si erreur
		add_settings_field(
			'stop_on_error',
			__( 'Arrêt si erreur', 'ai-recipe-generator-pro' ),
			array( $this, 'render_stop_on_error_field' ),
			'argp-settings',
			'argp_process_section'
		);

		// Section : Prompts Personnalisables
		add_settings_section(
			'argp_prompts_section',
			__( 'Prompts Personnalisables', 'ai-recipe-generator-pro' ),
			array( $this, 'render_prompts_section_callback' ),
			'argp-settings'
		);

		// Champ : Prompt Texte Recette
		add_settings_field(
			'prompt_text',
			__( 'Prompt Texte Recette', 'ai-recipe-generator-pro' ),
			array( $this, 'render_prompt_text_field' ),
			'argp-settings',
			'argp_prompts_section'
		);

		// Champ : Prompt Génération Image
		add_settings_field(
			'prompt_image',
			__( 'Prompt Génération Image', 'ai-recipe-generator-pro' ),
			array( $this, 'render_prompt_image_field' ),
			'argp-settings',
			'argp_prompts_section'
		);

		// PHASE 5: Section Debug
		add_settings_section(
			'argp_debug_section',
			__( 'Options avancées', 'ai-recipe-generator-pro' ),
			array( $this, 'render_debug_section_callback' ),
			'argp-settings'
		);

		// PHASE 5: Champ Debug
		add_settings_field(
			'enable_debug',
			__( 'Activer les logs', 'ai-recipe-generator-pro' ),
			array( $this, 'render_debug_field' ),
			'argp-settings',
			'argp_debug_section'
		);
	}

	/**
	 * Callback de la section API Keys
	 */
	public function render_api_keys_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Configurez vos clés API pour OpenAI et Replicate. Ces clés sont nécessaires pour générer du contenu avec l\'IA.', 'ai-recipe-generator-pro' );
		echo '</p>';

		// PHASE 5: Warning si chiffrement indisponible
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			echo '<div class="notice notice-warning inline">';
			echo '<p><strong>' . esc_html__( '⚠️ Attention :', 'ai-recipe-generator-pro' ) . '</strong> ';
			esc_html_e( 'L\'extension OpenSSL n\'est pas disponible sur votre serveur. Les clés API seront stockées en clair dans la base de données. Pour plus de sécurité, contactez votre hébergeur pour activer OpenSSL.', 'ai-recipe-generator-pro' );
			echo '</p></div>';
		} else {
			echo '<div class="notice notice-success inline">';
			echo '<p><strong>' . esc_html__( '✓ Sécurité :', 'ai-recipe-generator-pro' ) . '</strong> ';
			esc_html_e( 'Les clés API sont chiffrées avant stockage avec OpenSSL (AES-256-CBC).', 'ai-recipe-generator-pro' );
			echo '</p></div>';
		}
	}

	/**
	 * Affiche le champ Modèle OpenAI
	 */
	public function render_openai_model_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-4o';

		?>
		<select 
			id="argp_openai_model" 
			name="<?php echo esc_attr( $this->option_name ); ?>[openai_model]"
			class="regular-text"
		>
			<option value="gpt-4o" <?php selected( $value, 'gpt-4o' ); ?>>
				GPT-4o (Recommandé) - ~$0.03/recette
			</option>
			<option value="gpt-4o-mini" <?php selected( $value, 'gpt-4o-mini' ); ?>>
				GPT-4o Mini (Économique) - ~$0.015/recette (-50%)
			</option>
			<option value="gpt-4-turbo" <?php selected( $value, 'gpt-4-turbo' ); ?>>
				GPT-4 Turbo - ~$0.03/recette
			</option>
			<option value="gpt-3.5-turbo" <?php selected( $value, 'gpt-3.5-turbo' ); ?>>
				GPT-3.5 Turbo (Tests) - ~$0.003/recette (-90%)
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Modèle utilisé pour générer le texte. GPT-4o recommandé pour la qualité, GPT-3.5 pour économiser.', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * Callback de la section Préférences
	 */
	public function render_preferences_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Personnalisez le comportement du générateur de recettes.', 'ai-recipe-generator-pro' );
		echo '</p>';
	}

	/**
	 * Callback de la section Processus
	 */
	public function render_process_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Configurez le comportement du processus de génération.', 'ai-recipe-generator-pro' );
		echo '</p>';
	}

	/**
	 * Callback de la section Prompts
	 */
	public function render_prompts_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Modifiez les prompts utilisés pour générer les textes et images. Variables disponibles : {titre}, {item}, {ingredients}', 'ai-recipe-generator-pro' );
		echo '</p>';
	}

	/**
	 * PHASE 5: Callback de la section Debug
	 */
	public function render_debug_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Options de débogage et maintenance. Activer uniquement si vous rencontrez des problèmes.', 'ai-recipe-generator-pro' );
		echo '</p>';
	}

	/**
	 * Affiche le champ OpenAI API Key
	 */
	public function render_openai_api_key_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['openai_api_key'] ) ? $this->decrypt_api_key( $options['openai_api_key'] ) : '';
		$masked  = ! empty( $value ) ? str_repeat( '•', 20 ) . substr( $value, -4 ) : '';

		?>
		<div class="argp-api-key-wrapper">
			<input 
				type="password" 
				id="argp_openai_api_key" 
				name="<?php echo esc_attr( $this->option_name ); ?>[openai_api_key]" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="regular-text argp-api-key-field"
				placeholder="sk-..."
				maxlength="200"
			/>
			<button type="button" class="button button-small argp-toggle-visibility" data-target="argp_openai_api_key">
				<?php esc_html_e( 'Afficher', 'ai-recipe-generator-pro' ); ?>
			</button>
			<button type="button" class="button button-small argp-test-api" data-api="openai">
				<?php esc_html_e( 'Tester l\'API', 'ai-recipe-generator-pro' ); ?>
			</button>
			<?php if ( ! empty( $value ) ) : ?>
				<span class="argp-key-preview"><?php echo esc_html( $masked ); ?></span>
			<?php endif; ?>
			
			<!-- Résultat test API -->
			<div id="argp-openai-test-result" class="argp-api-test-result" style="display: none;"></div>
			
			<!-- Crédits API -->
			<div id="argp-openai-credits" class="argp-api-credits" style="display: none;"></div>
		</div>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL vers OpenAI */
				esc_html__( 'Obtenez votre clé API sur %s', 'ai-recipe-generator-pro' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Affiche le champ Replicate API Key
	 */
	public function render_replicate_api_key_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['replicate_api_key'] ) ? $this->decrypt_api_key( $options['replicate_api_key'] ) : '';
		$masked  = ! empty( $value ) ? str_repeat( '•', 20 ) . substr( $value, -4 ) : '';

		?>
		<div class="argp-api-key-wrapper">
			<input 
				type="password" 
				id="argp_replicate_api_key" 
				name="<?php echo esc_attr( $this->option_name ); ?>[replicate_api_key]" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="regular-text argp-api-key-field"
				placeholder="r8_..."
				maxlength="200"
			/>
			<button type="button" class="button button-small argp-toggle-visibility" data-target="argp_replicate_api_key">
				<?php esc_html_e( 'Afficher', 'ai-recipe-generator-pro' ); ?>
			</button>
			<button type="button" class="button button-small argp-test-api" data-api="replicate">
				<?php esc_html_e( 'Tester l\'API', 'ai-recipe-generator-pro' ); ?>
			</button>
			<?php if ( ! empty( $value ) ) : ?>
				<span class="argp-key-preview"><?php echo esc_html( $masked ); ?></span>
			<?php endif; ?>
			
			<!-- Résultat test API -->
			<div id="argp-replicate-test-result" class="argp-api-test-result" style="display: none;"></div>
			
			<!-- Crédits API -->
			<div id="argp-replicate-credits" class="argp-api-credits" style="display: none;"></div>
		</div>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL vers Replicate */
				esc_html__( 'Obtenez votre clé API sur %s', 'ai-recipe-generator-pro' ),
				'<a href="https://replicate.com/account/api-tokens" target="_blank">Replicate</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Affiche le champ Titres manuels préférés
	 */
	public function render_manual_titles_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['manual_titles'] ) ? $options['manual_titles'] : '';

		?>
		<textarea 
			id="argp_manual_titles" 
			name="<?php echo esc_attr( $this->option_name ); ?>[manual_titles]" 
			rows="8" 
			class="large-text code"
			placeholder="<?php esc_attr_e( 'Un titre par ligne...', 'ai-recipe-generator-pro' ); ?>"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Liste de titres d\'articles à utiliser comme référence pour les suggestions. Entrez un titre par ligne.', 'ai-recipe-generator-pro' ); ?>
			<br>
			<?php esc_html_e( 'Ces titres seront combinés avec les 15 derniers articles de votre blog pour générer des suggestions pertinentes.', 'ai-recipe-generator-pro' ); ?>
		</p>

		<!-- Affichage des 15 derniers titres du blog -->
		<div style="margin-top: 20px; padding: 15px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;">
			<h4 style="margin: 0 0 12px 0; font-size: 14px; color: #1d2327;">
				<?php esc_html_e( '📚 Titres de votre blog utilisés pour le bouton "Suggérer" :', 'ai-recipe-generator-pro' ); ?>
			</h4>
			<p style="margin: 0 0 10px 0; font-size: 13px; color: #646970;">
				<?php esc_html_e( 'Ces 15 derniers titres publiés sont automatiquement utilisés comme référence pour les suggestions.', 'ai-recipe-generator-pro' ); ?>
			</p>
			<?php
			// Récupérer les 15 derniers articles
			$recent_posts = get_posts(
				array(
					'numberposts' => 15,
					'post_status' => 'publish',
					'post_type'   => 'post',
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			if ( ! empty( $recent_posts ) ) {
				echo '<ol style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">';
				foreach ( $recent_posts as $post ) {
					echo '<li style="color: #1d2327;">' . esc_html( $post->post_title ) . '</li>';
				}
				echo '</ol>';
			} else {
				echo '<p style="margin: 0; font-size: 13px; color: #646970; font-style: italic;">';
				esc_html_e( 'Aucun article publié trouvé. Publiez quelques articles pour améliorer les suggestions.', 'ai-recipe-generator-pro' );
				echo '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Affiche le champ Ordre de génération
	 */
	public function render_generation_order_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['generation_order'] ) ? $options['generation_order'] : 'image_first';

		?>
		<fieldset>
			<label>
				<input 
					type="radio" 
					name="<?php echo esc_attr( $this->option_name ); ?>[generation_order]" 
					value="image_first"
					<?php checked( $value, 'image_first' ); ?>
				/>
				<span style="margin-left: 5px;">🖼️ <?php esc_html_e( 'Image d\'abord, puis texte', 'ai-recipe-generator-pro' ); ?></span>
			</label>
			<p class="description" style="margin-left: 25px;">
				<?php esc_html_e( 'L\'IA génère l\'image, puis analyse l\'image pour créer le texte de recette (défaut)', 'ai-recipe-generator-pro' ); ?>
			</p>
			<br>
			<label>
				<input 
					type="radio" 
					name="<?php echo esc_attr( $this->option_name ); ?>[generation_order]" 
					value="text_first"
					<?php checked( $value, 'text_first' ); ?>
				/>
				<span style="margin-left: 5px;">📝 <?php esc_html_e( 'Texte d\'abord, puis image', 'ai-recipe-generator-pro' ); ?></span>
			</label>
			<p class="description" style="margin-left: 25px;">
				<?php esc_html_e( 'L\'IA génère le texte de recette, puis crée une image basée sur le texte', 'ai-recipe-generator-pro' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Affiche le champ Arrêt si erreur
	 */
	public function render_stop_on_error_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['stop_on_error'] ) ? (bool) $options['stop_on_error'] : false;

		?>
		<label>
			<input 
				type="checkbox" 
				id="argp_stop_on_error" 
				name="<?php echo esc_attr( $this->option_name ); ?>[stop_on_error]" 
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Arrêter la génération si une erreur critique survient', 'ai-recipe-generator-pro' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Si désactivé, le plugin passera à la recette suivante en cas d\'erreur.', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche le champ Prompt Texte
	 */
	public function render_prompt_text_field() {
		$options = get_option( $this->option_name, array() );
		$default = "Écris une recette à partir de : {titre}\n\nFormat:\n- Titre court\n- Personnes et temps\n- Ingrédients avec émojis et grammage\n- Étapes numérotées 1️⃣, 2️⃣ avec émojis\n- Astuce pour faciliter\n- Ingrédient à échanger\n- Astuce de cuisson";
		$value   = isset( $options['prompt_text'] ) ? $options['prompt_text'] : $default;

		?>
		<textarea 
			id="argp_prompt_text" 
			name="<?php echo esc_attr( $this->option_name ); ?>[prompt_text]" 
			rows="12" 
			class="large-text code"
			style="font-family: monospace;"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Prompt utilisé pour générer le texte de chaque recette. Variables : {titre}, {item}', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche le champ Prompt Image
	 */
	public function render_prompt_image_field() {
		$options = get_option( $this->option_name, array() );
		$default = "Tu es expert en direction artistique culinaire. Crée un prompt d'image détaillé et appétissant.\n\nConsignes:\n- Décris le rendu visuel final du plat\n- Type de plat, portions visibles\n- Ingrédients reconnaissables\n- Textures (fondant, croustillant, gratiné)\n- Couleurs dominantes\n- Type et couleur assiette\n- Disposition éléments\n- Ambiance : surface, style, éclairage naturel\n- Interdiction : personnages, mains\n- Style : photographie culinaire professionnelle, ultra réaliste, magazine";
		$value   = isset( $options['prompt_image'] ) ? $options['prompt_image'] : $default;

		?>
		<textarea 
			id="argp_prompt_image" 
			name="<?php echo esc_attr( $this->option_name ); ?>[prompt_image]" 
			rows="15" 
			class="large-text code"
			style="font-family: monospace;"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Prompt système utilisé pour créer le prompt image à partir du texte de recette. Variables : {recipe_text}', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * PHASE 5: Affiche le champ Debug
	 */
	public function render_debug_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['enable_debug'] ) ? (bool) $options['enable_debug'] : false;

		?>
		<label>
			<input 
				type="checkbox" 
				id="argp_enable_debug" 
				name="<?php echo esc_attr( $this->option_name ); ?>[enable_debug]" 
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Activer les logs de débogage', 'ai-recipe-generator-pro' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Les événements seront enregistrés dans le fichier de log WordPress (wp-content/debug.log). Activez uniquement en cas de problème pour diagnostiquer les erreurs.', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize les réglages avant sauvegarde
	 *
	 * @param array $input Données du formulaire.
	 * @return array Données nettoyées.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// PHASE 5: Sanitize et chiffrer OpenAI API Key
		if ( isset( $input['openai_api_key'] ) ) {
			$key = sanitize_text_field( $input['openai_api_key'] );
			$key = substr( $key, 0, 200 ); // Limite 200 caractères
			$sanitized['openai_api_key'] = $this->encrypt_api_key( $key );
		}

		// PHASE 5: Sanitize et chiffrer Replicate API Key
		if ( isset( $input['replicate_api_key'] ) ) {
			$key = sanitize_text_field( $input['replicate_api_key'] );
			$key = substr( $key, 0, 200 ); // Limite 200 caractères
			$sanitized['replicate_api_key'] = $this->encrypt_api_key( $key );
		}

		// Sanitize Manual Titles (textarea)
		if ( isset( $input['manual_titles'] ) ) {
			$sanitized['manual_titles'] = sanitize_textarea_field( $input['manual_titles'] );
		}

		// Sanitize OpenAI Model
		if ( isset( $input['openai_model'] ) ) {
			$model = sanitize_text_field( $input['openai_model'] );
			$allowed_models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
			$sanitized['openai_model'] = in_array( $model, $allowed_models, true ) ? $model : 'gpt-4o';
		}

		// Sanitize Generation Order
		if ( isset( $input['generation_order'] ) ) {
			$order = sanitize_text_field( $input['generation_order'] );
			$sanitized['generation_order'] = in_array( $order, array( 'image_first', 'text_first' ), true ) ? $order : 'image_first';
		}

		// Sanitize Stop on Error
		$sanitized['stop_on_error'] = isset( $input['stop_on_error'] ) && '1' === $input['stop_on_error'];

		// Sanitize Prompt Text
		if ( isset( $input['prompt_text'] ) ) {
			$sanitized['prompt_text'] = sanitize_textarea_field( $input['prompt_text'] );
		}

		// Sanitize Prompt Image
		if ( isset( $input['prompt_image'] ) ) {
			$sanitized['prompt_image'] = sanitize_textarea_field( $input['prompt_image'] );
		}

		// PHASE 5: Sanitize Debug option
		$sanitized['enable_debug'] = isset( $input['enable_debug'] ) && '1' === $input['enable_debug'];

		return $sanitized;
	}

	/* ========================================
	   PHASE 5: CHIFFREMENT DES CLÉS API
	   ======================================== */

	/**
	 * Chiffre une clé API avec OpenSSL (AES-256-CBC)
	 *
	 * @param string $key Clé en clair.
	 * @return string Clé chiffrée (base64) ou clé en clair si openssl indisponible.
	 */
	private function encrypt_api_key( $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		// Si openssl n'est pas disponible, stocker en clair
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $key;
		}

		// Si la clé est déjà chiffrée (base64 sans préfixe sk-/r8_), ne pas rechiffrer
		if ( ! preg_match( '/^(sk-|r8_)/', $key ) && base64_decode( $key, true ) !== false ) {
			return $key;
		}

		$method     = 'AES-256-CBC';
		$secret_key = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
		$iv         = substr( NONCE_KEY, 0, 16 );

		$encrypted = openssl_encrypt( $key, $method, $secret_key, 0, $iv );

		if ( false === $encrypted ) {
			// Fallback : stocker en clair
			return $key;
		}

		return base64_encode( $encrypted );
	}

	/**
	 * Déchiffre une clé API
	 *
	 * @param string $encrypted Clé chiffrée (base64).
	 * @return string Clé en clair.
	 */
	private function decrypt_api_key( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		// Si la clé commence par sk- ou r8_, elle est déjà en clair
		if ( preg_match( '/^(sk-|r8_)/', $encrypted ) ) {
			return $encrypted;
		}

		// Si openssl n'est pas disponible, assume clair
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $encrypted;
		}

		$method     = 'AES-256-CBC';
		$secret_key = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
		$iv         = substr( NONCE_KEY, 0, 16 );

		$decrypted = openssl_decrypt( base64_decode( $encrypted ), $method, $secret_key, 0, $iv );

		if ( false === $decrypted ) {
			// Fallback : retourner tel quel
			return $encrypted;
		}

		return $decrypted;
	}

	/**
	 * PHASE 5: Récupère une clé déchiffrée (méthode statique publique)
	 *
	 * @param string $key_name Nom de la clé (openai_api_key ou replicate_api_key).
	 * @return string Clé déchiffrée.
	 */
	public static function get_decrypted_key( $key_name ) {
		$options = get_option( 'argp_settings', array() );

		if ( ! isset( $options[ $key_name ] ) ) {
			return '';
		}

		$instance = self::get_instance();
		return $instance->decrypt_api_key( $options[ $key_name ] );
	}

	/**
	 * Récupère une option spécifique
	 *
	 * @param string $key     Clé de l'option.
	 * @param mixed  $default Valeur par défaut.
	 * @return mixed Valeur de l'option.
	 */
	public static function get_option( $key, $default = '' ) {
		$options = get_option( 'argp_settings', array() );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * PHASE 5: Vérifie si le mode debug est activé
	 *
	 * @return bool True si debug activé.
	 */
	public static function is_debug_enabled() {
		return (bool) self::get_option( 'enable_debug', false );
	}

	/**
	 * PHASE 5: Log un message si debug activé
	 *
	 * @param string $message Message à logger.
	 * @param string $level   Niveau (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		$formatted = sprintf(
			'[AI Recipe Generator Pro] [%s] %s',
			strtoupper( $level ),
			$message
		);

		error_log( $formatted );
	}
}
