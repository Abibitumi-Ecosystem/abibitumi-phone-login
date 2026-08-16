# Abibitumi ID and Phone Login

WordPress/BuddyBoss identity and login work for Abibitumi properties.

This repository is for two closely related authentication concerns:

- **Abibitumi ID** - the identity layer: account identity, verification status,
  profile truth, roles, capabilities, and future cross-site identity behavior.
- **Phone Login** - a login method for Abibitumi ID: OTP delivery, verification,
  account lookup/linking, rate limiting, and mobile/API authentication flows.

The Tidio chat replacement is a separate product concern and does not belong in
this repository. It should live in its own repository and integrate with
Abibitumi ID through explicit hooks or APIs when it needs verified user context.

## Current Scope

- OTP phone login for BuddyBoss/BuddyPress.
- SMS providers: Twilio, Africa's Talking, Vonage.
- Custom DB table for OTP storage.
- bcrypt OTP hashing.
- Layered rate limiting.
- REST API endpoints for mobile.
- Abibitumi ID integration boundary for verified phone/user identity.
- Ghana-first phone normalization with canonical E.164 storage.
- Phone-first sign-in UI for shortcode pages and the standard WordPress login
  screen.

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

There is no separate phone-login plugin directory; new phone login work belongs
inside `plugins/abibitumi-id/`.

## Version History

- Phone Login v1.0.0 - Initial release.
- Phone Login v1.1.0 - Added Africa's Talking provider.
- Phone Login v1.1.1 - bcrypt hashing audit.
- Phone Login v1.1.2 - Rate limiting layer.
- Phone Login v1.1.3 - REST API endpoints.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via WordPress admin.
3. Configure SMS provider credentials in the plugin settings.

## Deployment Artifact

GitHub Actions can build a WordPress-installable ZIP from this repository:

- Run `Package Abibitumi ID` manually in GitHub Actions, or push a tag matching
  `abibitumi-id-v*`.
- Download the `abibitumi-id` artifact.
- Upload `abibitumi-id.zip` through the WordPress plugin installer.

## Branches

- `main` - stable/released.
- `dev` - active development.
- `release/*` - release candidates.
