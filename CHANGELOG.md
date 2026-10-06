# Changelog

### 0.4.5
- Leave phone-only account email blank instead of inventing a `.invalid` address; clear matching old placeholders on upgrade.
- Apply the site's normal account-approval gate before issuing a phone-login cookie.
- Verify the browser actually has a WordPress session before showing “You are signed in.”
- Keep SMS verification and account approval distinct: a pending account remains pending after a valid code.

### 0.4.4
- Add Twilio API-key authentication with separate Account SID, API Key SID, and API Key secret fields.
- Add optional international routing and Hubtel-failure fallback to Twilio while retaining Hubtel as Ghana-first provider.
- Hide saved provider secrets in settings and preserve them when password fields are left blank.

### 0.4.3
- Move phone add/verify/manage forms to a dedicated admin page so they do not
  nest inside WordPress's profile form and silently submit to the wrong handler.

### 0.4.2
- Send Hubtel OTP messages through the account's working SMSC endpoint and
  migrate the old developer-portal endpoint setting automatically.

### 0.4.1
- Make the registered phone-profile renderer public so WordPress can invoke it.

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
- Added Hubtel as the recommended Ghana-first SMS provider.
- Added Firebase trusted-device push login for repeat sign-in approvals after
  first phone verification.
- Added Abibitumi.com identity-provider SSO tickets so sister sites can accept
  Abibitumi ID and link users by verified phone hash or email.

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
