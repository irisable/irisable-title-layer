# WordPress.org second-round review response

Received November 19, 2026, for the 1.0.2 submission. Candidate: **1.0.3**.
Existing account and slug remain `irisable` and `irisable-title-layer`.
Only the two issues listed in the follow-up are in scope for this update.

- **Kadence fallback escaping.** Every return path of `filterKadenceTitle()`
  now goes through the narrow heading allow-list, including before-hook,
  nonmatching and exception fallbacks. It preserves safe inline emphasis and
  existing entities. The filter is still installed only around the verified
  singular Kadence slot, not globally.
- **Content groups after a Series structure edit.** The save hook stores a new
  per-Series generation/checkpoint and schedules WordPress Cron. It does not
  enumerate or modify member posts. A worker reads at most 50 relationship IDs
  in increasing object-ID order, including drafts, and schedules its retry
  before processing. It advances the persisted cursor only after successful
  processing and conditionally removes the completed checkpoint. A newer
  structure edit supersedes the old generation. Direct edits to individual
  posts, including Publisher writes, still synchronize their membership at save.

While Cron catches up, a newly scoped group archive can be incomplete; old group
IDs from an incompatible scope are excluded. The saved group label, Series
membership, post content, reading order and REST contract are untouched. Sites
with built-in WP-Cron disabled must have their normal system Cron runner enabled.

Validation includes a 107-member fixture crossing multiple batches, a simulated
interruption after some writes, restart from the previous checkpoint, superseding
structure changes, and archive visibility checks. The package matrix also runs
actual clean plugin activation under `WP_DEBUG`.

The worker's prepared, bounded SQL reads the current relationship IDs rather
than using an unbounded cached member list; no arbitrary SQL or user identifiers
are interpolated. The WordPress APIs remain responsible for updating metadata.

No production update, GitHub push, ownership-record change or new submission.
