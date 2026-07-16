# phpcpd / phpmd Code Quality Run

## Problem

Neither `phpcpd` (copy-paste detector) nor `phpmd` (mess detector) is
installed as a dev dependency in this project — not in `composer.json`, not
in `vendor/bin/`. There had never been a baseline check of `src/` against
either tool.

## Solution

### 1. Standalone `.phar` execution

Rather than adding permanent dev dependencies, both tools were downloaded as
standalone signed `.phar` releases into a scratch directory and run directly
against `src/`, then deleted — `composer.json` / `composer.lock` are
untouched:

```bash
curl -sL -o phpcpd.phar  https://phar.phpunit.de/phpcpd.phar
curl -sL -o phpmd.phar   https://github.com/phpmd/phpmd/releases/latest/download/phpmd.phar

php phpcpd.phar src/
php -d error_reporting="E_ALL & ~E_DEPRECATED" phpmd.phar src/ text \
    cleancode,codesize,controversial,design,naming,unusedcode
```

(`error_reporting` tweak suppresses PHP 8.4 implicit-nullable-parameter
deprecation noise coming from the tools' own internals, not from this
project's code.)

### 2. Baseline results

- **phpcpd 6.0.3**: `No clones found.` — no duplicated code in `src/`.
- **phpmd 2.15.0**: 77 violations across the standard rulesets. Full
  breakdown:

| Rule | Count | Notes |
|------|-------|-------|
| `StaticAccess` | 28 | Idiomatic static calls to `Json`/`Html`/`RequestUtil`/`Random` — left as-is |
| `LongVariable` | 14 | Subjective naming — left as-is |
| `ElseExpression` | 9 | Style-only — left as-is except where bundled with a functional fix |
| `IfStatementAssignment` | 5 | Style-only — left as-is |
| `CouplingBetweenObjects` | 4 | `OpenIdConnect` (28 deps) worst offender — real but needs redesign, not a mechanical fix |
| `BooleanGetMethodName` | 3 | `getIsValid()`/`getIsExpired()`/`getValidateAuthNonce()` — **public API**, renaming would break `invoice`; left as-is |
| `UnusedLocalVariable` | 2 | Fixed (see below) |
| `UnusedFormalParameter` | 2 | One is `AuthAction::process($handler)`, required by the PSR-15 `MiddlewareInterface` contract — false positive, left as-is |
| `ShortVariable` | 2 | Subjective naming — left as-is |
| `ExcessiveClassComplexity` | 2 | `OpenIdConnect` (57), `OAuth2` (51) — real but needs redesign |
| `CyclomaticComplexity` | 2 | `AuthChoice::clientLink()`, `OAuth2::sanitizeKeys()`, both at threshold (10) — left as-is |
| `ShortClassName` | 1 | Class `X` — deliberate name for the X/Twitter client, left as-is |
| `MissingImport` | 1 | Fixed (see below) |
| `CamelCaseMethodName` | 1 | Fixed (see below) |

### 3. Fixes applied

Only genuine, low-risk findings were fixed — no public-API renames, no
speculative refactors:

| File | Fix |
|------|-----|
| `src/Signature/RsaSha.php` | **Real bug**: constructor accepted `$algorithm` but never assigned it to `$this->algorithm`, so `getName()` / `generateSignature()` / `verify()` always operated on an unset value. The documented usage in `docs/guide/en/oauth-direct-authentication.md` (`'algorithm' => OPENSSL_ALGO_SHA256`) relies on this being wired up. Added `$this->algorithm = $algorithm;`. |
| `src/Collection.php` | `getClients()` looped `as $name => $client` but never used `$client` (refetched via `getClient($name)` instead). Switched to `array_keys()`. |
| `src/OAuthToken.php` | Same unused-loop-variable pattern in `defaultExpireDurationParamKey()`, plus a needless empty-if/else — simplified to a single positive condition over `array_keys()`. |
| `src/Factory/CollectionFactory.php` | Added `use InvalidArgumentException;` instead of the fully-qualified `\InvalidArgumentException`. |
| `src/OAuth2.php` | Renamed `private function parse_str_clean()` → `parseStrClean()` (PSR naming). Private with 2 call sites, both updated — zero BC risk. |

## Result

- Re-ran `phpmd` after the fixes: all 5 targeted violations gone, only the
  deliberately-skipped ones remain.
- Re-ran `phpcpd`: still `No clones found.`
- `php -l` clean on all 5 changed files.
- Full test suite: 14/14 passing, same 4 pre-existing `PHPUnit Notices`
  confirmed present before the changes too (verified via `git stash` /
  rerun / `git stash pop`) — unrelated to this work.

Committed as `c8e1568` on the `invoice` branch (follows on from the PR #17
sync in [UPSTREAM_SYNC_PR17.md](UPSTREAM_SYNC_PR17.md), which left `invoice`
at `a7bda0c`).

## Files changed

| File | Lines |
|------|-------|
| `src/Collection.php` | 3 changed |
| `src/Factory/CollectionFactory.php` | 3 changed |
| `src/OAuth2.php` | 6 changed |
| `src/OAuthToken.php` | 8 changed |
| `src/Signature/RsaSha.php` | 1 added |

## Notes

- Neither tool was added to `composer.json`'s `require-dev` — re-run this
  process's [Solution](#solution) commands to repeat the check; there is no
  `composer.json` script wired up for it yet.
- The `RsaSha` fix is the only one of the five that changes runtime
  behaviour rather than just readability. `RsaSha` itself is not currently
  instantiated anywhere in `src/` (OAuth1 clients were removed from this
  fork per `README.md`) — it's only reachable if a consumer configures it
  directly per the JWT-signing docs, so the practical blast radius of this
  fix is limited to that documented usage.
