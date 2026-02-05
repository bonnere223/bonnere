<?php
/**
 * Gestion des exports (ZIP images + TXT recettes)
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ARGP_Export
 * Gère les exports ZIP et TXT des articles générés
 */
class ARGP_Export {

	/**
	 * Instance unique (singleton)
	 *
	 * @var ARGP_Export
	 */
	private static $instance = null;

	/**
	 * Récupère l'instance unique
	 *
	 * @return ARGP_Export
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
		// Ajouter la metabox sur l'écran d'édition
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );

		// Enregistrer les handlers d'export
		add_action( 'admin_post_argp_export_zip', array( $this, 'handle_export_zip' ) );
		add_action( 'admin_post_argp_export_txt', array( $this, 'handle_export_txt' ) );

		// Ajouter styles pour la metabox
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_styles' ) );
	}

	/**
	 * Enregistre la metabox
	 */
	public function register_metabox() {
		add_meta_box(
			'argp_export_metabox',
			__( 'AI Recipe Generator Pro – Export', 'ai-recipe-generator-pro' ),
			array( $this, 'render_metabox' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Enqueue styles pour la metabox
	 *
	 * @param string $hook Hook de la page actuelle.
	 */
	public function enqueue_metabox_styles( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_add_inline_style( 'argp-admin-css', '
			.argp-export-metabox .argp-export-button {
				display: block;
				width: 100%;
				margin-bottom: 10px;
				text-align: center;
				padding: 8px 12px;
			}
			.argp-export-metabox .argp-export-button .dashicons {
				margin-top: 3px;
			}
			.argp-export-metabox .argp-export-info {
				font-size: 12px;
				color: #646970;
				margin-top: 10px;
				padding: 8px;
				background: #f6f7f7;
				border-left: 3px solid #2271b1;
			}
			.argp-export-metabox .argp-export-warning {
				font-size: 12px;
				color: #d63638;
				margin-top: 10px;
				padding: 8px;
				background: #fcf0f1;
				border-left: 3px solid #d63638;
			}
		' );
	}

	/**
	 * Affiche le contenu de la metabox
	 *
	 * @param WP_Post $post Article actuel.
	 */
	public function render_metabox( $post ) {
		// Vérifier les permissions
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			echo '<p>' . esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) . '</p>';
			return;
		}

		// Générer le nonce
		$nonce = wp_create_nonce( 'argp_export_' . $post->ID );

		// URLs d'export
		$zip_url = add_query_arg(
			array(
				'action'   => 'argp_export_zip',
				'post_id'  => $post->ID,
				'_wpnonce' => $nonce,
			),
			admin_url( 'admin-post.php' )
		);

		$txt_url = add_query_arg(
			array(
				'action'   => 'argp_export_txt',
				'post_id'  => $post->ID,
				'_wpnonce' => $nonce,
			),
			admin_url( 'admin-post.php' )
		);

		?>
		<div class="argp-export-metabox">
			<a href="<?php echo esc_url( $zip_url ); ?>" class="button button-secondary argp-export-button">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Télécharger ZIP des images', 'ai-recipe-generator-pro' ); ?>
			</a>

			<a href="<?php echo esc_url( $txt_url ); ?>" class="button button-secondary argp-export-button">
				<span class="dashicons dashicons-media-text"></span>
				<?php esc_html_e( 'Télécharger TXT des recettes', 'ai-recipe-generator-pro' ); ?>
			</a>

			<div class="argp-export-info">
				<strong><?php esc_html_e( 'ℹ️ Info :', 'ai-recipe-generator-pro' ); ?></strong><br>
				<?php esc_html_e( 'Les images sont exportées dans l\'ordre d\'apparition des recettes. Le fichier TXT contient uniquement les noms et instructions.', 'ai-recipe-generator-pro' ); ?>
			</div>

			<?php if ( ! class_exists( 'ZipArchive' ) ) : ?>
				<div class="argp-export-warning">
					<strong><?php esc_html_e( '⚠️ Attention :', 'ai-recipe-generator-pro' ); ?></strong><br>
					<?php esc_html_e( 'ZipArchive n\'est pas disponible sur votre serveur. Le plugin utilisera PclZip comme fallback.', 'ai-recipe-generator-pro' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ========================================
	   HANDLER : EXPORT ZIP
	   ======================================== */

	/**
	 * Handler pour l'export ZIP des images
	 */
	public function handle_export_zip() {
		// Récupérer et valider les paramètres
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		// Vérifier le nonce
		if ( ! wp_verify_nonce( $nonce, 'argp_export_' . $post_id ) ) {
			wp_die( esc_html__( 'Erreur de sécurité : nonce invalide.', 'ai-recipe-generator-pro' ) );
		}

		// Vérifier les permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		// Récupérer le post
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'Article non trouvé.', 'ai-recipe-generator-pro' ) );
		}

		// Extraire les images
		$images = $this->extract_images_from_post( $post );

		if ( empty( $images ) ) {
			wp_die( esc_html__( 'Aucune image trouvée dans cet article.', 'ai-recipe-generator-pro' ) );
		}

		// Créer le ZIP
		$zip_path = $this->create_zip_from_images( $images, $post_id );

		if ( is_wp_error( $zip_path ) ) {
			wp_die( esc_html( $zip_path->get_error_message() ) );
		}

		// Streamer le fichier
		$this->stream_file_download( $zip_path, 'images-recettes-' . $post_id . '.zip', 'application/zip' );

		// Supprimer le fichier temporaire
		@unlink( $zip_path );

		exit;
	}

	/**
	 * Extrait les images d'un article
	 *
	 * @param WP_Post $post Article.
	 * @return array Liste des fichiers images avec métadonnées.
	 */
	private function extract_images_from_post( $post ) {
		$content = $post->post_content;
		$images  = array();

		// Méthode 1 : Détecter les classes wp-image-{ID}
		preg_match_all( '/wp-image-(\d+)/i', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				$file_path     = get_attached_file( $attachment_id );

				if ( $file_path && file_exists( $file_path ) ) {
					$images[] = array(
						'id'   => $attachment_id,
						'path' => $file_path,
						'ext'  => strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ),
					);
				}
			}
		}

		// Méthode 2 : Fallback - extraire les src et tenter de mapper
		if ( empty( $images ) ) {
			preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $img_matches );

			if ( ! empty( $img_matches[1] ) ) {
				foreach ( $img_matches[1] as $img_url ) {
					$attachment_id = attachment_url_to_postid( $img_url );

					if ( $attachment_id ) {
						$file_path = get_attached_file( $attachment_id );

						if ( $file_path && file_exists( $file_path ) ) {
							$images[] = array(
								'id'   => $attachment_id,
								'path' => $file_path,
								'ext'  => strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ),
							);
						}
					}
				}
			}
		}

		// Dédupliquer par ID
		$unique_images = array();
		$seen_ids      = array();

		foreach ( $images as $image ) {
			if ( ! in_array( $image['id'], $seen_ids, true ) ) {
				$unique_images[] = $image;
				$seen_ids[]      = $image['id'];
			}
		}

		return $unique_images;
	}

	/**
	 * Crée un ZIP à partir d'une liste d'images
	 *
	 * @param array $images  Liste des images.
	 * @param int   $post_id ID du post (pour nommage).
	 * @return string|WP_Error Chemin du fichier ZIP ou erreur.
	 */
	private function create_zip_from_images( $images, $post_id ) {
		// Créer un fichier temporaire
		$temp_dir  = get_temp_dir();
		$zip_path  = $temp_dir . 'argp-images-' . $post_id . '-' . time() . '.zip';

		// Utiliser ZipArchive si disponible
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();

			if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
				return new WP_Error( 'zip_error', __( 'Impossible de créer le fichier ZIP.', 'ai-recipe-generator-pro' ) );
			}

			foreach ( $images as $index => $image ) {
				$new_name = 'recette-' . ( $index + 1 ) . '.' . $image['ext'];
				$zip->addFile( $image['path'], $new_name );
			}

			$zip->close();

		} else {
			// Fallback : utiliser PclZip (fourni par WordPress)
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

			$zip = new PclZip( $zip_path );

			$file_list = array();
			foreach ( $images as $index => $image ) {
				$file_list[] = array(
					PCLZIP_ATT_FILE_NAME       => $image['path'],
					PCLZIP_ATT_FILE_NEW_FULL_NAME => 'recette-' . ( $index + 1 ) . '.' . $image['ext'],
				);
			}

			$result = $zip->create( $file_list );

			if ( 0 === $result ) {
				return new WP_Error( 'pclzip_error', __( 'Erreur PclZip : ', 'ai-recipe-generator-pro' ) . $zip->errorInfo( true ) );
			}
		}

		return $zip_path;
	}

	/* ========================================
	   HANDLER : EXPORT TXT
	   ======================================== */

	/**
	 * Handler pour l'export TXT des recettes
	 */
	public function handle_export_txt() {
		// Récupérer et valider les paramètres
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		// Vérifier le nonce
		if ( ! wp_verify_nonce( $nonce, 'argp_export_' . $post_id ) ) {
			wp_die( esc_html__( 'Erreur de sécurité : nonce invalide.', 'ai-recipe-generator-pro' ) );
		}

		// Vérifier les permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ) );
		}

		// Récupérer le post
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'Article non trouvé.', 'ai-recipe-generator-pro' ) );
		}

		// Extraire les recettes
		$recipes = $this->extract_recipes_from_post( $post );

		if ( empty( $recipes ) ) {
			wp_die( esc_html__( 'Aucune recette trouvée dans cet article.', 'ai-recipe-generator-pro' ) );
		}

		// Générer le contenu TXT
		$txt_content = $this->generate_txt_from_recipes( $recipes );

		// Créer un fichier temporaire
		$temp_dir = get_temp_dir();
		$txt_path = $temp_dir . 'argp-recettes-' . $post_id . '-' . time() . '.txt';

		file_put_contents( $txt_path, $txt_content );

		// Streamer le fichier
		$this->stream_file_download( $txt_path, 'recettes-' . $post_id . '.txt', 'text/plain; charset=utf-8' );

		// Supprimer le fichier temporaire
		@unlink( $txt_path );

		exit;
	}

	/**
	 * Extrait les recettes d'un article
	 *
	 * @param WP_Post $post Article.
	 * @return array Liste des recettes avec nom et instructions.
	 */
	private function extract_recipes_from_post( $post ) {
		$content = $post->post_content;
		$recipes = array();

		// Tenter d'utiliser DOMDocument pour un parsing propre
		if ( class_exists( 'DOMDocument' ) ) {
			$recipes = $this->extract_recipes_dom( $content );
		}

		// Fallback : regex si DOMDocument échoue ou retourne vide
		if ( empty( $recipes ) ) {
			$recipes = $this->extract_recipes_regex( $content );
		}

		return $recipes;
	}

	/**
	 * Extrait les recettes avec DOMDocument
	 *
	 * @param string $content Contenu HTML.
	 * @return array Liste des recettes.
	 */
	private function extract_recipes_dom( $content ) {
		$recipes = array();

		// Supprimer les warnings de DOMDocument
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		libxml_clear_errors();

		// Récupérer tous les H2 (titres de recettes)
		$h2_list = $dom->getElementsByTagName( 'h2' );

		foreach ( $h2_list as $h2 ) {
			$recipe_name = trim( $h2->textContent );

			if ( empty( $recipe_name ) ) {
				continue;
			}

			// Chercher le prochain <ol> après ce H2
			$instructions = array();
			$next_node    = $h2->nextSibling;

			while ( $next_node ) {
				if ( $next_node->nodeType === XML_ELEMENT_NODE && 'ol' === $next_node->nodeName ) {
					// Récupérer les <li>
					$li_list = $next_node->getElementsByTagName( 'li' );
					foreach ( $li_list as $li ) {
						$instructions[] = trim( $li->textContent );
					}
					break;
				}

				$next_node = $next_node->nextSibling;
			}

			if ( ! empty( $instructions ) ) {
				$recipes[] = array(
					'name'         => $recipe_name,
					'instructions' => $instructions,
				);
			}
		}

		return $recipes;
	}

	/**
	 * Extrait les recettes avec regex (fallback)
	 *
	 * @param string $content Contenu HTML.
	 * @return array Liste des recettes.
	 */
	private function extract_recipes_regex( $content ) {
		$recipes = array();

		// Extraire les H2
		preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $content, $h2_matches );

		if ( empty( $h2_matches[1] ) ) {
			return $recipes;
		}

		// Pour chaque H2, chercher le prochain <ol>
		foreach ( $h2_matches[1] as $h2_content ) {
			$recipe_name = wp_strip_all_tags( $h2_content );

			// Chercher les <ol> après ce H2
			$pattern = '/<ol[^>]*>(.*?)<\/ol>/is';
			if ( preg_match( $pattern, $content, $ol_matches ) ) {
				// Extraire les <li>
				preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $ol_matches[1], $li_matches );

				$instructions = array();
				foreach ( $li_matches[1] as $li_content ) {
					$instructions[] = wp_strip_all_tags( $li_content );
				}

				if ( ! empty( $instructions ) ) {
					$recipes[] = array(
						'name'         => $recipe_name,
						'instructions' => $instructions,
					);
				}
			}
		}

		return $recipes;
	}

	/**
	 * Génère le contenu TXT à partir des recettes
	 *
	 * @param array $recipes Liste des recettes.
	 * @return string Contenu TXT formaté.
	 */
	private function generate_txt_from_recipes( $recipes ) {
		$txt = '';

		foreach ( $recipes as $index => $recipe ) {
			// Titre de la recette
			$txt .= strtoupper( $recipe['name'] ) . "\n";
			$txt .= str_repeat( '=', mb_strlen( $recipe['name'] ) ) . "\n\n";

			// Instructions
			foreach ( $recipe['instructions'] as $step_index => $instruction ) {
				$txt .= ( $step_index + 1 ) . ') ' . $instruction . "\n";
			}

			// Ligne vide entre recettes (sauf dernière)
			if ( $index < count( $recipes ) - 1 ) {
				$txt .= "\n\n";
			}
		}

		return $txt;
	}

	/* ========================================
	   UTILITAIRE : STREAM FILE
	   ======================================== */

	/**
	 * Streame un fichier pour téléchargement
	 *
	 * @param string $file_path  Chemin du fichier.
	 * @param string $file_name  Nom du fichier pour le download.
	 * @param string $mime_type  Type MIME.
	 */
	private function stream_file_download( $file_path, $file_name, $mime_type ) {
		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Fichier non trouvé.', 'ai-recipe-generator-pro' ) );
		}

		// Nettoyer le buffer de sortie
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Headers
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		// Lire et envoyer le fichier
		readfile( $file_path );
	}
}
