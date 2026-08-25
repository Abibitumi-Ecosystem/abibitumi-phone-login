# AGENTS.md

Guidance for AI coding agents working in this repository.

## Repository Purpose

This repository is for Abibitumi authentication work:

- **Abibitumi ID** - identity, verification status, profile truth, roles,
  capabilities, and future cross-site identity behavior.
- **Phone Login** - OTP-based phone authentication and account linking as a
  login method for Abibitumi ID.

The Tidio chat replacement is intentionally out of scope for this repository.
Keep chat code in its own repository and integrate it with Abibitumi ID only
through explicit hooks, filters, or REST/API contracts.

## Layout

Phone login is merged into Abibitumi ID as an internal module:

```text
plugins/
  abibitumi-id/
    abibitumi-id.php
    includes/
      identity/
      phone-login/
      otp/
      account-linking/
```

There is no separate phone-login plugin directory. Do not add one unless the
architecture decision changes.

## Expected Checks

Use the checks provided by the plugin source once it is present. At minimum,
new WordPress/PHP code should support:

```bash
find plugins -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/smoke/admin-entitlements.php
php tests/smoke/phone-normalization.php
node --check plugins/abibitumi-id/assets/js/login.js
composer validate --strict
composer test
```

If JavaScript assets are added, include syntax checks or a small test suite for
the relevant package.

## Conventions

- Follow WordPress coding standards.
- Escape output, sanitize input, and prepare SQL queries.
- Keep authentication and identity code conservative: rate-limit OTP flows,
  hash secrets, avoid logging OTP values, and treat phone numbers as sensitive
  identifiers.
- Prefer explicit integration hooks over cross-plugin reach-ins.
- Keep Abibitumi ID as the source of truth for verified identity. Phone login
  should supply/verify credentials, not fork identity state.

## Guardrails

- Do not add unrelated product features to this repository.
- Do not reintroduce chat/Tidio replacement code here.
- Do not hard-code one site's policy where a setting or hook is more
  appropriate.
