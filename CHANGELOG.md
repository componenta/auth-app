# Changelog

## Unreleased (3.0.0)

- Require Auth 4 and Identity 1.1.1, Config 4 and DI 6.
- Require PHP 8.5.13 or newer and run CI against PHP 8.5.
- Preserve invocation-only request sourcing and the existing attribute API.
- Verify the existing public-contract suite against the coordinated new stack.

The new major version drops the former Auth/Session, Config and DI dependency
generation. Upgrade the related packages together; see
[the compatibility notes](docs/CONFIG4-COMPATIBILITY.md).
