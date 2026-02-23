<?php
/**
 * Block Registry - Register custom blocks and block variants
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Block_Registry {

	/**
	 * Registered block variants
	 *
	 * @var array
	 */
	private $block_variants = array();

	/**
	 * Initialize the block registry
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block_variants' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'init', array( $this, 'register_block_styles' ) );
	}

	/**
	 * Register block variants
	 */
	public function register_block_variants() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$config_file = $folder . '/block.json';
			$style_file = $folder . '/style.css';

			// Load block configuration
			$config = $this->load_block_config( $slug, $config_file );

			if ( ! $config ) {
				continue;
			}

			// Register block style
			// CSS will be loaded via enqueue_block_variant_styles()
			// No inline style needed if CSS file exists
			register_block_style(
				$config['block'],
				array(
					'name'  => $config['name'],
					'label' => $config['label'],
				)
			);

			// Store for admin display
			$this->block_variants[] = array(
				'slug'        => $slug,
				'block'       => $config['block'],
				'name'        => $config['name'],
				'label'       => $config['label'],
				'type'        => $config['type'] ?? 'variant',
				'description' => $config['description'] ?? '',
				'folder'      => $folder,
				'has_style'   => file_exists( $style_file ),
			);
		}
	}

	/**
	 * Load block configuration from JSON or use defaults
	 *
	 * @param string $slug Block variant slug
	 * @param string $config_file Path to block.json
	 * @return array|null
	 */
	private function load_block_config( $slug, $config_file ) {
		if ( file_exists( $config_file ) ) {
			$config = json_decode( file_get_contents( $config_file ), true );
			if ( $config ) {
				return $config;
			}
		}

		// Fallback: Use defaults based on folder name
		// For checkmark-list variant
		if ( $slug === 'checkmark-list' ) {
			return array(
				'block'       => 'core/list',
				'name'        => 'checkmark-list',
				'label'       => __( 'Checkmark', 'gutenblock-pro' ),
				'type'        => 'variant',
				'description' => __( 'Zeigt Checkmarks (✓) statt Bullets für alle Listenelemente', 'gutenblock-pro' ),
			);
		}

		return null;
	}

	/**
	 * Get all registered block variants
	 *
	 * @return array
	 */
	public function get_block_variants() {
		// If variants not yet populated, discover them from filesystem
		if ( empty( $this->block_variants ) ) {
			$this->discover_block_variants();
		}
		return $this->block_variants;
	}

	/**
	 * Discover block variants from filesystem
	 */
	private function discover_block_variants() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$config_file = $folder . '/block.json';
			$style_file = $folder . '/style.css';

			$config = $this->load_block_config( $slug, $config_file );

			if ( $config ) {
				$this->block_variants[] = array(
					'slug'        => $slug,
					'block'       => $config['block'],
					'name'        => $config['name'],
					'label'       => $config['label'],
					'type'        => $config['type'] ?? 'variant',
					'description' => $config['description'] ?? '',
					'folder'      => $folder,
					'has_style'   => file_exists( $style_file ),
				);
			}
		}
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_assets() {
		$this->enqueue_block_variant_styles( 'editor' );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		$this->enqueue_block_variant_styles( 'frontend' );
	}

	/**
	 * Register block styles for FSE iframe support
	 */
	public function register_block_styles() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$config_file = $folder . '/block.json';
			$style_file = $folder . '/style.css';

			if ( ! file_exists( $style_file ) ) {
				continue;
			}

			$config = $this->load_block_config( $slug, $config_file );
			if ( ! $config ) {
				continue;
			}

			// Register for FSE iframe support
			wp_enqueue_block_style(
				$config['block'],
				array(
					'handle' => 'gutenblock-pro-block-' . $slug,
					'src'    => GUTENBLOCK_PRO_URL . 'blocks/' . $slug . '/style.css',
					'ver'    => filemtime( $style_file ),
					'path'   => $style_file,
				)
			);

			$custom = gutenblock_pro_custom_block_file( $slug );
			if ( file_exists( $custom['path'] ) ) {
				wp_enqueue_block_style(
					$config['block'],
					array(
						'handle' => 'gutenblock-pro-block-' . $slug . '-custom',
						'src'    => $custom['url'],
						'ver'    => filemtime( $custom['path'] ),
						'path'   => $custom['path'],
					)
				);
			}
		}
	}

	/**
	 * Enqueue styles for all block variants
	 *
	 * @param string $context 'editor' or 'frontend'
	 */
	private function enqueue_block_variant_styles( $context = 'frontend' ) {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$style_file = $folder . '/style.css';

			if ( file_exists( $style_file ) ) {
				$handle = 'gutenblock-pro-block-' . $slug;
				wp_enqueue_style(
					$handle,
					GUTENBLOCK_PRO_URL . 'blocks/' . $slug . '/style.css',
					array(),
					filemtime( $style_file )
				);

				$custom = gutenblock_pro_custom_block_file( $slug );
				if ( file_exists( $custom['path'] ) ) {
					wp_enqueue_style(
						$handle . '-custom',
						$custom['url'],
						array( $handle ),
						filemtime( $custom['path'] )
					);
				}
			}
		}
	}
}
