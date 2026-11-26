# KitMage Member Drip

A lightweight WordPress plugin for selling and delivering private, time-released experiences through WooCommerce. Create a seasonal container, workshop series, guided practice library, or self-paced program without setting up a full learning management system or community platform.

Participants sign in to access their purchased experiences, read teachings, listen to protected audio, download PDFs, and optionally share reflections with other participants.

**Current version:** 0.10.0-dev (installable development beta). PHP compatibility, WordPress/WooCommerce integration and HTTP access checks have passed in automated test environments. Production hosting, Elementor Pro, checkout and email delivery still need site-specific testing. Use dummy material and test accounts before admitting real participants.

## Contents

- [Requirements and installation](#requirements-and-installation)
- [Create your first experience](#create-your-first-experience)
- [Add weekly content](#add-weekly-content)
- [Design the participant pages](#design-the-participant-pages)
- [Upload private audio and PDFs](#upload-private-audio-and-pdfs)
- [Manage participants](#manage-participants)
- [Private reflections](#private-reflections)
- [Automatic emails](#automatic-emails)
- [Preview and duplicate experiences](#preview-and-duplicate-experiences)
- [Example seasonal experience](#example-seasonal-experience)
- [Before you launch](#before-you-launch)
- [Troubleshooting](#troubleshooting)
- [Updates, backups and privacy](#updates-backups-and-privacy)

## Requirements and installation

You need WordPress, an active WooCommerce installation, administrator access, and PHP 7.4 or later. Use a currently supported PHP version compatible with your WordPress and WooCommerce versions. Elementor is optional; the plugin also works through WordPress shortcodes. Participants need JavaScript enabled and must sign in to a WordPress account.

1. Make a complete website and database backup. If possible, test on a staging site; otherwise use only a controlled test on your live site.
2. Obtain the installable plugin ZIP from your developer or a successful [WordPress integration and beta package workflow](https://github.com/kitmage/kitmage-memberdrip/actions/workflows/integration.yml). The GitHub Actions artifact is an outer ZIP; extract that outer archive and use the inner `kitmage-memberdrip-beta.zip`. If you already have an installable `kitmage-memberdrip.zip`, do not extract it.
3. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
4. Select the installable ZIP, click **Install Now**, then **Activate Plugin**.
5. Open the new **Experiences** menu.
6. Check **Settings → General → Timezone**. Select your local city/timezone (for example, Chicago), rather than a fixed UTC offset, if your area observes daylight saving time.

The development-build warning is expected. Installing the plugin does not automatically create products or pages, change checkout settings, or enable participant emails.

### Important: customer accounts

Access is associated with the WordPress user ID on a paid WooCommerce order, not simply the buyer's email address. In **WooCommerce → Settings → Accounts & Privacy**, review guest checkout, login and account creation options. Make sure buyers of experience products sign in or create an account when purchasing.

The current plugin does not enforce account creation at checkout or automatically link historical guest orders. Disabling guest checkout globally may affect other store purchases, so review that decision first. For a guest order, verify the purchase and associate it with the correct account using WooCommerce or manually grant the verified account access. Never grant access based on an unverified email claim.

## Create your first experience

### 1. Create the product

In **Products → Add New**, create your WooCommerce product with its name, description, price and sales settings. For a digital-only experience, a simple virtual product is usually appropriate. WooCommerce continues to handle payments, discounts, receipts and refunds.

For an initial test, use a hidden, inexpensive test product and a dedicated customer account. Catalog visibility alone is not an access restriction. The plugin does not control product sales availability, enrollment deadlines, or stock; manage those separately in WooCommerce.

### 2. Create the experience

In **Experiences**, add a new experience.

- **Title:** the name participants will see in their dashboard.
- **Main editor:** welcome text, orientation, and introductory material that should be available to eligible participants immediately.
- **WooCommerce product:** select the product that unlocks this experience.
- **Optional variation ID:** leave blank for a simple product or when all variations should qualify. Enter a specific variation ID if only that variation should unlock it. This overrides the product selection.
- **Access closes:** enter the date and time access ends. Leave blank for no expiration.
- **Experience access page for email links:** select the participant page after creating it (see below). This also supplies the dashboard's entry link.
- **Enable automatic participant emails:** leave unchecked during setup and testing.

Save as a draft while preparing material. Publish when ready for the controlled test or launch. Publishing does not make private teachings publicly accessible; participants still need an eligible account.

Access ends at the exact configured closing time, not at the end of that date unless you explicitly set the appropriate time.

## Add weekly content

Open **Experiences → Experience content** and add a content section for each week, module or practice.

1. Enter a section title such as **Week 1 — Arriving**.
2. Add written material using the native WordPress editor. Headings, paragraphs, links and basic formatting are supported.
3. Under **Experience settings**, select the parent experience.
4. Choose a release rule and enter the corresponding date or delay.
5. Set the section's order using **Page Attributes → Order** (if shown). Lower numbers appear first; ties are ordered by creation ID.
6. Optionally check **Enable private reflections**.
7. Save as draft or publish. A published section with a future release stays locked until its release time. A draft section is not available to participants.

### Choose a release rule

| Rule | What it does | Example |
| --- | --- | --- |
| Calendar date | Everyone receives the section at the same site-local date and time. | January 24 at 9:00 AM |
| Days after enrollment | Each participant receives the section a specified number of elapsed 24-hour days after enrollment. | 7 days after purchase |

For a normal calendar section, a blank release date means immediate availability. For a relative section, enter **0** for immediate availability; a blank or invalid delay leaves the section locked. Fixed dates remain the default.

A participant joining a calendar-based experience late receives previously released sections immediately. Relative timing starts at the earliest currently qualifying paid purchase or the recorded manual grant date. If an earlier order is refunded, the remaining qualifying purchase may change that date. Relative delays are elapsed days, not local calendar days; daylight saving changes can shift the displayed hour.

The experience's closing date applies to both release methods. Purchase-relative expiration is not currently supported.

**Keep private material in the experience and content-section editors, not in ordinary public WordPress pages or the public media library.** Arbitrary shortcodes and dynamic blocks are not executed in the protected content renderer.

## Design the participant pages

Create ordinary WordPress pages for the layout. The plugin loads eligible material into the page after checking the visitor's signed-in account.

### Individual experience page

1. Edit the experience and note its numerical ID. The edit-page address contains a value such as `post=123`; use that number.
2. Create a page named after your experience.
3. In Elementor, add a **Shortcode** widget where the protected material should appear. In the WordPress editor, use a **Shortcode** block instead.
4. Insert this shortcode, replacing 123 with the actual experience ID:

   ```text
   [kmd_experience id="123"]
   ```

5. Publish the page, then return to the experience settings and select it as the **Experience access page for email links**.

The surrounding page can use your theme or Elementor design. The plugin provides a welcome area, release navigation, unlocked sections, audio players, download links and optional reflections. Its lightweight styling inherits the site's fonts and colors.

The surrounding page and its headings are public. Do not place confidential teachings, meeting links or private file URLs outside the shortcode.

### My Experiences page

Create a separate page named **My Experiences** and insert:

```text
[kmd_experiences]
```

Publish it and add it to your menu or WooCommerce account navigation using your usual WordPress tools. The plugin does not automatically create a WooCommerce account menu item. Eligible participants see their active experiences, closing dates and links to enter them. If an experience has no assigned access page, its material is shown directly in the dashboard.

A signed-out visitor sees a sign-in link. Someone without active access sees a generic unavailable message or an empty dashboard.

## Upload private audio and PDFs

**Do not upload confidential files to the standard WordPress Media Library.** Ordinary media URLs may remain accessible even when the experience is locked.

### One-time hosting setup

Private uploads require a directory outside all publicly accessible website folders. Ask your hosting provider or website administrator to:

- Create a private directory outside all web document roots and public aliases, writable by the PHP service account and restricted to that account.
- Define `KMD_PRIVATE_DIR` in the site's `wp-config.php` using the actual absolute directory path.
- Confirm there is no direct web or CDN access to that directory, and include it in the site's secure backup plan.

You can forward this example to your host; do not copy the placeholder path literally:

```php
define('KMD_PRIVATE_DIR', '/absolute/path/to/private-memberdrip-files');
```

If storage is not configured or fails the plugin's directory checks, private uploads are unavailable. Do not work around this by selecting a public folder.

### Upload and remove files

In **Experiences → Files & access**:

1. Under **Upload private material**, choose the content section.
2. Choose an audio file (MP3, M4A, OGG or WAV) or PDF.
3. Click **Upload private file**.

The plugin validates file type and uses the host's upload-size limit. Audio appears in a player; PDFs appear as download links. Each new file request checks enrollment, expiration and the section's release rule.

Under **Manage private files**, find the file, tick **Confirm removal**, then click **Remove file**. This revokes future access and attempts to delete the private copy. If physical deletion fails, contact your host using the error shown. A transfer already underway may finish. Previously downloaded copies cannot be recalled; this is access control, not digital rights management.

## Manage participants

### Automatic access

An eligible, paid WooCommerce order associated with a signed-in customer account unlocks the matching published experience. Pending and unpaid orders do not qualify. Fully refunded orders and refunded line quantities are checked when deciding access. For unusual partial refunds or disputes, review the account manually.

### Gift, block, restore or extend access

Go to **Experiences → Files & access → Participant access**.

1. Select the experience.
2. Enter the participant's exact existing WordPress account email.
3. Choose one of the following access decisions.
4. Optionally enter a personal closing date.
5. Click **Save access**.

| Decision | Result |
| --- | --- |
| Grant access | Provides access without a qualifying order, subject to publication and expiration. |
| Block access | Denies access even if a qualifying order or extension exists. |
| Restore purchase rules | Removes the manual override and uses WooCommerce eligibility again. |

An optional personal closing date must be later than the valid, finite experience closing date. It extends an existing entitlement but does not independently grant access. Leave it blank to remove a previous extension. A manual grant overrides purchase eligibility, so remove or block that grant separately if the participant should no longer have access.

A participant must already have an account. Use WordPress **Users** to create or locate it; do not share passwords. A previously granted account without a recorded enrollment date must have its grant saved again before using relative releases.

## Private reflections

Enable **private reflections** in any content section where you want participants to share. There is no separate forum or community page.

Eligible participants can submit plain-text reflections and one level of replies. Their display name is visible to other eligible participants, and they should share only what they are comfortable sharing with the group.

In **Experiences → Reflections**, review submissions:

- **Approve** makes the entry visible.
- **Hide** removes it from participant view until approved again.
- **Delete with replies** permanently removes the entry and any direct replies.

All submissions require approval. Reflections are separate from ordinary WordPress comments and are not shown in public comment feeds. When access expires, participants lose access to the sharing area; stored reflections are not automatically deleted.

Current limits: the participant view shows up to 500 approved entries per section; moderation lists up to 100 entries, pending first. Staff replies without enrollment and reply notifications are not yet supported.

## Automatic emails

Each experience has independent email settings. Emails are **off by default**. Select a published access page and enable automatic participant emails only after testing.

| Email | When it is sent |
| --- | --- |
| Welcome | When the scheduled notification process first discovers an eligible participant. |
| New material | When a section releases after the participant's welcome message, within the release notification window. |
| Closing reminder | Within seven days of the participant's effective closing date. |

A late participant's welcome email directs them to material already available; it does not send a separate email for every previous release. Existing eligible participants may receive a welcome email when notifications are first enabled.

WordPress cron processes batches of accounts every five minutes when scheduling runs normally. Delivery is not instant or guaranteed. Large account lists, low site traffic, mail configuration or interrupted jobs can delay messages. Ask your host to confirm reliable cron and mail delivery. Test with an isolated recipient before enabling messages for real customers.

### Customize the wording

In the experience editor, under **Optional email wording**, enter your own subject and plain-text message. Leave a field blank to use the default.

Use these placeholders exactly as shown:

| Placeholder | Inserts |
| --- | --- |
| `{experience}` | Experience name |
| `{section}` | Section name (release emails only) |
| `{link}` | Participant access page |
| `{expiry}` | Effective closing date and time, if applicable |

Example release email:

```text
Subject: A new week of {experience} is ready

Hello,

{section} is now available whenever you are ready to explore it.

Enter your experience here:
{link}

With warmth,
InnerSpark
```

The plugin appends the access link if it is missing. Messages contain links, not private lessons or attachments. Disabling emails stops future scheduled messages; it cannot recall messages already sent. Duplicate-send safeguards are included, but interrupted sending may need administrator investigation.

## Preview and duplicate experiences

### Preview

From the **Experiences** list, choose **Preview schedule** beneath an experience. Set a simulated viewing time and enrollment time, then choose **Simulated schedule** or **Show all written material**.

Preview is read-only and administrator-only. It does not change enrollment, trigger emails or modify the real schedule. It shows written content and file names, not actual private playback, participant reflections or the exact Elementor layout. Use a dedicated test account to verify the real participant view.

### Duplicate

From the **Experiences** list, choose **Duplicate as draft**.

The copy includes written material, section order, sharing preferences and custom email wording. The original experience remains unchanged. All copied posts are drafts.

Before publishing the copy, select a new product and access page, set the closing date and every section's release rule, review the written material, upload private files and decide whether to enable emails. The duplicate intentionally does not inherit participants, access overrides, reflections, media records, previous email history or dates. Dates must be explicitly configured; the copy stays locked until you do so.

## Example seasonal experience

Here is an illustrative six-week **Held by Winter** setup. Adjust the dates and times to match your actual offering.

- WooCommerce product: Held by Winter.
- Experience welcome: immediately available after an eligible purchase and publication.
- Release rule: calendar date.
- Enrollment deadline: manage separately in WooCommerce; not enforced by the plugin.
- Access closes: March 7, 2027 at 9:00 AM (illustrative site-local time).

| Section | Example release | Order |
| --- | --- | --- |
| Week 1 | January 17, 2027, 9:00 AM | 10 |
| Week 2 | January 24, 2027, 9:00 AM | 20 |
| Week 3 | January 31, 2027, 9:00 AM | 30 |
| Week 4 | February 7, 2027, 9:00 AM | 40 |
| Week 5 | February 14, 2027, 9:00 AM | 50 |
| Week 6 | February 21, 2027, 9:00 AM | 60 |

Use the same section structure each week: welcome or teaching, an audio practice, written prompts, optional PDF, and optional reflections. Weeks already released remain available until access closes.

## Before you launch

Complete this checklist using dummy material and separate test accounts. If there is no staging site, keep the test narrowly scoped to a hidden test product and dedicated accounts. Do not use real participant material or enable customer notifications prematurely.

- [ ] Back up WordPress, its database and private storage; confirm you can restore them.
- [ ] Verify the site timezone, release dates, closing date and product selection.
- [ ] Confirm buyers must have an associated WordPress account.
- [ ] Confirm the dashboard and individual Elementor page work on desktop and mobile.
- [ ] Verify an unpaid order and a separate unrelated account cannot access material.
- [ ] Complete a controlled test purchase and confirm immediate access.
- [ ] Verify future material is locked and released material is readable.
- [ ] Test direct audio/PDF links when signed in, signed out, as another customer, and before release.
- [ ] Play audio, seek within it, and download a PDF.
- [ ] Verify expiration, manual grant, block, extension and refund behavior.
- [ ] Submit, approve, reply to and hide a test reflection.
- [ ] Have your host confirm caches/CDNs never cache private portal or file responses. Exclude WordPress `admin-ajax.php` and `admin-post.php` from caching.
- [ ] Switch accounts and verify that protected content is not shared between sessions.
- [ ] Verify WordPress cron and actual welcome, release and closure email delivery to test recipients; check for duplicates.
- [ ] Verify the real sales and enrollment cutoff settings before opening the product to customers.

The automated tests are useful, but do not replace this site-specific walkthrough.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| Experiences menu is missing | Confirm the plugin is active and you are signed in as an administrator. |
| Customer paid but cannot enter | Confirm the experience is published, the correct product/variation is selected, the order is paid and linked to their WordPress account, access has not expired, and no manual block exists. |
| Section remains locked | Confirm it is published, assigned to the correct experience, and its release rule and date/delay are valid. Check the site timezone. |
| Experience stays on “Loading” | Confirm JavaScript is enabled, the shortcode is correct, and scripts and the WordPress AJAX endpoint are not blocked by caching or security settings. |
| Dashboard entry link is missing | Select a published experience access page in the experience settings. |
| Private upload is unavailable | Have your host configure and verify private storage. Check upload limits and file types. |
| Audio will not play | Confirm the participant has access, the file format is supported, and the server supports authenticated byte-range responses. |
| Reflection is not visible | Check the section's sharing setting, enrollment, release date and moderation status. |
| Welcome or release email did not arrive | Verify emails are enabled, the access page is published, the account is eligible, cron is running and WordPress mail is working. Check spam. Avoid repeatedly re-enabling emails or resetting records without investigating. |
| A private page appears to expose someone else's data | Stop using the portal, disable the plugin if necessary, clear relevant caches and contact your developer immediately. |

## Updates, backups and privacy

Keep backups of the WordPress database and the separate private media directory. Update using a tested replacement ZIP; do not delete the plugin or its data merely to install an update. Confirm the plugin's features after any WordPress, WooCommerce, Elementor or hosting change.

WordPress **Tools → Export Personal Data** and **Erase Personal Data** include the participant's own stored reflections. Erasure removes the original text and author association while preserving the thread structure for other participants. Use this workflow before deleting a user account. Other participant metadata, WooCommerce order records, quoted text in other people's submissions and backups may need separate handling.

Expiration revokes access; it does not automatically erase participant records or media. Automatic retention, full plugin-specific data erasure and uninstall cleanup remain incomplete in this beta. Establish a privacy and retention policy appropriate for your offering.

If something goes wrong during testing, deactivate KitMage Member Drip under **Plugins**. This stops its runtime functionality and scheduled notification hook but does not automatically erase its records or private files. Preserve the backup and contact your developer before making destructive changes.

## Development and support

- [Source code](https://github.com/kitmage/kitmage-memberdrip)
- [Automated tests and beta packages](https://github.com/kitmage/kitmage-memberdrip/actions)
- [Report an issue](https://github.com/kitmage/kitmage-memberdrip/issues)
- [Technical development notes](DEVELOPMENT.md)
- [Original requirements](SPECIFICATION.md)
- [Integration notes](INTEGRATION.md)

When reporting an issue, include the plugin version, WordPress/WooCommerce/PHP versions, steps to reproduce, and whether the account was eligible. Do not post real participant information, passwords, private files or authentication tokens in public GitHub issues.
