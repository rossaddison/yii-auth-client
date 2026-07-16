# Upstream Sync — PR #17 Partial Merge into `invoice`

## Problem

`rossaddison/yii-auth-client` is a fork of `yiisoft/yii-auth-client`, and
`rossaddison/invoice` depends on this fork's `invoice` branch
(`"rossaddison/yii-auth-client": "dev-invoice"` in `invoice`'s `composer.json`).

Upstream `yiisoft/yii-auth-client` had accumulated commits that never reached
`invoice`, tracked via `rossaddison/yii-auth-client` PR #17 ("Pull
yiisoft/yii-auth-client into rossaddison/yii-auth-client master"). That PR
targets `master`, not `invoice`, and by the time it was reviewed it contained
the *entire* upstream history since the fork point — including features
(`TikTok`, `VKontakte` domain rename, CSP-related widget changes) that
`invoice` had already re-implemented independently and differently. A full
merge of PR #17's tip into `invoice` produced 16 conflicting files, several
requiring real judgment calls (see [Conflict analysis](#conflict-analysis)
below).

Only a small, self-contained slice of PR #17 was both safe and useful to pull
into `invoice`: dependency/PHP-version relaxations and CI hardening that
`invoice` had not yet caught up on, plus one genuine bug fix (VKontakte's
`vk.com` → `vk.ru` domain rename).

## Conflict analysis

A trial `git merge --no-commit` of PR #17's tip (`9ebdc0d`) into `invoice`
surfaced 16 conflicting files. Severity breakdown:

| Tier | Files | Resolution |
|------|-------|------------|
| Trivial (take newer/stricter side) | `composer.json`, `AuthAction.php`, `OpenIdConnect.php`, `OAuth.php`, `Facebook.php`, CI workflows, `README.md`, `rector.php` | `invoice`'s constraints (`php 8.4-8.5`, `yiisoft/html >=4.2`) are strict supersets of upstream's relaxed ones — no real conflict of intent. |
| Needs a specific side | `VKontakte.php` (take upstream's `vk.ru` domain), `AuthChoiceAsset.php` (must keep `invoice`'s `@vendor/rossaddison/yii-auth-client` `sourcePath` — taking upstream's would break asset publishing) | One mechanical decision each. |
| Genuine judgment required | `AuthChoice.php` — upstream still uses `WebView::registerJs()` and inline `onclick`, which is exactly the CSP violation `invoice`'s most recent commit (`9b9367f`, "Fix CSP violations in AuthChoice widget") removed. Taking upstream would silently reintroduce it. | Kept `invoice`'s CSP-safe data-attribute approach; discarded upstream's `WebView` param/import entirely. |
| Duplicate independent work | `TikTok.php` — add/add conflict; upstream's version (91 lines) is an explicitly-commented "untested shell" with empty `authUrl`/`tokenUrl`, `invoice`'s (252 lines) is a complete working implementation. | Kept `invoice`'s version wholesale. |

Given `AuthChoice.php` and `TikTok.php` require judgment that a scripted merge
can't safely make, **the full PR was not merged**. Instead, the narrow
9-commit range below was cherry-picked, which avoids touching either file.

## Solution

Cherry-picked two commit ranges from PR #17 onto `invoice` (`git
cherry-pick <first>^..<last>`), resolving conflicts commit-by-commit rather
than merging the full branch history:

### Range 1 — `04dcc50..9ebdc0d` (7 commits)

| Commit | Description |
|--------|--------------|
| `7e19bf5` (`04dcc50` upstream) | Use default `FUNDING.yml` / `ISSUE_TEMPLATE.md` |
| `b039e47` (`e28970a` upstream) | Change PHP constraint to `8.2 - 8.5` |
| `f5129ca` (`cea746d` upstream) | Widen `yiisoft/html` to `^3.11 \|\| ^4.0` |
| `a74e31a` (`8d0b1cc` upstream) | Group Dependabot updates |
| `84a1e5e` (`1af3201` upstream) | Bump `actions/checkout` and `actions/cache` |
| `91e3570` (`ec88367` upstream) | Harden GitHub workflows (add `zizmor`) |
| `db8ea3c` (`9ebdc0d` upstream) | Update dependabot config |

Conflicts hit: `composer.json` (×3), `README.md`, `src/OAuth.php`,
`.github/workflows/build.yml` / `static.yml` / `mutation.yml` — all resolved
by keeping `invoice`'s stricter constraints or accepting upstream's
non-breaking superset changes (e.g. nullable `saveAccessToken(?OAuthToken
$token = null)`).

### Range 2 — `cc86457..e5830fb` (2 commits)

| Commit | Description |
|--------|--------------|
| `cc86457` "Apply fixes from StyleCI" | Skipped — cherry-picked as an **empty** commit; `invoice` already had this formatting. |
| `a7bda0c` (`e5830fb` upstream) "Change vk.com api domain to vk.ru" | Applied cleanly, 14 references updated across `src/Client/VKontakte.php`. |

## Result

`invoice` moved from `9b9367f` to `a7bda0c` (later `c8e1568`, see
[PHPMD_PHPCPD_CODE_QUALITY.md](PHPMD_PHPCPD_CODE_QUALITY.md) for the
follow-up commit) via 9 new commits, zero force-pushes, zero rewritten
history.

## Files changed

| File | Change |
|------|--------|
| `.github/FUNDING.yml`, `.github/ISSUE_TEMPLATE.md` | removed (default GitHub templates used instead) |
| `.github/dependabot.yml` | regrouped updates |
| `.github/workflows/build.yml`, `static.yml`, `mutation.yml` | `actions/checkout@v6→v7`, `actions/cache@v4→v6` |
| `.github/workflows/zizmor.yml`, `.github/zizmor.yml` | new — workflow security linting |
| `.phpunit-watcher.yml` | removed (unused) |
| `rector.php`, `tools/.gitignore`, `tools/composer-require-checker/composer.json` | `bamarni/composer-bin-plugin` tooling layout |
| `src/AuthAction.php` | dropped PHP 8.3-only typed-const syntax (`public const string` → `public const`) for the 8.2 floor |
| `src/Client/OpenIdConnect.php` | constructor property promotion for `$cache`/`$name`/`$title` |
| `src/Client/VKontakte.php` | `vk.com` → `vk.ru` domain rename (14 references) |
| `src/OAuth.php` | `saveAccessToken()` param made nullable |

## Notes

- `rossaddison/invoice` pins `dev-invoice`, so none of this reaches `invoice`
  the consuming app until this branch is updated — confirmed compatible
  beforehand: `invoice`'s own `composer.json` already requires `php 8.4-8.5`
  and `yiisoft/html >=4.2`, both stricter supersets of what these commits
  relax to, and `invoice`'s source only calls `AuthChoice::widget()->getClient()`,
  never `saveAccessToken()` or subclasses `OpenIdConnect`, so the internal
  signature tweaks don't touch it.
- The remainder of PR #17 (everything before `cc86457`, plus the CSP/TikTok
  conflict area) was deliberately **not** merged. Revisit if/when `invoice`'s
  own `AuthChoice.php` CSP rework and `TikTok.php` implementation are ready to
  reconcile with upstream's versions.
