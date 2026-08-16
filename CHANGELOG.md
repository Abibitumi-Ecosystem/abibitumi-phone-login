# Changelog

## Repository

### Unreleased
- Removed the Tidio chat replacement from this repository boundary.
- Reframed this repository around Abibitumi ID and phone login.
- Added the initial merged Abibitumi ID plugin foundation with phone OTP login
  as an internal module.
- Added BuddyBoss phone profile sync and Better Messages verified-phone trust
  integration.
- Added verified-user contact discovery for WhatsApp-style "contacts already on
  Abibitumi" lookup without storing uploaded contacts.
- Added optional Abibitumi ID admin entitlements so trusted identities can be
  granted site administrator access by email alias or verified phone hash.
- Added a smoke test for admin entitlement alias and phone/hash parsing.
- Added Ghana-first phone normalization so local Ghana numbers are stored and
  matched as E.164 numbers.
- Added a phone-first login UI shortcode, standard WordPress login-screen
  integration, and app-friendly phone normalization endpoint.
- Added GitHub Actions checks and packaging for the Abibitumi ID WordPress
  plugin ZIP.

## Phone Login

### [1.1.3]
- REST API endpoints for mobile.

### [1.1.2]
- Layered rate limiting.

### [1.1.1]
- bcrypt OTP hashing audit.

### [1.1.0]
- Africa's Talking provider added.

### [1.0.0]
- Initial release.
