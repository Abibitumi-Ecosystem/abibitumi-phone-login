# Changelog

## Abibitumi Chat

### [1.2.0] - 2026-08-05
- Added proactive page rules: a self-hosted replacement for Tidio Flows.
- Rules match the visitor's current URL, audience (new, returning, logged in or out) and office hours, then fire on page open, dwell time, scroll depth or exit intent.
- Proactive messages carry quick-reply buttons; clicking one opens the panel and is answered by the existing chatbot through the new `abchat_bot_flows` filter.
- Frequency caps (per page, session, day, ever) are enforced in the browser and again server-side with transients, so nobody is messaged twice.
- Added `POST /proactive` and `POST /proactive/engaged` REST routes, a JSON rule editor in Settings, and a shown/replied/engagement table so rule performance is visible.
- Starter rules ship for product pages, cart and checkout exit intent, course access help, and first-time visitor orientation.

### [1.1.1] - 2026-07-11
- Added compatibility handling for legacy MySQL `utf8mb3` option tables.
- Preserves normal Unicode on modern `utf8mb4` sites while omitting unsupported four-byte characters on legacy tables.
- Ensures activation and preset application can create the settings option.

### [1.1.0] - 2026-07-11
- Added Tidio contacts and transcript CSV migration tooling.
- Added live visitor page journey context for operators and chatbot recommendations.
- Added privacy export/erasure and retention integration for journey data.

### [1.0.0] - 2026-07-11
- Added self-hosted Tidio replacement plugin under `abibitumi-chat/`.
- Includes live chat widget, operator inbox, chatbot flows, file sharing, visitor tracking, analytics, PWA support, Web Push, site presets, and CI packaging.

## Abibitumi Phone Login

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
### [1.0.0]
- Initial release.
