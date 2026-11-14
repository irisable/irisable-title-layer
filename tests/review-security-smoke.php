<?php
/** Review regression tests using real WordPress authentication and enqueue APIs. */
define( 'WP_DEBUG', true );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
define( 'DOING_AJAX', true ); // Keep wp_send_json on the interceptable WordPress wp_die path.

use WPTitleLayer\Admin\PostListColumns;
use WPTitleLayer\Admin\SequenceManager;
use WPTitleLayer\Migration\AdminPage;
use WPTitleLayer\Migration\AjaxController;
use WPTitleLayer\Presentation\ThemeTitleIntegration;

$checks = 0;
$assert = static function ( $value, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $value ) { throw new RuntimeException( $message ); }
};
$die = static function () { return static function () { throw new RuntimeException( 'request_denied' ); }; };
add_filter( 'wp_die_handler', $die );
add_filter( 'wp_die_ajax_handler', $die );
add_filter( 'wp_die_json_handler', $die );
$invoke = static function ( $class, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( $class, $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
};
$denied = static function ( $callback, $label ) use ( $assert ) {
	$blocked = false;
	ob_start();
	try { $callback(); } catch ( RuntimeException $error ) {
		if ( 'request_denied' !== $error->getMessage() ) { throw $error; }
		$blocked = true;
	} finally { ob_end_clean(); }
	$assert( $blocked, $label );
};
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( 0 !== ( error_reporting() & $severity ) && false !== strpos( $file, '/plugins/wp-title-layer/' ) ) {
		throw new RuntimeException( "$message at $file:$line" );
	}
	return false;
} );
wp_set_current_user( 1 );
$subscriber = wp_insert_user( array( 'user_login' => 'review-subscriber', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$assert( ! is_wp_error( $subscriber ), 'Subscriber fixture failed.' );

// Every browser mutation handler must deny missing, malformed, or invalid nonce input.
$actions = array( 'initialize', 'continue', 'rollback', 'move', 'undo', 'book_enable' );
foreach ( $actions as $action ) {
	foreach ( array( null, array( 'invalid' ), 'invalid' ) as $nonce ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array( 'series_id' => 123 );
		if ( null !== $nonce ) { $_POST['_wpnonce'] = $nonce; }
		$_REQUEST = $_POST;
		$denied( static function () use ( $action ) { call_user_func( array( SequenceManager::class, 'handle_' . $action ) ); }, "Sequence $action accepted an invalid nonce." );
	}
}
foreach ( array( 'ajax_move', 'ajax_search' ) as $method ) {
	foreach ( array( null, array( 'invalid' ), 'invalid' ) as $nonce ) {
		$_POST = array( 'series_id' => 123 );
		if ( null !== $nonce ) { $_POST['nonce'] = $nonce; }
		$_REQUEST = $_POST;
		$denied( static function () use ( $method ) { call_user_func( array( SequenceManager::class, $method ) ); }, "$method accepted an invalid nonce." );
	}
}
$_POST = array( '_wpnonce' => wp_create_nonce( 'wptl_sequence_move_123' ), 'nonce' => wp_create_nonce( 'wptl_sequence_move_123' ) );
$_REQUEST = $_POST;
$invoke( SequenceManager::class, 'verify_action', array( 'wptl_sequence_move', 123 ) );
$invoke( SequenceManager::class, 'verify_ajax', array( 123 ) );
$assert( true, 'Valid sequence credentials accepted.' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$denied( static function () use ( $invoke ) { $invoke( SequenceManager::class, 'verify_action', array( 'wptl_sequence_move', 123 ) ); }, 'Sequence accepted GET with a valid nonce.' );
$denied( static function () use ( $invoke ) { $invoke( SequenceManager::class, 'verify_ajax', array( 123 ) ); }, 'Sequence AJAX accepted GET.' );
$_SERVER['REQUEST_METHOD'] = 'POST';
wp_set_current_user( $subscriber );
$_POST = array( '_wpnonce' => wp_create_nonce( 'wptl_sequence_move_123' ), 'nonce' => wp_create_nonce( 'wptl_sequence_move_123' ) );
$_REQUEST = $_POST;
$denied( static function () use ( $invoke ) { $invoke( SequenceManager::class, 'verify_action', array( 'wptl_sequence_move', 123 ) ); }, 'Sequence accepted a subscriber nonce.' );
$denied( static function () use ( $invoke ) { $invoke( SequenceManager::class, 'verify_ajax', array( 123 ) ); }, 'Sequence AJAX accepted a subscriber nonce.' );

foreach ( array( AdminPage::class, AjaxController::class ) as $class ) {
	$args = AdminPage::class === $class ? array( 'wptl_review' ) : array();
	wp_set_current_user( 1 );
	foreach ( array( null, array( 'invalid' ), 'invalid' ) as $nonce ) {
		$_POST = null === $nonce ? array() : array( '_wpnonce' => $nonce, 'nonce' => $nonce );
		$_REQUEST = $_POST;
		$denied( static function () use ( $invoke, $class, $args ) { $invoke( $class, 'authorize', $args ); }, 'Migration accepted an invalid nonce.' );
	}
	$_POST = array( '_wpnonce' => wp_create_nonce( 'wptl_review' ), 'nonce' => wp_create_nonce( 'wptl_migration_ajax' ) );
	$_REQUEST = $_POST;
	$invoke( $class, 'authorize', $args );
	$assert( true, 'Valid migration credentials accepted.' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$denied( static function () use ( $invoke, $class, $args ) { $invoke( $class, 'authorize', $args ); }, 'Migration accepted GET.' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	wp_set_current_user( $subscriber );
	$_POST = array( '_wpnonce' => wp_create_nonce( 'wptl_review' ), 'nonce' => wp_create_nonce( 'wptl_migration_ajax' ) );
	$_REQUEST = $_POST;
	$denied( static function () use ( $invoke, $class, $args ) { $invoke( $class, 'authorize', $args ); }, 'Migration accepted a subscriber nonce.' );
}

wp_set_current_user( 1 );
foreach ( array( 'dashboard' => 'index.php', 'post' => 'post.php', 'edit-page' => 'edit.php' ) as $screen => $hook ) {
	set_current_screen( $screen );
	PostListColumns::enqueueStyles( $hook );
	$assert( ! wp_style_is( 'wptl-post-list', 'enqueued' ), 'Post-list CSS leaked to ' . $screen );
}
set_current_screen( 'edit-post' );
ob_start(); PostListColumns::enqueueStyles( 'edit.php' ); $output = ob_get_clean();
$assert( '' === $output && wp_style_is( 'wptl-post-list', 'enqueued' ), 'Post-list CSS was not silently enqueued.' );
foreach ( array( 'dashboard' => false, 'post' => false, 'plugins' => true, 'tools_page_wp-title-layer-migration' => true ) as $screen => $visible ) {
	set_current_screen( $screen );
	ob_start(); WPTitleLayer\Plugin::render_legacy_notice(); $notice = ob_get_clean();
	$assert( $visible === ( '' !== $notice ), 'Migration notice scope incorrect: ' . $screen );
}
wp_set_current_user( $subscriber );
set_current_screen( 'plugins' );
ob_start(); WPTitleLayer\Plugin::render_legacy_notice(); $notice = ob_get_clean();
$assert( '' === $notice, 'Subscriber saw an administrative notice.' );

// Plugin-owned output is escaped; unrelated filter values remain byte-for-byte intact.
$integration = new ThemeTitleIntegration();
$integration->register();
$assert( false === has_filter( 'the_title', array( $integration, 'filterKadenceTitle' ) ), 'A global title filter was installed at bootstrap.' );
$original = '<em>A &amp; B</em>';
$assert( $original === $integration->filterKadenceTitle( $original, 17 ), 'Unrelated title formatting was changed.' );
foreach ( array( 'kadence_post_id' => 17, 'kadence_fragments' => array(
	'before' => '<p class="wptl-context"><a href="javascript:alert(1)" onclick="bad()">Context</a></p>',
	'title' => '<span class="wptl-title" onclick="bad()">A &amp; B</span><img src=x onerror="bad()">',
	'after' => '<p onclick="bad()">Subtitle</p>',
) ) as $property => $value ) {
	$reflection = new ReflectionProperty( $integration, $property );
	$reflection->setAccessible( true );
	$reflection->setValue( $integration, $value );
}
add_filter( 'the_title', array( $integration, 'filterKadenceTitle' ), 99, 2 );
ob_start(); $title = $integration->filterKadenceTitle( $original, 17 ); $integration->finishKadenceTitle(); $context = ob_get_clean();
$assert( false === strpos( $title . $context, 'onclick' ) && false === strpos( $title . $context, 'javascript:' ), 'Unsafe output escaped the title allow-list.' );
$assert( false === strpos( $title, '<img' ) && false !== strpos( $title, 'A &amp; B' ), 'Heading allow-list or entity handling regressed.' );
$assert( false === has_filter( 'the_title', array( $integration, 'filterKadenceTitle' ) ), 'Title filter remained after rendering.' );
restore_error_handler();
echo "Review security checks passed: $checks\n";
