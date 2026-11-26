# KitMage Member Drip — development specification

Status: initial product direction approved; implementation and testing pending.

## Purpose
A lightweight, reusable WordPress/WooCommerce plugin for private seasonal experiences, not a full LMS or community. Use native WordPress editing and accounts; no paid external dependencies.

## Core requirements
- Multiple independent experiences, associated with WooCommerce products.
- Grant access to authenticated purchasers after confirmed payment; support manual grants, extensions and revocation. Handle full refunds and canceled/unpaid orders; avoid duplicate grants and account confusion. Use WooCommerce CRUD APIs for HPOS compatibility.
- Experience landing page and individual editable content pages; support text, audio, downloads and live gathering links/replays.
- Fixed calendar release dates and optional enrollment-relative release rules. Welcome content can open immediately. Late purchasers see all currently released content. Previously released content remains accessible until expiration.
- Optional fixed expiration or lifetime access. Access checks must be enforced server-side on every request, not dependent on scheduled tasks.
- Protect content and private media, including direct links, REST, feeds, search, previews, excerpts, comments and caching. Ordinary public WordPress uploads and external media are not automatically private; design a dedicated private-media storage/delivery mechanism, including audio range requests. Explicit downloads cannot be recalled after saving.
- Private threaded sharing/comments per content page, optional and moderated. Restrict both reading and writing to eligible participants and authorized staff, including API endpoints. Optional reply notifications.
- My Experiences area, schedule with locked/unlocked states, accessible responsive layout inheriting theme styling.
- Administrative settings for dates, products, content order, participants, preview, duplication, and email templates.
- Welcome, weekly release and optional closure emails; idempotent delivery and retry behavior. Scheduling uses site timezone with explicit handling of daylight saving.
- No quizzes, certificates, gamification, forums, private messaging or proprietary page builder.

## Initial use case: Held by Winter
Six weeks, common calendar schedule, immediate welcome, one week of post-experience access, late enrollment supported. Dates and enrollment cutoff must be configurable and confirmed in the site admin rather than hardcoded. Live gathering material may be added as needed.

## Implementation milestones
1. Data model, administration, WooCommerce access rules, content and private media protection.
2. Participant navigation, account integration, private discussion and theme compatibility.
3. Notifications, duplication, participant tools and previews.
4. Automated checks plus staging acceptance tests before production installation.

## Acceptance checks
Test paid, unpaid, guest checkout/account creation, canceled/refunded purchases, multiple purchases, manual overrides, late enrollment, release boundaries, expiration, unauthorized direct page/media/API/comment access, cache behavior across users, email duplicate prevention, audio playback/seeking, and mobile/theme compatibility. Confirm production hosting can support private media storage and delivery. Do not deploy untested access-control code to a live store.

## Open decisions
Hosting/server and cache configuration, WordPress/WooCommerce versions, target theme/editor, checkout account policy, and exact first-experience dates. Keep enrollment cutoff distinct from access expiration. Refund edge cases and notification defaults to be finalized during implementation. No customer information or credentials should be committed to this public repository.
