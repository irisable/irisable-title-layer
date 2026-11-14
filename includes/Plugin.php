<?php
/**
 * Main application bootstrap.
 *
 * @package WPTitleLayer
 */

namespace WPTitleLayer;

use WPTitleLayer\Admin\ControlCenter;
use WPTitleLayer\Admin\ChannelHealth;
use WPTitleLayer\Admin\ClassicEditor;
use WPTitleLayer\Admin\PostListColumns;
use WPTitleLayer\Admin\SequenceManager;
use WPTitleLayer\Admin\SeriesHealth;
use WPTitleLayer\Core\Bootstrap as Core_Bootstrap;
use WPTitleLayer\Integrations\Bootstrap as Integrations_Bootstrap;
use WPTitleLayer\Migration\Bootstrap as Migration_Bootstrap;
use WPTitleLayer\Presentation\Bootstrap as Presentation_Bootstrap;
use WPTitleLayer\Reader\Bootstrap as Reader_Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates independent plugin modules.
 */
final class Plugin {
	/**
	 * Register hooks after all plugins are available for conflict detection.
	 *
	 * @return void
	 */
	public static function register() {
		$legacy_active = function_exists( 'get_secondary_title' );

		add_action( 'init', array( __CLASS__, 'register_translations' ), 0 );

		Core_Bootstrap::register( ! $legacy_active );
		Migration_Bootstrap::register();
		ControlCenter::register( $legacy_active );

		if ( $legacy_active ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_legacy_notice' ) );
			return;
		}

		require_once WPTL_PATH . 'includes/compat.php';
		ChannelHealth::register();
		SeriesHealth::register();
		SequenceManager::register();
		PostListColumns::register();
		ClassicEditor::register();
		Presentation_Bootstrap::register();
		Reader_Bootstrap::register();
		Integrations_Bootstrap::register();
	}

	/** Register the bundled fallback for manual installs; core language packs take priority. */
	public static function register_translations(): void {
		load_plugin_textdomain( 'irisable-title-layer', false, dirname( plugin_basename( WPTL_FILE ) ) . '/languages' );
	}

	/**
	 * Warn administrators while the legacy plugin still owns title output.
	 *
	 * @return void
	 */
	public static function render_legacy_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! current_user_can( 'manage_options' ) || ! $screen instanceof \WP_Screen
			|| ( ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true )
				&& false === strpos( $screen->id, '_page_wp-title-layer' ) ) ) {
			return;
		}

		$url = admin_url( 'tools.php?page=wp-title-layer-migration' );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Irisable Title Layer is in migration-only mode.', 'irisable-title-layer' ); ?></strong>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: migration page URL. */
						__( 'Secondary Title is still active, so Irisable Title Layer has disabled its editor and front-end output to prevent duplicate titles. <a href="%s">Review the migration report</a>, then deactivate Secondary Title before switching output.', 'irisable-title-layer' ),
						esc_url( $url )
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
