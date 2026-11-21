<?php
/** Exercise public groups through WordPress saves, REST, queries and rendering. */
define( 'WP_DEBUG', true );
require_once '/wordpress/wp-load.php';

use WPTitleLayer\Core\ContentGroups as Groups;
use WPTitleLayer\Core\ContentGroupReconciliation as Reconciliation;
use WPTitleLayer\Core\Series;
use WPTitleLayer\Core\Sequence;
use WPTitleLayer\Reader\Archive;
use WPTitleLayer\Presentation\Renderer;

$checks = 0;
$failures = array();
function group_check( $ok, $message ) {
	global $checks, $failures;
	++$checks;
	if ( ! $ok ) { $failures[] = $message; }
}
function group_post( $series, $season, $label, $position, $status = 'publish', array $extra = array() ) {
	$id = wp_insert_post( array_merge( array( 'post_title' => 'Group article ' . $position, 'post_status' => $status,
		'meta_input' => array( 'wptl_season_key' => $season, 'wptl_series_group' => $label, 'wptl_sequence_position' => $position ),
		'tax_input' => array( 'wptl_series' => array( $series->term_id ) ),
	), $extra ) );
	Groups::flush();
	\WPTitleLayer\Core\SequenceRuntime::flush();
	return $id;
}
function group_query( $series, $group, $season = '', $page = 1, $size = 20 ) {
	global $wp_query, $wp_the_query;
	$old_query = $wp_query;
	$old_main = $wp_the_query;
	$wp_the_query = new WP_Query();
	$wp_query = $wp_the_query;
	$wp_query->query( array( 'wptl_series' => $series->slug, 'wptl_group' => (string) $group,
		'wptl_season' => $season, 'posts_per_page' => $size, 'paged' => $page ) );
	$result = $wp_query;
	$wp_query = $old_query;
	$wp_the_query = $old_main;
	return $result;
}
function group_batch( $series ) {
	$state = get_term_meta( $series->term_id, '_wptl_group_reconciliation', true );
	if ( ! is_array( $state ) ) { throw new RuntimeException( 'Missing reconciliation checkpoint.' ); }
	$args = array( $series->term_id, $state['generation'] );
	$event = wp_next_scheduled( Reconciliation::HOOK, $args );
	if ( false === $event ) { throw new RuntimeException( 'Missing reconciliation event.' ); }
	// Core unschedules a single event before calling its action.
	wp_unschedule_event( $event, Reconciliation::HOOK, $args );
	do_action( Reconciliation::HOOK, ...$args );
}

$admin = wp_insert_user( array( 'user_login' => 'groups-admin', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
wp_set_current_user( $admin );
$term = wp_insert_term( 'Rainbow', 'wptl_series' );
$series = get_term( $term['term_id'], 'wptl_series' );
update_term_meta( $series->term_id, 'wptl_series_mode', 'ordered' );
update_term_meta( $series->term_id, 'wptl_series_structure', 'seasoned' );
Sequence::persist_seasons( $series, array( array( 'key' => 's1', 'label' => 'First season', 'sort' => 1 ), array( 'key' => 's2', 'label' => 'Second season', 'sort' => 2 ) ) );
$a = group_post( $series, 's1', 'W1 看见人的软弱', 1 );
$b = group_post( $series, 's1', 'W1 看见人的软弱', 2 );
$c = group_post( $series, 's1', 'W2 看见自己的有限', 9 );
$d = group_post( $series, 's2', 'W1 看见人的软弱', 1 );
$plain = group_post( $series, 's1', '', 10 );
$draft = group_post( $series, 's1', 'W1 看见人的软弱', 3, 'draft' );
$group = Groups::for_post( $a );
group_check( $group instanceof WP_Term, 'Saving a text group did not create its stable identity.' );
if ( ! $group ) { throw new RuntimeException( implode( '; ', $failures ) ); }
group_check( Groups::for_post( $b )->term_id === $group->term_id, 'Same-scope labels did not reuse the group.' );
group_check( Groups::for_post( $d )->term_id !== $group->term_id, 'Same labels in different seasons shared identity.' );
group_check( null === Groups::for_post( $plain ), 'Blank group acquired a public group.' );
$url = Groups::url( $group, $series );
group_check( false !== strpos( $url, 'wptl_group=' . $group->term_id ) && false !== strpos( $url, 'wptl_season=1' ), 'Group URL omitted stable group or season identity.' );
$renderer = new Renderer();
$context = $renderer->context( $a );
group_check( 'Rainbow · First season · W1 看见人的软弱 · 01' === $context['eyebrow'], 'Title layer did not compose group independently from number: ' . $context['eyebrow'] );
group_check( '' === $renderer->context( $plain )['group'], 'Ungrouped title changed.' );
$archive = new Archive();
$posts = array_map( static function ( $id ) use ( $archive, $series ) { return $archive->postContext( $id, 'ordered', 'seasoned', false, false, $series ); }, array( $a, $b, $c, $plain, $a ) );
$runs = $archive->contentSegments( $posts );
group_check( 4 === count( $runs ) && 2 === count( $runs[0]['posts'] ), 'Consecutive groups were not split correctly.' );
group_check( '' === $runs[2]['label'] && $runs[3]['id'] === $group->term_id, 'Ungrouped gaps or repeated nonconsecutive groups changed article order.' );
$query = group_query( $series, $group->term_id, '1', 1, 1 );
group_check( 2 === (int) $query->found_posts && 2 === (int) $query->max_num_pages, 'Group filtering leaked other seasons, groups or drafts, or broke pagination.' );
group_check( array( $a ) === wp_list_pluck( $query->posts, 'ID' ), 'Filtered first page lost canonical order.' );
$query2 = group_query( $series, $group->term_id, '1', 2, 1 );
group_check( array( $b ) === wp_list_pluck( $query2->posts, 'ID' ), 'Filtered second page lost canonical order.' );
group_check( false !== strpos( $archive->pagination( $query ), 'wptl_group=' . $group->term_id ), 'Pagination dropped the active group filter.' );
$saved_query = $GLOBALS['wp_query'];
$GLOBALS['wp_query'] = group_query( $series, '', '1' );
ob_start();
include WPTL_PATH . 'templates/series-archive.php';
$html = ob_get_clean();
$GLOBALS['wp_query'] = $saved_query;
group_check( 2 === substr_count( $html, 'class="wptl-content-group__title"' ), 'Real archive template did not render exactly two group headings.' );
group_check( false !== strpos( $html, '<h4 class="wptl-series-list__title"' ), 'Grouped articles lack subordinate heading semantics.' );
group_check( false !== strpos( $html, 'wptl_group=' . $group->term_id ), 'Archive group heading did not include its filter URL.' );
group_check( 0 === count( group_query( $series, $group->term_id, '2' )->posts ), 'Group URL accepted an incompatible season.' );
group_check( 0 === count( group_query( $series, 'bogus' )->posts ), 'Invalid group token broadened to all articles.' );
$other = wp_insert_term( 'Other series', 'wptl_series' );
group_check( 0 === count( group_query( get_term( $other['term_id'], 'wptl_series' ), $group->term_id )->posts ), 'Foreign Series accepted a group.' );

$server = rest_get_server();
$rename = new WP_REST_Request( 'POST', '/wp-title-layer/v1/groups/' . $group->term_id );
$rename->set_param( 'post', $b );
$rename->set_param( 'label', 'W1 新组名' );
$response = $server->dispatch( $rename );
group_check( 200 === $response->get_status(), 'Group rename failed.' );
group_check( 'W1 新组名' === Groups::for_post( $b )->name && $url === Groups::url( Groups::for_post( $b ), $series ), 'Rename changed identity or failed to update other article display.' );
$get = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $b ) );
group_check( 'W1 新组名' === ( $get->get_data()['meta']['wptl_series_group'] ?? '' ), 'REST read returned the obsolete label after rename.' );
$new = group_post( $series, 's1', 'W1 新组名', 4 );
group_check( $group->term_id === Groups::for_post( $new )->term_id, 'Renamed label created another group.' );
Groups::sync( $a );
group_check( $group->term_id === Groups::for_post( $a )->term_id, 'Old Publisher text undid a group rename.' );
$rename->set_param( 'label', 'W2 看见自己的有限' );
group_check( 409 === $server->dispatch( $rename )->get_status(), 'Rename merged conflicting groups silently.' );

$edit = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $plain );
$edit->set_param( 'meta', array( 'wptl_series_group' => 'W1 新组名' ) );
group_check( 200 === $server->dispatch( $edit )->get_status() && $group->term_id === Groups::for_post( $plain )->term_id, 'Publisher REST text did not resolve a group before response.' );
$edit->set_param( 'meta', array( 'wptl_subtitle' => 'Independent subtitle' ) );
$server->dispatch( $edit );
group_check( $group->term_id === Groups::for_post( $plain )->term_id, 'Omitted group field cleared the group.' );
$edit->set_param( 'meta', array( 'wptl_series_group' => '' ) );
$server->dispatch( $edit );
group_check( null === Groups::for_post( $plain ) && ! get_post_meta( $plain, '_wptl_group_id', true ), 'Explicit clearing left a filterable group membership.' );
update_post_meta( $a, 'wptl_season_key', 's2' );
Groups::flush();
group_check( $group->term_id !== Groups::for_post( $a )->term_id, 'Changing season retained the former scope identity.' );
wp_set_current_user( 0 );
group_check( 401 === $server->dispatch( $rename )->get_status(), 'Anonymous caller could rename groups.' );
group_check( 401 === $server->dispatch( new WP_REST_Request( 'GET', '/wp-title-layer/v1/groups' ) )->get_status(), 'Anonymous caller could list editorial groups.' );

wp_set_current_user( $admin );
foreach ( array( 'ordered', 'unordered' ) as $mode ) {
	foreach ( array( 'flat', 'seasoned' ) as $structure ) {
		$result = wp_insert_term( 'Shape ' . $mode . $structure, 'wptl_series' );
		$shape = get_term( $result['term_id'], 'wptl_series' );
		update_term_meta( $shape->term_id, 'wptl_series_mode', $mode );
		update_term_meta( $shape->term_id, 'wptl_series_structure', $structure );
		if ( 'seasoned' === $structure ) { Sequence::persist_seasons( $shape, array( array( 'key' => 's1', 'label' => 'Season', 'sort' => 1 ) ) ); }
		$season = 'seasoned' === $structure ? 's1' : '';
		$first = group_post( $shape, $season, 'Unit A', 1 );
		$second = group_post( $shape, $season, 'Unit B', 2 );
		$unit = Groups::for_post( $first );
		group_check( $unit && array( $first ) === wp_list_pluck( group_query( $shape, $unit->term_id )->posts, 'ID' ), 'Group filtering failed for ' . $mode . '/' . $structure );
		// Activate advanced structure without altering the grouping model.
		update_term_meta( $shape->term_id, 'wptl_book_structure_version', 1 );
		$intro = group_post( $shape, $season, 'Unit A', 0 );
		update_post_meta( $intro, 'wptl_series_role', 'intro' );
		update_post_meta( $intro, 'wptl_series_scope', 'seasoned' === $structure ? 'season' : 'series' );
		Groups::flush();
		\WPTitleLayer\Core\SequenceRuntime::flush();
		$ids = wp_list_pluck( group_query( $shape, $unit->term_id )->posts, 'ID' );
		group_check( array( $first ) === $ids, 'Non-main articles leaked into group for ' . $mode . '/' . $structure );
		group_check( null === Groups::for_post( $intro ) && 'Unit A' === get_post_meta( $intro, 'wptl_series_group', true ), 'Inactive group was displayed or destructively cleared.' );
		update_post_meta( $intro, '_wptl_group_id', $unit->term_id );
		group_check( array( $first ) === wp_list_pluck( group_query( $shape, $unit->term_id )->posts, 'ID' ), 'Pre-upgrade group ID leaked a non-main article.' );
		update_post_meta( $intro, 'wptl_series_role', 'article' );
		Groups::flush();
		group_check( Groups::for_post( $intro ) && $unit->term_id === Groups::for_post( $intro )->term_id, 'Returning to main article did not restore its saved group.' );
	}
}

update_term_meta( $shape->term_id, 'wptl_series_structure', 'flat' );
Groups::flush();
group_batch( $shape );
group_check( Groups::for_post( $first ) && '' === Groups::post_scope( $first, $shape ), 'Changing Series structure left existing groups unresolved.' );

// A structural save must not reconcile all existing posts synchronously.
$large_result = wp_insert_term( 'Reconciliation fixture', 'wptl_series' );
$large = get_term( $large_result['term_id'], 'wptl_series' );
$members = array();
for ( $i = 0; $i < 107; ++$i ) {
	$members[] = group_post( $large, 's1', 'Scoped unit', $i + 1, 106 === $i ? 'draft' : 'publish' );
}
$flat_id = (int) get_post_meta( $members[0], '_wptl_group_id', true );
Sequence::persist_seasons( $large, array( array( 'key' => 's1', 'label' => 'First season', 'sort' => 1 ) ) );
update_term_meta( $large->term_id, 'wptl_series_structure', 'seasoned' );
Groups::flush();
$state = get_term_meta( $large->term_id, '_wptl_group_reconciliation', true );
$generation = $state['generation'];
group_check( 0 === $state['after'], 'Saving the structure advanced the background cursor.' );
group_check( $flat_id === (int) get_post_meta( $members[0], '_wptl_group_id', true ) && $flat_id === (int) get_post_meta( $members[106], '_wptl_group_id', true ), 'Saving structure immediately rewrote group IDs.' );
group_check( 0 === (int) group_query( $large, $flat_id )->found_posts, 'Old flat membership leaked during the background rebuild.' );

group_batch( $large );
$state = get_term_meta( $large->term_id, '_wptl_group_reconciliation', true );
$season_id = (int) get_post_meta( $members[0], '_wptl_group_id', true );
group_check( $season_id > 0 && $flat_id !== $season_id && $members[49] === $state['after'], 'First worker did not stop exactly at the 50-member checkpoint.' );
group_check( $flat_id === (int) get_post_meta( $members[50], '_wptl_group_id', true ) && $flat_id === (int) get_post_meta( $members[106], '_wptl_group_id', true ), 'First batch touched unprocessed members.' );

$interrupt = static function ( $meta_id, $object_id, $meta_key ) use ( $members ) {
	if ( '_wptl_group_id' === $meta_key && $members[53] === (int) $object_id ) { throw new RuntimeException( 'simulated_worker_interruption' ); }
};
add_action( 'updated_post_meta', $interrupt, 5, 3 );
$interrupted = false;
try { group_batch( $large ); } catch ( RuntimeException $error ) {
	if ( 'simulated_worker_interruption' !== $error->getMessage() ) { throw $error; }
	$interrupted = true;
} finally { remove_action( 'updated_post_meta', $interrupt, 5 ); }
group_check( $interrupted && $state === get_term_meta( $large->term_id, '_wptl_group_reconciliation', true ), 'Interrupted batch did not retain its prior checkpoint.' );
group_check( false !== wp_next_scheduled( Reconciliation::HOOK, array( $large->term_id, $generation ) ), 'Interrupted batch lost its retry.' );
group_batch( $large );
group_check( $members[99] === get_term_meta( $large->term_id, '_wptl_group_reconciliation', true )['after'], 'Retry did not resume from the previous successfully committed batch.' );
group_batch( $large );
group_check( ! metadata_exists( 'term', $large->term_id, '_wptl_group_reconciliation' ) && false === wp_next_scheduled( Reconciliation::HOOK, array( $large->term_id, $generation ) ), 'Completed job retained its checkpoint or retry event.' );
$indexed = array_map( static function ( $id ) { return (int) get_post_meta( $id, '_wptl_group_id', true ); }, $members );
group_check( array( $season_id ) === array_values( array_unique( $indexed ) ), 'Completed batches missed members, including the draft.' );
group_check( 106 === (int) group_query( $large, $season_id, '1' )->found_posts, 'Group archive lost published members or exposed draft content.' );

// The superseded worker must leave the latest structure and cursor alone.
update_term_meta( $large->term_id, 'wptl_series_structure', 'flat' );
group_check( 0 === (int) group_query( $large, $season_id )->found_posts, 'Inactive season group leaked during the background rebuild.' );
$stale = get_term_meta( $large->term_id, '_wptl_group_reconciliation', true );
group_batch( $large );
update_term_meta( $large->term_id, 'wptl_series_structure', 'seasoned' );
$latest = get_term_meta( $large->term_id, '_wptl_group_reconciliation', true );
do_action( Reconciliation::HOOK, $large->term_id, $stale['generation'] );
group_check( $latest !== $stale && 0 === $latest['after'] && $latest === get_term_meta( $large->term_id, '_wptl_group_reconciliation', true ), 'Superseded worker did not yield to the latest generation.' );
group_batch( $large ); group_batch( $large ); group_batch( $large );
group_check( $season_id === (int) get_post_meta( $members[0], '_wptl_group_id', true ), 'Latest generation did not restore the original stable season group.' );
group_check( 'Scoped unit' === get_post_meta( $members[106], 'wptl_series_group', true ), 'Background reconciliation modified canonical group text.' );

$author = wp_insert_user( array( 'user_login' => 'group-author', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$restricted = array();
foreach ( array( 'draft', 'private', 'pending', 'future', 'protected' ) as $index => $visibility ) {
	$extra = 'protected' === $visibility ? array( 'post_password' => 'fixture-protected' ) : array();
	if ( 'future' === $visibility ) { $extra['post_date'] = date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ); }
	$restricted[ $visibility ] = group_post( $series, 's1', 'Restricted unit ' . $visibility, 20 + $index, 'protected' === $visibility ? 'publish' : $visibility, $extra );
}
$private_group = Groups::for_post( $restricted['private'] );
$protected_group = Groups::for_post( $restricted['protected'] );
$own_draft = group_post( $series, 's1', 'Own draft group', 30, 'draft', array( 'post_author' => $author ) );
$own_group = Groups::for_post( $own_draft );
wp_set_current_user( $author );
$suggestions = new WP_REST_Request( 'GET', '/wp-title-layer/v1/groups' );
$suggestions->set_param( 'series', $series->term_id );
$suggestions->set_param( 'season', 's1' );
$view = $server->dispatch( $suggestions );
$labels = wp_list_pluck( $view->get_data(), 'label' );
group_check( 200 === $view->get_status() && in_array( 'W1 新组名', $labels, true ), 'Assign-only author could not load a public group.' );
group_check( 'private, no-store' === ( $view->get_headers()['Cache-Control'] ?? '' ), 'User-specific suggestions can be cached publicly.' );
group_check( ! in_array( 'W1 看见人的软弱', $labels, true ), 'Historical group alias was suggested.' );
foreach ( $view->get_data() as $row ) { group_check( ! array_key_exists( 'aliases', $row ), 'Group listing disclosed historical aliases.' ); }
foreach ( $restricted as $visibility => $protected_post ) {
	$suggestions->set_param( 'post', $protected_post );
	$labels = wp_list_pluck( $server->dispatch( $suggestions )->get_data(), 'label' );
	group_check( ! in_array( 'Restricted unit ' . $visibility, $labels, true ), 'Unauthorized post context revealed a restricted group: ' . $visibility );
}
$suggestions->set_param( 'post', $own_draft );
$own_rows = array_column( $server->dispatch( $suggestions )->get_data(), null, 'id' );
group_check( 'Own draft group' === ( $own_rows[ $own_group->term_id ]['label'] ?? null ) && false === ( $own_rows[ $own_group->term_id ]['can_rename'] ?? null ), 'Author cannot see their editable draft or gained shared rename rights.' );
$suggestions->set_param( 'season', 's2' );
group_check( ! in_array( 'Own draft group', wp_list_pluck( $server->dispatch( $suggestions )->get_data(), 'label' ), true ), 'Current draft group leaked outside the saved season.' );
$suggestions->set_param( 'season', array( 's1' ) );
group_check( 400 === $server->dispatch( $suggestions )->get_status(), 'Malformed season argument was not rejected.' );
group_check( 403 === $server->dispatch( $rename )->get_status(), 'Assign-only author could rename a shared group.' );
( new WP_User( $author ) )->add_cap( get_taxonomy( 'wptl_series' )->cap->edit_terms );
wp_set_current_user( 0 );
wp_set_current_user( $author );
$private_rename = new WP_REST_Request( 'POST', '/wp-title-layer/v1/groups/' . $private_group->term_id );
$private_rename->set_param( 'label', 'Changed label' );
$private_rename->set_param( 'post', $restricted['private'] );
group_check( 403 === $server->dispatch( $private_rename )->get_status(), 'Term capability bypassed the post permission on rename.' );
$private_rename->set_param( 'post', $own_draft );
group_check( 403 === $server->dispatch( $private_rename )->get_status(), 'Unrelated editable post authorized another group rename.' );
wp_set_current_user( 0 );
$unlocked = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $restricted['protected'] );
$protected_rest = $server->dispatch( $unlocked );
group_check( 200 === $protected_rest->get_status() && ! isset( $protected_rest->get_data()['meta']['wptl_series_group'] ), 'Protected article REST output did not retain the public shell without its group.' );
foreach ( array( $private_group, $protected_group ) as $hidden_group ) {
	$hidden_query = group_query( $series, $hidden_group->term_id, '1' );
	group_check( '' === $archive->context( $series, $hidden_query )['active_group'], 'Empty or restricted group archive disclosed the group name.' );
}
group_check( '' === $archive->postContext( $restricted['protected'], 'ordered', 'seasoned', false, false, $series )['group_label'], 'Public archive card exposed the protected post group.' );
group_check( 'W1 新组名' === $archive->context( $series, group_query( $series, $group->term_id, '1' ) )['active_group'], 'A valid public group lost its archive heading.' );
wp_set_current_user( $admin );
$editable = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $restricted['protected'] );
$editable->set_param( 'context', 'edit' );
group_check( 'Restricted unit protected' === ( $server->dispatch( $editable )->get_data()['meta']['wptl_series_group'] ?? null ), 'Editable protected article lost Publisher readback.' );

$cap_request = new WP_REST_Request( 'GET', '/wp/v2/wptl_series/' . $shape->term_id );
$cap_response = $server->dispatch( $cap_request );
group_check( array( 'version' => 1, 'book_structure' => true, 'content_groups' => 'article-only' ) === ( $cap_response->get_data()['wptl_capabilities'] ?? null ), 'Enabled Series did not expose the semantic public capability.' );
$cap_write = new WP_REST_Request( 'POST', '/wp/v2/wptl_series/' . $shape->term_id );
$cap_write->set_param( 'wptl_capabilities', array( 'version' => 1, 'book_structure' => false ) );
$server->dispatch( $cap_write );
group_check( true === ( $server->dispatch( $cap_request )->get_data()['wptl_capabilities']['book_structure'] ?? null ), 'Client could overwrite computed capability.' );
$plain_cap = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/wptl_series/' . $other['term_id'] ) );
group_check( false === ( $plain_cap->get_data()['wptl_capabilities']['book_structure'] ?? null ), 'Non-enabled Series advertised advanced capability.' );

if ( $failures ) { fwrite( STDERR, implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo 'Irisable Title Layer content-group checks passed: ' . $checks . "\n";
