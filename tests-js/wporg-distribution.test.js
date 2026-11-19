const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );

const read = ( file ) => fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' );

test( 'WordPress.org distribution uses native update eligibility and the owner account', () => {
	assert.doesNotMatch( read( 'wp-title-layer.php' ), /\*\s*Update URI:/ );
	assert.match( read( 'readme.txt' ), /^Contributors: irisable$/m );
	assert.match( read( 'readme.txt' ), /== Privacy and external services ==/ );
	assert.match( read( 'readme.txt' ), /https:\/\/github\.com\/irisable\/wp-title-layer/ );
} );

test( 'Archive output escapes its selected heading and avoids the obsolete taxonomy argument', () => {
	assert.doesNotMatch( read( 'templates/series-archive.php' ), /echo \$wptl_heading;/ );
	assert.match( read( 'templates/series-archive.php' ), /echo esc_html\( \$wptl_heading \);/ );
	assert.match( read( 'includes/Reader/Archive.php' ), /term_description\( \(int\) \$term->term_id \)/ );
} );

test( 'review fixes scope styles, notices and title filtering without changing persisted identities', () => {
	const columns = read( 'includes/Admin/PostListColumns.php' );
	assert.doesNotMatch( columns, /<style>/ );
	assert.match( columns, /admin_enqueue_scripts/ );
	assert.match( columns, /'edit.php' !== \$hook_suffix/ );
	assert.match( read( 'assets/post-list.css' ), /column-wptl_subtitle/ );
	const theme = read( 'includes/Presentation/ThemeTitleIntegration.php' );
	const register = theme.slice( theme.indexOf( 'public function register()' ), theme.indexOf( 'public function markQueryLoopDescendant' ) );
	assert.doesNotMatch( register, /add_filter\( 'the_title'/ );
	assert.match( theme, /remove_filter\( 'the_title'/ );
	const callback = theme.slice( theme.indexOf( 'public function filterKadenceTitle' ), theme.indexOf( 'public function finishKadenceTitle' ) );
	assert.doesNotMatch( callback, /return \$original;/ );
	assert.equal( [ ...callback.matchAll( /return wp_kses\( \$original, Renderer::classicTitleHtml\(\) \);/g ) ].length, 3 );
	assert.match( callback, /\$title_escaped = wp_kses\(/ );
	assert.match( read( 'includes/Plugin.php' ), /add_action\( 'init', array\( __CLASS__, 'register_translations' \), 0 \)/ );
	assert.match( read( 'includes/Core/ContentGroups.php' ), /TAXONOMY = 'wptl_content_group'/ );
} );

test( 'Series structure changes queue paginated reindexing outside the save request', () => {
	const source = read( 'includes/Core/ContentGroupReconciliation.php' );
	const trigger = source.slice( source.indexOf( 'function queue_structure_change' ), source.indexOf( 'function run_batch' ) );
	assert.doesNotMatch( trigger, /get_objects_in_term|ContentGroups::sync|\$wpdb/ );
	assert.match( source, /object_id > %d ORDER BY object_id ASC LIMIT %d/ );
	assert.match( source, /wp_schedule_single_event/ );
} );
