# Target integration

Confirmed by site owner:
- Production: https://innerspark.life
- WordPress page builder: Elementor Pro
- Theme: Hello Elementor
- Commerce: WooCommerce
- No staging site currently exists.

## Design decisions
Use native editable WordPress pages and Elementor-compatible shortcodes for dynamic experience navigation, account links, protected media and private sharing where appropriate. Keep frontend CSS scoped and minimal. Do not require a custom theme or proprietary page builder. Access enforcement must be independent of Elementor rendering, including preview, REST, cached HTML and direct URLs. Validate Elementor editor previews for authorized editors and ensure protected content cannot leak through public endpoints or cached output.

## Deployment gate
Develop independently of production. Establish a private staging or equivalent isolated WordPress test environment before live installation. Use WooCommerce test payments, disable real outgoing customer emails, avoid exposing copied customer data, and verify backups and rollback. Exact host, installed versions, checkout settings, caching and private storage capabilities remain unknown. No production changes authorized or performed by this document.
