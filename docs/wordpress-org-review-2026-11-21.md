# WordPress.org group-permission review response

November 21, 2026. The latest review found that the editor's Series group
suggestions could expose draft/private labels and historical aliases to users
with Series assignment permissions alone. Candidate: **1.0.4**.

The suggestions endpoint still requires authentication and the native Series
assignment capability. It now derives suggestions from readable, publicly
viewable, unprotected articles in the requested Series/season (at most 200
recent candidates), plus the saved group of the requested article only when the
caller can read and edit that article and it belongs to that scope. Responses
are private/no-store. This bounded suggestion list is optional; free-text
entry of older or new group names remains possible.

Historical aliases still resolve saved labels server-side, but neither editor
receives them. Shared renames require the native Series term-edit capability
and read/edit access to a saved member of the exact group. Save a new group
assignment before renaming it. Group identifiers and stored data do not change.

Related output checks: protected or otherwise nonpublic post responses do not
expose group metadata to callers without read/edit access. Public archives do
not show protected groups or a group name on an empty filtered result. The
Publisher field `wptl_series_group` remains available for authorized post edits
and readback; its input validation, format and canonical-name resolution remain.

Regression fixtures cover account capabilities, multiple restricted post
statuses, cross-season context, anonymous archive/REST output, alias omission,
authorized group renames and preservation of Publisher behavior. Both source
and exact ZIP passed on WordPress 6.5/PHP 7.4 and WordPress 7.1/PHP 8.3:
62 PHP files, 828 integration, 111 sequence, 67 book structure, 92 content
group, 46 Publisher, and 59 security checks per environment. Each ZIP also
passed 9 clean-activation and 13 legacy coexistence checks per environment.

Candidate source: `0ac385b`. The exact `irisable-title-layer-1.0.4.zip` has
SHA-256 `42763b52f603dc744ca5fb6d3cd3cda47bebda5f04693756e2203a6c80c566e5`.
ZIP integrity and byte-for-byte reproduction passed. The old 1.0.3 ZIP is unchanged.
JavaScript and contract checks: 102 passed. Official Kadence 1.6.0 template
checks: 51 passed. Plugin Check 2.2.0 on the exact ZIP: 31 static checks, 0
errors, 129 warnings. The two new performance warnings concern the taxonomy
and metadata conditions on the bounded suggestion query; none were suppressed.

WordPress.org accepted the 1.0.4 file in the existing submission on November 21,
and Gmail confirmed the reply to the original review thread at 19:24
Asia/Shanghai. Approval remains pending. Screenshots, logs and the Plugin Check
report are in the ignored `build/wporg-review-20261121/` directory. No slug or
repository-address change, GitHub push/release, or production deployment.
