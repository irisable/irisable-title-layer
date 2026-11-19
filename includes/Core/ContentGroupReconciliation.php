<?php
/** Resume large Series membership reindexing outside the editor request. */
namespace WPTitleLayer\Core;

defined( 'ABSPATH' ) || exit;

final class ContentGroupReconciliation {
	public const HOOK = 'wptl_reconcile_content_groups';
	private const STATE = '_wptl_group_reconciliation';
	private const BATCH_SIZE = 50;

	public static function register(): void {
		foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'queue_structure_change' ), 40, 3 );
		}
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ), 10, 2 );
	}

	public static function queue_structure_change( $meta_id, int $term_id, string $key ): void {
		if ( ! in_array( $key, array( Schema::TERM_META_SERIES_STRUCTURE, Schema::TERM_META_SEASONS ), true ) ) { return; }
		$taxonomy = Series::claimed_taxonomy();
		if ( '' === $taxonomy || ! get_term( $term_id, $taxonomy ) instanceof \WP_Term ) { return; }
		$previous = get_term_meta( $term_id, self::STATE, true );
		$state = array( 'generation' => wp_generate_uuid4(), 'after' => 0 );
		// Arm an event before persisting its checkpoint. If WP-Cron rejects the
		// replacement, a previously scheduled worker can still pick up the job.
		$scheduled = self::schedule( $term_id, $state['generation'], 5 );
		if ( update_term_meta( $term_id, self::STATE, $state ) && $scheduled && is_array( $previous ) && ! empty( $previous['generation'] ) ) {
			wp_clear_scheduled_hook( self::HOOK, array( $term_id, $previous['generation'] ) );
		}
	}

	public static function run_batch( int $term_id, string $generation ): void {
		$state = get_term_meta( $term_id, self::STATE, true );
		if ( ! is_array( $state ) || empty( $state['generation'] ) ) { return; }
		if ( $generation !== $state['generation'] ) {
			self::schedule( $term_id, $state['generation'], 5 );
			return;
		}
		$taxonomy = Series::claimed_taxonomy();
		$series = '' === $taxonomy ? null : get_term( $term_id, $taxonomy );
		if ( ! $series instanceof \WP_Term ) { return; }

		// Core removes the fired single event before invoking us. Arm the next
		// one first, so interruption retries the same uncommitted batch.
		self::schedule( $term_id, $generation, 30 );
		global $wpdb;
		// The term_relationships primary key gives stable object IDs, without
		// OFFSET drift, full member hydration, or filtering out drafts. Always
		// read fresh relationships because editors can change them between runs.
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d AND object_id > %d ORDER BY object_id ASC LIMIT %d",
			(int) $series->term_taxonomy_id,
			(int) $state['after'],
			self::BATCH_SIZE
		) );
		if ( '' !== $wpdb->last_error ) { return; }
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( '' === (string) get_post_meta( $id, Schema::META_SERIES_GROUP, true ) && ! metadata_exists( 'post', $id, Schema::META_GROUP_ID ) ) { continue; }
			if ( ! ContentGroups::sync( $id ) ) { return; }
		}

		if ( count( $ids ) < self::BATCH_SIZE ) {
			// Delete only our generation and cursor. A concurrent editor's reset
			// must never be removed by a worker for the former structure.
			if ( delete_term_meta( $term_id, self::STATE, $state ) ) {
				wp_clear_scheduled_hook( self::HOOK, array( $term_id, $generation ) );
			}
			return;
		}
		update_term_meta( $term_id, self::STATE, array( 'generation' => $generation, 'after' => (int) end( $ids ) ), $state );
	}

	private static function schedule( int $term_id, string $generation, int $delay ): bool {
		$args = array( $term_id, $generation );
		return false !== wp_next_scheduled( self::HOOK, $args ) || wp_schedule_single_event( time() + $delay, self::HOOK, $args );
	}
}
