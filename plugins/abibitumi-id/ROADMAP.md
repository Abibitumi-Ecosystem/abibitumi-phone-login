# Abibitumi ID Phone-First Roadmap

The product goal is a WhatsApp-grade phone identity experience that fits the
existing Abibitumi WordPress stack: BuddyBoss for community identity and Better
Messages for messaging.

## Principle

Abibitumi ID owns trust. Better Messages owns conversations. BuddyBoss owns the
community profile surface. Phone login must connect those layers without
turning messaging or profiles into authentication code.

## Current Foundation

- Phone OTP start/verify REST endpoints.
- App-friendly phone normalization REST endpoint.
- Phone-first shortcode and standard WordPress login-screen option.
- Hashed OTP storage with expiry, attempt limits, and start rate limits.
- Verified phone user meta:
  - `abid_phone_e164`
  - `abid_phone_hash`
  - `abid_phone_verified_at`
- Ghana-first phone normalization with E.164 storage.
- Configurable SMS provider adapters:
  - Twilio
  - Africa's Talking
  - Vonage
  - `log` for local development only
- BuddyBoss/BuddyPress xProfile phone sync after verification.
- Verified-user contact discovery for finding contacts with Abibitumi accounts.
- Optional admin entitlement mapping so trusted Abibitumi ID admins can become
  administrators across sites without requiring the same email address.
- Better Messages integration:
  - verified phone can feed Better Messages verified badge state.
  - verified phone can be required before sending messages.

## Near-Term Product Requirements

1. Phone-first login screen
   - Country selector.
   - E.164 normalization with Ghana local-number support.
   - OTP resend countdown.
   - Clear recovery path when a phone is already attached to another account.

2. Account linking
   - Logged-in users can add or change verified phone.
   - Existing users can sign in by phone without creating duplicates.
   - Admin tools for resolving duplicate phone/account claims.

3. BuddyBoss profile integration
   - Sync verified phone to a configured Phone profile field.
   - Keep phone visibility controlled by BuddyBoss field visibility settings.
   - Add profile trust indicators based on Abibitumi ID, not ad hoc theme code.

4. Cross-site admin identity
   - Use verified Abibitumi ID identity as the source of admin entitlement.
   - Support email aliases for existing admin accounts that do not share the
     same email as the primary Abibitumi account.
   - Prefer verified phone hash matching for cross-site admin identity because
     it does not depend on email reuse.
   - Keep network super admin promotion as a separate manual decision.

5. Better Messages trust layer
   - Verified badge source from Abibitumi ID.
   - Optional "verified phone required to message" enforcement.
   - Contact discovery result shape that Better Messages and app clients can
     turn into a recipient picker.
   - Future: invite flows for contacts that do not yet have accounts.

6. Mobile/PWA readiness
   - REST responses shaped for mobile clients.
   - Session handling compatible with WordPress auth cookies and app-password
     style server integrations.
   - Device/session audit trail before production launch.

## Production Hardening

- Add automated tests around OTP expiry, attempts, rate limits, phone
  normalization, account linking, and REST permissions.
- Add visual/browser tests for the phone-first login form on desktop and
  mobile.
- Replace `log` SMS mode before production.
- Add audit logging for verification events without storing OTP codes.
- Add configurable phone-number redaction in admin/profile displays.
- Review abuse controls for message-sending gates before enabling them globally.
- Add contact discovery throttling beyond per-request batch limits before
  public mobile launch.
