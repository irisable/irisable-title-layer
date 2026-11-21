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
authorized group renames and preservation of Publisher behavior. The same
artifact will be checked on both supported WordPress test environments before
submitting it to the existing entry; no new slug, GitHub release or deployment.
