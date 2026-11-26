# Development build 0.1.0-dev

Not production-ready. Use dummy content only. No live installation has been performed.

## Initial usage (for controlled testing after review)
Create an Experience from the WordPress admin, set the WooCommerce product ID and optional local expiration date, and publish it. Create Experience content entries with the parent Experience ID, local release date and menu order. Publish them. On a dedicated Elementor page use the Shortcode widget with [kmd_experience id="123"] or [kmd_experiences]. Use account-associated paid test orders.

The initial renderer uses non-public custom post types and sanitizes stored HTML without executing shortcodes. It displays all released sections inline. It does not yet provide full Elementor editing of protected entries, private file delivery, comments, manual access, enrollment-relative releases, guest enrollment, notification emails, or duplication. Product IDs are entered manually. Do not upload confidential files to the standard media library.

## Gates before installation
- PHP lint and WordPress/WooCommerce integration tests.
- Validate order pagination and paid/refunded line items with HPOS and legacy order storage.
- Verify non-public post types against REST, feeds, search, XML sitemaps, direct queries, oEmbed, third-party search and Elementor endpoints.
- Ensure dedicated shortcode pages are excluded from all page/CDN caches, including anonymous cache hits. Shortcode-level no-cache headers alone are insufficient.
- Check site timezone boundaries, invalid dates, and DST transitions.
- Review admin capability isolation and test customer accounts.
- Verify rollback and backups before any controlled live test.

Owner has authorized controlled production testing, but this does not make untested code safe to deploy. Do not use actual member material until all access and cache checks pass. No production credentials or customer data are needed in GitHub.
