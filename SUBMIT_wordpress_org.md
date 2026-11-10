# Submitting to the WordPress.org plugin directory

Status: **not submitted**. The zip is ready; nothing has been uploaded and no SVN repository exists yet.

| | |
|---|---|
| Plugin name | GunsNPrices Firearm Specs |
| Slug (from the Plugin Name header) | `gunsnprices-firearm-specs`, free on 2026-11-10 (`https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=gunsnprices-firearm-specs` → "Plugin not found"; pending or rejected submissions are not visible there) |
| Version | 0.1.0 (header, `GNPFS_VERSION`, readme `Stable tag`) |
| WordPress.org account | `gunsnprices` (readme `Contributors: gunsnprices`) |
| Zip | `dist/gunsnprices-firearm-specs.zip`, built by `sh tools/build-zip.sh` |
| Directory assets | `.wordpress-org/` (banners, icons, 6 screenshots) → SVN `assets/` after approval |
| Plugin URI / Author URI | https://gunsnprices.com |

## Before uploading

1. **Two-factor authentication.** Required for every plugin owner and committer and for submitting a new plugin
   since 1 October 2024
   (https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/).
   Log in as `gunsnprices`, open https://profiles.wordpress.org/me/profile/edit/group/1/ → "Account & Security",
   turn on two-factor (authenticator app) and save the backup codes.
2. **Account email on gunsnprices.com.** The slug starts with our brand. Guideline 17 forbids a brand as the first
   word of a slug "unless proof of legal ownership/representation can be confirmed", and the developer FAQ says the
   team knows it is really you "because of your email address mostly". Make sure the account email is
   `app@gunsnprices.com` or `info@gunsnprices.com` (Account & Security), and submit from that account only.
3. **Rebuild and recheck the zip** (from this folder):

   ```sh
   php tools/vendor-parser.php ../gunsnprices-php   # only if the parser changed; then commit
   sh tools/build-zip.sh                              # → dist/gunsnprices-firearm-specs.zip
   ```

   The submission page runs Plugin Check's "Plugin Repo" category on upload and blocks errors. Our local run on
   2026-11-10 (Plugin Check 2.2.0, WordPress 7.1.4, WooCommerce 11.2.2) found no errors or warnings.

## Uploading (together with the owner)

1. Go to https://wordpress.org/plugins/developers/add/ while logged in as `gunsnprices`.
2. Tick the confirmations (guidelines, GPL-compatible licence, ownership) and upload
   `dist/gunsnprices-firearm-specs.zip` (limit about 10 MB; ours is about 140 KB).
3. Check the slug shown on the confirmation screen: it should be `gunsnprices-firearm-specs`. It can be changed
   **once, before the review starts**; after approval it can never be renamed.
4. Wait for email from plugins@wordpress.org. The page promises a first review within 5 business days ("anywhere
   between 1 and 10 days"). The real queue is long: the Plugins team stats for 9 November 2026 list **6,026 plugins
   in the queue** (719 new and not yet looked at, 535 waiting on a reviewer, 4,517 waiting on their authors)
   (https://make.wordpress.org/updates/2026/11/09/plugins-team-9-nov-2026/). Expect 1–3 weeks.
5. If the reviewer asks for changes: fix them here, rebuild, upload the new zip from the same page and reply to the
   email. Answer every point; unanswered reviews are eventually closed.

Points a reviewer may raise, and the answers:

- *Bundled library modified?* `vendor-prefixed/gunsnprices/` is gunsnprices-php 0.1.0 (MIT,
  https://github.com/gunsnprices/gunsnprices-php) copied by `tools/vendor-parser.php`; the only changes are the
  namespace prefix (`GunsNPricesFirearmSpecs\Vendor\GunsNPrices`) and `esc_html()` around exception messages.
  `tools/` is in the Git repository but not in the zip.
- *Data licence?* `models.json` and `calibers.json` are CC BY 4.0, which the FSF lists as compatible with all
  versions of the GPL (https://www.gnu.org/licenses/license-list.html#ccby). We own the data, so if a reviewer still
  objects we can additionally license the bundled copy under GPLv2-or-later (owner's decision).
- *Generic shortcode name?* `[cost_per_round]` is registered only if no other plugin has taken it; the prefixed
  `[gnpfs_cost_per_round]` is always there. If asked, drop the unprefixed one.
- *Links to gunsnprices.com?* Front end: only the opt-in credit row, off by default. Admin: the CC BY attribution
  line on the settings and tools pages.
- *External requests / tracking?* None.

## After approval: SVN

The approval email gives `https://plugins.svn.wordpress.org/gunsnprices-firearm-specs/`. Commits need an SVN
password (2FA accounts cannot use the account password): Account & Security → "SVN credentials" → Generate Password.

```sh
svn checkout https://plugins.svn.wordpress.org/gunsnprices-firearm-specs ~/svn/gunsnprices-firearm-specs
cd ~/svn/gunsnprices-firearm-specs

# trunk = contents of the zip, without the top folder
rsync -a --delete ~/gunsnprices-oss/wp-gunsnprices/build/gunsnprices-firearm-specs/ trunk/
# assets = banners, icons, screenshots (top-level assets/, NOT trunk/assets)
cp ~/gunsnprices-oss/wp-gunsnprices/.wordpress-org/*.png assets/

svn add --force trunk assets
svn propset svn:mime-type image/png assets/*.png
svn cp trunk tags/0.1.0
svn status                                   # review before committing
svn ci -m "GunsNPrices Firearm Specs 0.1.0" --username gunsnprices
```

- `Stable tag: 0.1.0` in readme.txt points the directory at `tags/0.1.0`; new plugins may not use `trunk` as the
  stable tag.
- The page updates within minutes, sometimes up to 6 hours: https://wordpress.org/plugins/gunsnprices-firearm-specs/
- Asset names and limits (https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/): `banner-772x250`
  and `banner-1544x500` (≤ 4 MB), `icon-128x128` and `icon-256x256` (≤ 1 MB), `screenshot-N.png` (≤ 10 MB,
  captions are the numbered lines under `== Screenshots ==` in readme.txt).

## Next releases

1. Bump the version in the plugin header, `GNPFS_VERSION`, `Stable tag` and `blocks/cost-per-round/block.json`;
   add a Changelog entry; update `Tested up to` when a new WordPress major ships.
2. `sh tools/build-zip.sh`, run Plugin Check, then rsync into `trunk/`, `svn cp trunk tags/X.Y.Z`, commit.

## URLs

- Submit: https://wordpress.org/plugins/developers/add/
- Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Common review issues: https://developer.wordpress.org/plugins/wordpress-org/common-issues/
- Readme format: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- SVN: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- Assets: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- Developer FAQ: https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/
- 2FA and SVN passwords: https://make.wordpress.org/meta/handbook/tutorials-guides/svn-access/
- Plugin Check: https://wordpress.org/plugins/plugin-check/
