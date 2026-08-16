# Repository Cleanup Plan

## Decisions

- Abibitumi ID and Phone Login belong together because both are authentication
  concerns.
- The Tidio chat replacement is a separate product/plugin and should stand on
  its own.
- Future product plugins should consume Abibitumi ID through explicit hooks,
  filters, or REST/API contracts instead of living inside this repository.

## Completed Locally

- Removed `abibitumi-chat/` from this repository working tree.
- Removed the chat-only GitHub Actions workflow.
- Preserved the extracted chat plugin beside this checkout at
  `../abibitumi-chat-standalone`.
- Reframed root documentation around Abibitumi ID and Phone Login.
- Added a merged Abibitumi ID plugin foundation with phone OTP login as an
  internal module.

## Remaining

- Create or choose the standalone GitHub repository for the chat replacement.
- Move `../abibitumi-chat-standalone` into that repository.
- Harden and test the merged Abibitumi ID phone-login implementation against a
  real WordPress/BuddyBoss staging site.
