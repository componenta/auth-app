# Config 4 and DI 6 compatibility verification

This branch is a compatibility candidate; it must not be tagged or merged as a verified release until both dependency combinations pass the existing public-contract suite.

## Intended compatibility

| Runtime | Config | DI |
| --- | --- | --- |
| PHP 8.4+ | 3.x | 5.x |
| PHP 8.5.13+ | 4.x | 6.x |

Config 4 and DI 5 cannot be installed together with the currently published DI 5 constraints. DI 6 currently requires Config 4 and PHP ^8.5.13. Keep the existing PHP ^8.4 constraint for the DI 5 combination; Composer enforces the stricter platform requirement for DI 6.

The current provider uses the protected `getAttributeDefinitions()` extension point and the public DI parameter attribute handler contract. Static inspection of Config 4.0.0 (4685e71d86500a7b51f4a773bb3022dd787fba7f) and DI main (e203077d12048936f078d37d1cf3badd661e1716) found these used signatures and capability names retained, so no production resolver changes are proposed at this stage. Static compatibility is not proof of runtime behavior.

## Release gates

- DI 6 must have an installable stable release; the released Identity dependency must admit Config 4. Identity v1.1.0 still requires Config ^3.0.
- Run the complete existing suite with each dependency combination, including the provider and tests/CurrentUserAttributeTest.php tests. Verify trusted request sourcing, authoritative argument precedence, missing/invalid request behavior, nullable and required targets, constructor prohibition, and absence of state retained between invocations.
- Resolve the coordinated Auth dependency versions as part of the release preparation; do not use an unbounded development dependency or pretend a development branch is a published version.

Run in separate clean checkouts with the relevant real PHP runtime, after compatible releases exist:

```sh
composer validate --strict
composer update --with 'componenta/config:^3.0' --with 'componenta/di:^5.0' --no-interaction
composer check
composer audit --no-interaction
```

```sh
composer validate --strict
composer update --with 'componenta/config:^4.0' --with 'componenta/di:^6.0' --no-interaction
composer check
composer audit --no-interaction
```

The wider Composer ranges are intentional candidates. No change to CurrentUser's public API, security policy, or existing tests is needed based on the inspected contracts. Do not weaken the tests to make the migration pass. Both runtime matrices are pending; the existing audit branches remain unchanged.
