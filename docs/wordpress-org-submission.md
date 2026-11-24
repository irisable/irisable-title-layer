# WordPress.org submission preparation

Updated: 2026-11-24. Approved version: 1.0.4. Directory account: `irisable`.

## Current outcome: approved; first directory release is being prepared

The approval email was received on November 24, 2026 at 01:44 Asia/Shanghai.
The plugin directory assigned [irisable-title-layer](https://wordpress.org/plugins/irisable-title-layer/)
with SVN at `https://plugins.svn.wordpress.org/irisable-title-layer/`.
Publish the exact reviewed 1.0.4 files from Git commit `0ac385b`, SHA-256
`42763b52f603dc744ca5fb6d3cd3cda47bebda5f04693756e2203a6c80c566e5` for the
original ZIP. The GitHub repository was renamed to `irisable/irisable-title-layer`
after approval. The approved artifact is unchanged and its original GitHub
links redirect to the same repository. No production site update is implied.

## Third review outcome: 1.0.4 uploaded and reply sent

The November 21 review of 1.0.3 flagged the visibility of labels and historical
aliases in the editorial group picker. The verified 1.0.4 package was uploaded
to the existing submission and the original review email was answered on
November 21. See the [1.0.4 review record](wordpress-org-review-2026-11-21.md).

## Second review outcome: 1.0.3 update uploaded and reply sent

The second review of 1.0.2 identifies Kadence fallback escaping and synchronous
content-group reconciliation. The 1.0.3 update was tested, uploaded to the
existing submission and followed by a reply in the original review thread on
November 19. Awaiting the review team's response. See the
[second-round record](wordpress-org-review-2026-11-19.md) for evidence and
validation. The slug remains unchanged.

## First review outcome: 1.0.2 uploaded and review reply sent

The November 15 email is **Review in Progress**, including automated/AI-assisted
findings and an ownership-verification request. It is not approval or a final
rejection. Candidate 1.0.2 passed validation and was uploaded on November 15.
Owner-authorized DNS verification is publicly visible; the owner-approved reply
was sent in the original thread on November 15 at 07:41 Asia/Shanghai. Awaiting
the review team's response, not approved for directory publication. See the
[item-by-item review record](wordpress-org-review-2026-11-15.md).

## Historical submission receipt (November 11)

The renamed **Irisable Title Layer 1.0.1** upload was accepted on 2026-11-11.
The authenticated submission page shows **Awaiting Review**, assigned slug
`irisable-title-layer`, version `1.0.1`, and **Automated Plugin Scanning: Pass**.
The server-side result lists one nonblocking `unexpected_markdown_file` warning
for the deliberately retained `CREDITS.md`. The page says a confirmation email
was sent; the owner subsequently opened it in Chrome, where its subject and
body were verified as the automatic successful-submission receipt, not a human
review response.

Submitted source commit: `895d3c5`. Submitted file:
`irisable-title-layer-1.0.1.zip`, SHA-256
`416dd4148eb0cb1508b77838ac321177140019b3cdccbb5e78143534290a2821`.
Local Plugin Check 2.2.0 selected 31 static checks: **0 errors, 125 reviewed
warnings**. The full exact-ZIP dual-version matrix passed, as did 99 JS/contracts
and 9 new-directory/legacy-coexistence/data-preservation checks.

This was NOT manual approval or a live directory release. The review email has
now arrived; respond within that submission, not as a new plugin submission.
No production redeployment, GitHub release replacement or Obsidian reload.

## Initial submission under the approved name

After the first upload was rejected, the owner approved **Irisable Title Layer**
and a new submission. The intended directory slug, package root and translation
domain are `irisable-title-layer`. The source repository kept its original
GitHub URL until directory approval. Database keys, taxonomy names, PHP namespace, REST routes,
stored block names and editor registration IDs remain unchanged.

The old copy must be deactivated before activating the directory copy. The new
loader refuses to run alongside an active legacy copy and uses a closure
autoloader to avoid redefining the legacy global function. No production
deployment or automatic deactivation is performed. The old `Update URI: false`
installation needs a one-time manual switch; changing the public name does not
magically migrate the WordPress plugin basename.

Build the directory artifact with `bash bin/build-wporg-release.sh`; the
existing legacy-root builder remains available for controlled updates in the
old folder. Run `bin/test-release-package.sh irisable-title-layer-1.0.1.zip` and
the standalone `tests/distribution-smoke.php` with the new directory mount.

Submitting 1.0.1 did not itself make the plugin available in the directory.
The historical GitHub 1.0.0 release is preserved. Production was not redeployed
as part of this preparation.

## Historical first attempt (superseded by the accepted renamed submission)

The owner explicitly authorized the directory declarations and upload. The
upload endpoint rejected the name **WP Title Layer**, stating that `wp` cannot
begin the plugin permalink or display name. There is no accepted submission or
review-queue acknowledgement. This live validation overrides the more permissive
comment in Plugin Check's source. No automatic rename or repeated upload was
attempted; owner choice is required before changing the public identity/slug.

Candidate commit: `4d436c8`. ZIP SHA-256:
`c00afbc20033dabdb3b8c33dbb16ed0f011c87c41e36fd9346567cfb31b9a901`.
Final official static result: **0 errors, 128 reviewed warnings**. All 99
JavaScript/contracts passed. Both source and exact packaged-payload matrices
passed on WordPress 6.5/PHP 7.4 and current WordPress/PHP 8.3: 61 PHP files,
828 integration, 111 sequence, 67 book structure, 57 content group and 46
Publisher checks per environment.

## Distribution changes

- Remove `Update URI: false` so directory-distributed installations can use
  native WordPress.org updates after approval and SVN publication.
- Correct the readme contributor to `irisable`.
- Add privacy, external-service, readable-source and compatibility-credit notes.
- Add missing translator comments. Rank Math's literal `%...%` tokens are not
  printf placeholders and retain their existing translations.
- Escape the fixed h3/h4 archive heading name at output and remove the obsolete
  second `term_description()` argument.
- Sanitize/unslash the migration request-method string and rollback confirmation.
- Document narrowly scoped static-analysis false positives for prepared SQL
  and streaming CSV downloads. No database schema or content migration.

## Official Plugin Check

Tool: official WordPress.org Plugin Check 2.2.0, downloaded from
https://downloads.wordpress.org/plugin/plugin-check.2.2.0.zip

Run in an isolated current-WordPress/PHP 8.3 Playground against the installable
payload, not the repository's tests or development tools. All 31 default static
checks are selected through the tool's `CLI_Runner`; AI analysis is disabled.
This is not a claim that Plugin Check runtime checks ran. Runtime behavior is
covered separately by this project's WordPress integration matrix.

Initial 1.0.0 result: 29 errors and 133 warnings. Candidate results and exact ZIP
hash are recorded in the local submission evidence under `build/wporg-preflight/`.

### Reviewed remaining warnings

These warnings remain visible rather than being globally suppressed. Counts
refer to diagnostic locations, not distinct defects. They need to be disclosed
to a reviewer if requested; the review team can require additional changes.

| Warning family | Review and disposition |
| --- | --- |
| Nonce verification in read-only GET reports / editor context | Health filters, pagination, notice text, and editor post selection do not perform a content mutation. Administrative reports retain capability gates; values are sanitized and output is escaped. |
| Nonce verification in migration / sequence POST helpers | `AdminPage::authorize()`, `AjaxController::authorize()`, `SequenceManager::verify_action()` and `verify_ajax()` perform capability and action-specific nonce checks before mutation. Static analysis does not follow these helpers. |
| InputNotSanitized on composite form input | Classic Editor and Series term forms sanitize each recognized field with its specific sanitizer before persistence; indiscriminate sanitization of the entire structured payload would change intended values. |
| InputNotSanitized on run IDs / health IDs | `Config::clean_run_id()` applies the run-ID allowlist. ChannelHealth validates scalar values then applies `absint()` before use. |
| Interpolated SQL / unfinished prepare | Variable IN clauses contain only generated `%s` placeholders. Values go through `$wpdb->prepare()`. Book-track role/scope expressions are fixed source strings; SQL aliases are fixed internal arguments additionally restricted to ASCII letters, digits and underscores. |
| Direct queries / no caching | Migration scans need current data, metadata row IDs and duplicate detection. Atomic compare-and-swap/delete operations protect concurrent author edits and invalidate the relevant WordPress cache. Source ACF key lookup is memoized per resolver. These are intentionally not replaced by stale cached counts or non-atomic read/write operations. |
| tax_query / meta_query / post__not_in | Required for scoped Series/Season/group membership, sequence management and adjacent navigation. These generic potential-performance warnings do not identify a broken query; retain existing pagination/batching and integration coverage. |
| Unprefixed functions / hooks | Guarded Secondary Title compatibility functions and legacy hooks preserve existing integrations. Rollback emits WordPress core metadata hooks for its guarded atomic deletion. New plugin-owned interfaces use the plugin prefix or namespace. |
| load_plugin_textdomain | Keep bundled Simplified Chinese working for existing manual/GitHub installations too; in 1.0.2 registration is deferred to init. Directory language packs can still be used. |
| CREDITS.md | Intentionally retain human-readable third-party review provenance and attribution; no third-party ZIP or development fixture is distributed. |
| `wp` trademark warning | The initial display-name/slug blocker was resolved by the owner-approved Irisable Title Layer rename. Existing wptl data/API identifiers remain for compatibility; the November 15 prefix finding is addressed separately in the review record. |

No telemetry, tracking cookies, outbound content submission, remote executable
download, license gate, trial limit, or external account requirement was found
in the plugin. Optional ACF, Secondary Title, Rank Math and theme integrations
operate on installed local software/data. Repository and author links are
ordinary user-initiated links, not background services.

## Submission sequence

1. Finish candidate regression tests and check the exact final ZIP.
2. Have the owner confirm the submission declarations and directory conditions.
3. Upload the candidate ZIP; retain the acknowledgement and requested slug.
4. Respond to review feedback in the same review email thread.
5. After approval, publish the reviewed source to assigned SVN trunk/tag;
   verify the generated download, readme and public directory page.

Approval does not itself mean the downloadable directory release is live.
Do not overwrite the already published GitHub 1.0.0 archive or tag.

Official references:
- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/
- https://wordpress.org/plugins/plugin-check/
- https://wordpress.org/plugins/developers/add/
