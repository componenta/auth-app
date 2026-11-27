# Config 4 and DI 6 compatibility

This package uses the Auth 4 dependency generation: Config 4 and DI 6.
PHP 8.5.13 or newer is required by DI 6 and is declared directly by this
package. The former Config 3 / DI 5 combination is outside this release line.

## Public behavior

The current PSR-7 request supplies the authenticated identity for each callable
invocation. The existing parameter-handler contract and
`getAttributeDefinitions()` provider extension point work with Config 4
and DI 6; no replacement resolver or compatibility shim is needed.

The contract suite verifies request-only sourcing, authoritative argument
precedence, missing or malformed request state, nullable and required
targets, constructor rejection, and reuse across separate requests.

## Verified dependency sources

- Config 4.0.0: `4685e71d86500a7b51f4a773bb3022dd787fba7f`.
- DI 6 candidate: `e4f19ab9a9f6bd18da85fc52c8d478433a32baec`.
- Identity 1.1.1 candidate: `3feb94e0d64956ecdf7b22abed4a8bd9f597abeb`.

The contract suite passed with these real sources on PHP 8.5.13:
11 tests and 22 assertions. Candidate versions were supplied by
Composer path repositories in the integration test project. These labels
describe the intended releases; they do not assert that tags already exist.
The package manifest keeps normal SemVer requirements and stable minimum
stability. An installation from published packages requires the corresponding
dependency releases first.

## Checks

After the prerequisite versions are available:

```sh
composer validate --strict
composer update --no-interaction
composer check
composer audit --no-interaction
```

The complete coordinated graph and the source revisions are recorded in
[the Auth dependency audit](https://github.com/componenta/auth/blob/fix/auth-dependency-audit-2026-11-27/docs/AUTH-DEPENDENCY-AUDIT-2026-11-27.md).
