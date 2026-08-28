# Abibitumi ID

Identity layer and phone OTP login for Abibitumi WordPress/BuddyBoss
properties.

## Responsibilities

- Store and expose verified identity state.
- Own verified phone user meta.
- Send and verify phone OTP login codes.
- Link verified phone numbers to WordPress users.
- Promote trusted Abibitumi admin identities across sites when explicitly
  enabled.
- Sync verified phone status into BuddyBoss and Better Messages.
- Provide REST/API contracts for sibling plugins that need verified user
  context.

## REST API

- `POST /wp-json/abid/v1/phone/normalize`
  - Body: `{ "phone": "024 123 4567", "country": "GH" }`
  - Returns the normalized E.164 phone and validity state.
- `POST /wp-json/abid/v1/phone/start`
  - Body: `{ "phone": "+15555555555" }`
  - Sends an OTP using the configured SMS provider.
- `POST /wp-json/abid/v1/phone/verify`
  - Body: `{ "phone": "+15555555555", "code": "123456" }`
  - Verifies the OTP, marks the phone as verified, and signs the user in when
    the phone is linked to an account.
- `POST /wp-json/abid/v1/push/device`
  - Body: `{ "token": "firebase-device-token", "device_name": "Android" }`
  - Requires a logged-in user with a verified phone.
  - Registers the current device as trusted for future push-login approvals.
- `POST /wp-json/abid/v1/push/start`
  - Body: `{ "phone": "024 123 4567" }`
  - Sends a Firebase push approval request to trusted devices for that phone.
- `POST /wp-json/abid/v1/push/approve`
  - Body: `{ "challenge_id": "..." }`
  - Requires the trusted device user to be logged in with a verified phone.
- `POST /wp-json/abid/v1/push/status`
  - Body: `{ "challenge_id": "...", "poll_token": "..." }`
  - Polls a pending push login and signs in the requesting browser/session once
    the trusted device approves.
- `GET /wp-json/abid/v1/sso/start?return_to=https%3A%2F%2Fabibiwiase.com%2F`
  - Starts Abibitumi ecosystem sign-in on the identity provider site.
  - If the user is not logged in, they are sent through the normal Abibitumi.com
    login screen first.
  - If the user is logged in, Abibitumi.com redirects back with a short-lived
    one-time ticket.
- `POST /wp-json/abid/v1/sso/validate`
  - Body: `{ "ticket": "..." }`
  - Header: `X-Abibitumi-ID-Secret: shared-secret`
  - Validates and consumes a one-time ticket. Sister sites use this to create or
    link the local WordPress user and set a local login cookie.
- `GET /wp-json/abid/v1/me`
  - Returns the current user's Abibitumi ID phone identity.
- `POST /wp-json/abid/v1/contacts/lookup`
  - Body: `{ "contacts": [ { "phone": "+15555555555" } ] }`
  - Requires the current user to be logged in with a verified phone.
  - Returns matching Abibitumi users for contacts that already have verified
    phone-linked accounts.
  - Contacts are normalized and hashed for lookup during the request; they are
    not stored by Abibitumi ID.

## Settings

Go to `Settings -> Abibitumi ID` in WordPress admin to configure SMS delivery
OTP policy, BuddyBoss profile sync, Better Messages trust behavior, and admin
entitlements.

For a phone-first sign-in experience:

- enable automatic account creation for newly verified phone numbers.
- keep `Default phone country` set to `GH` for Ghana-first local-number entry.
- enable phone login on the standard WordPress login screen.
- add `[abid_phone_login]` to any BuddyBoss/login page that should use the
  same phone-first flow.
- configure Firebase Cloud Messaging if app clients should approve repeat
  sign-ins by push notification instead of SMS.
- configure ecosystem sign-in so Abibitumi.com can act as the identity provider
  and sister sites can accept Abibitumi ID.

Supported SMS providers:

- Hubtel
- Twilio
- Africa's Talking
- Vonage
- `log` for local development only

For Ghana production, use Hubtel first when possible because it is Ghana-native
and supports approved sender IDs. Africa's Talking is the next-best supported
Ghana/Africa-first option. Twilio remains useful globally, but Ghana delivery
requires attention to sender ID rules.

## Identity Meta

- `abid_phone_e164`
- `abid_phone_hash`
- `abid_phone_verified_at`

`abid_phone_hash` is used for lookups so other plugins do not need to query by
raw phone number.

## Phone Normalization

Verified phones are stored as E.164 values. The default country is `GH`, so
Ghana local numbers are accepted naturally:

- `0241234567`
- `024 123 4567`
- `241234567`
- `233241234567`
- `+233241234567`
- `00233241234567`

All of those normalize to `+233241234567`. The same normalization is used for
login, contact discovery, BuddyBoss sync, and admin phone entitlements.

## Admin Entitlements

Abibitumi admin access should follow the trusted Abibitumi ID identity, not only
the email address used on a given WordPress site. Enable `Admin entitlements`
and configure either:

- trusted admin email aliases, one per line.
- trusted verified phone numbers or phone hashes.

When a matched user logs in or verifies their phone, Abibitumi ID grants the
`administrator` role on that site. Raw phone numbers entered in settings are
converted to hashes on save. This feature does not grant multisite network super
admin.

## Ecosystem Sign-In

Use Abibitumi.com as the identity provider for the whole ecosystem. Install and
configure Abibitumi ID on Abibitumi.com and on each sister site.

On Abibitumi.com:

- enable `Ecosystem sign-in`.
- set `Identity provider URL` to `https://abibitumi.com`.
- set a long random `Shared SSO secret`.
- list every trusted destination under `Allowed ecosystem domains`.

On each sister site:

- enable `Ecosystem sign-in`.
- set `Identity provider URL` to `https://abibitumi.com`.
- use the same `Shared SSO secret`.
- enable `Create local users from verified Abibitumi ID identities` if members
  should land in the sister site without manual account setup.
- place `[abid_phone_login]` on the login page. The widget will show
  `Continue with Abibitumi ID` when the current site is not the identity
  provider.

The SSO ticket is one-time and expires after five minutes. The sister site
validates it server-to-server, then links by verified phone hash first and email
second before creating a local user.

## BuddyBoss and Better Messages

Abibitumi ID is designed to be the phone-first trust layer for the existing
community stack.

- BuddyBoss/BuddyPress: set `BuddyBoss phone profile field ID` to sync verified
  phone numbers into an xProfile Phone field after verification.
- Contact discovery: verified users can discover which uploaded contacts already
  have Abibitumi accounts. Use this to power WhatsApp-style "people you can
  message" UI in the site/app without putting account matching inside Better
  Messages.
- Better Messages: enable verified badge integration to expose Abibitumi ID
  verified-phone status through Better Messages verification filters.
- Better Messages: optionally require verified phone status before sending
  messages.

See `ROADMAP.md` for the WhatsApp-competitive phone identity plan.

## Push Login

Push login is for repeat sign-ins from a phone number that already has a trusted
device. It does not replace the first verification step. The first verified
login binds the phone to the user. After that, an app can register its Firebase
device token with `/push/device`; future login attempts can call `/push/start`
and wait on `/push/status` while the trusted app approves through
`/push/approve`.

## Production Readiness

Before switching this on for everyone:

- configure a real SMS provider; do not use `log` mode in production.
- for Ghana-first production, configure Hubtel Client ID, Client Secret, and an
  approved Sender ID such as `ABIBITUMI`.
- place `[abid_phone_login]` on the public login page and confirm the standard
  WordPress login screen also shows phone sign-in.
- test existing account linking for users who already have emails/usernames.
- test new account creation from only a phone number.
- test contact discovery from site and app clients.
- test ecosystem sign-in from Abibitumi.com into each sister site.
- test BuddyBoss profile sync and Better Messages send/verified behavior.
- run the smoke checks below.

## Smoke Checks

```bash
php tests/smoke/admin-entitlements.php
php tests/smoke/phone-normalization.php
```
