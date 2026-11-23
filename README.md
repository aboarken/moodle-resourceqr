# Resource QR Code (`local_resourceqr`)

Lets authorised staff generate a QR code for any Moodle activity or resource. The code links
to the activity's normal Moodle page, so anyone who scans it still goes through Moodle's login,
enrolment and access-restriction checks.

- Generated on the server with the QR encoder that ships with Moodle core (TCPDF). No external
  service, no third-party library, and no images are stored.
- Download as PNG or SVG, print, or copy the link.
- Three designs (rounded, dots, square), a configurable colour and an optional site-logo centre.
- Access is controlled by one capability, `local/resourceqr:generate`.

---

## Contents

1. [Compatibility](#compatibility)
2. [Installation](#installation)
3. [Administrator guide](#administrator-guide)
4. [User guide](#user-guide)
5. [Technical documentation](#technical-documentation)
6. [Testing](#testing)
7. [Security](#security)
8. [Known limitations](#known-limitations)

---

## Compatibility

| Moodle | Minimum PHP | Status |
|---|---|---|
| 4.5 LTS (from 4.5.0) | 8.1 | Supported. PHPUnit suite and browser checks run on 4.5.16 (PHP 8.3). |
| 5.0 | 8.2 | Supported. Covered by the CI configuration; not run locally. |
| 5.1 | 8.2 | Supported. Covered by the CI configuration; not run locally. |
| 5.2 | 8.3 | Supported. PHPUnit suite run on 5.2.5 (PHP 8.3). |
| 5.3 LTS | 8.3 | Supported (primary target). Code checked against the 5.3.1 source; PHPUnit not run locally (see [Testing](#testing)). |

The plugin itself uses no PHP feature newer than 8.1.
Minimum PHP versions are from each release's `admin/environment.xml`.
`version.php` sets `requires = 2024100700` (Moodle 4.5.0) and `supported = [405, 503]`.

Themes: Boost (and Boost-based themes) and Classic. Other themes work wherever they show the
settings navigation. Moodle 5.3 no longer bundles Classic; if you install it separately, the plugin
works with it in the same way.

Requirements:

- **GD or Imagick** PHP extension for PNG downloads. GD is preferred when both are present. Without
  either, PNG is hidden and an admin notice is shown; SVG still works.
- No other requirements. The QR encoder is Moodle core's `lib/tcpdf/tcpdf_barcodes_2d.php`.

## Installation

### From the ZIP

1. Go to *Site administration > Plugins > Install plugins*.
2. Upload `local_resourceqr.zip` (its top-level folder is `resourceqr/`) and follow the prompts.

### Manually

Copy the `resourceqr` folder into Moodle's `local` folder:

| Moodle | Install path |
|---|---|
| 4.5, 5.0 | `<moodle>/local/resourceqr` |
| 5.1 and later | `<moodle>/public/local/resourceqr` |

Then visit *Site administration > Notifications*, or run:

```bash
php admin/cli/upgrade.php
```

(On 5.1 and later the CLI scripts are under `public/admin/cli/`.)

After installation the plugin is enabled for every activity type.

## Administrator guide

### Settings

*Site administration > Plugins > Local plugins > Resource QR Code*

| Setting | Default | Notes |
|---|---|---|
| Enable QR codes | Yes | Turns the whole feature off for everyone when unticked. |
| All activity types | Yes | Every activity type with its own page, including ones installed later. |
| Enabled activity types | All | Shown when *All activity types* is off. Types without a page (Text and media area, subsections, question banks) are not listed. |
| QR code size | 600 | PNG width and height in pixels, 200 to 2000. Use 600+ for printing. SVG scales to any size. |
| QR code design | Rounded | Rounded, Dots or Square (classic). Codes with very small squares (under 5 px each) always use Square so they stay readable. |
| QR code colour | `#0f47ad` | Must have a contrast ratio of at least 4.5:1 with white. Lighter colours are refused because they don't scan reliably. The background is always white. |
| Error correction level | M | L, M, Q or H. Forced to H when the logo is shown. |
| Show site logo in centre | No | Uses the compact logo (or the full logo if there is no compact logo) from *Appearance > Logos*. The logo and its white padding take up no more than 20% of the code's width. |
| Allowed download formats | PNG, SVG | At least one is required. The on-screen preview is always SVG. |
| Cancel all expiring QR codes | (button) | Stops every QR code with an expiry date from working, straight away, after a confirmation. See *Cancelling expiring QR codes* below. |

### Cancelling expiring QR codes

If a QR code with an expiry date was shared with the wrong people, a site administrator can stop
it before its date: **Cancel all expiring QR codes** at the bottom of the plugin settings, then
confirm. The plugin's signing key is replaced, so every QR code with an expiry date made before
then shows "QR code not valid" when scanned. QR codes made afterwards work as usual. This applies
to the whole site and cannot be undone. The settings page shows when it was last done, and it is
recorded in *Reports > Config changes* (the key itself is never shown).

Lifetime QR codes hold the activity's own link and are not affected: to stop one, hide or delete
the activity.

### Capability

Access is controlled by a single capability, `local/resourceqr:generate` (activity context, no
risks). It is managed like any other Moodle capability, in *Site administration > Users >
Permissions*. QR codes can only be generated for activities the user is able to see.

### Logs

Each generated image is logged as **QR code generated**
(`\local_resourceqr\event\qrcode_generated`, read event, activity context). The description says
whether it was a preview or a download and in which format. View the logs in
*Site administration > Reports > Logs* or in a course's *Reports > Logs*, filtered by activity.
Opening the dialogue logs one preview, and each download logs one more event.

### Privacy

The plugin stores no personal data and has no database tables. It declares a privacy `null_provider`.
Its events are stored by the site's log stores, which handle privacy requests for them.

## User guide

### Making a QR code

**For any activity or file in a course:** on the course page, open **More** in the course menu (in
Classic, the *Course administration* block) and choose **QR codes**. The page lists the course's
activities and resources by section; choose **Generate QR code** next to the one you want. This is
the easiest way to find QR codes, and it works for files that open or download straight away.

**From inside an activity:**

1. Open the activity or resource (for example the quiz or the file).
2. In Boost-based themes, open **More** in the menu under the activity name and choose
   **Generate QR code**. In Classic, use **Generate QR code** in the *Administration* block.
3. A dialogue opens with:
   - the activity name,
   - the QR code,
   - the link the code opens,
   - a warning if the activity is hidden, not shown on the course page, in a hidden section, or has
     access restrictions (some people may not be able to open it),
   - buttons to **Download PNG**, **Download SVG**, **Print** and **Copy link**.

Without JavaScript, the menu item opens a page with the same content.

### Lifetime or expiry date

Under **Validity** in the dialogue, choose:

- **Lifetime (never expires)** (the default): the QR code holds the activity's normal link and
  works for as long as the activity exists.
- **Valid until** a date: the QR code works until the end of that day (23:59 in your time zone),
  up to 10 years ahead. The QR code, the link, the downloads and the print page all change to match,
  and "Valid until …" is shown under the code and on the printout.

A QR code with an expiry date holds a short plugin link (`/local/resourceqr/go.php?…`) instead of
the activity's link. Until the date, it takes people straight to the activity, where Moodle's
usual login, enrolment and access checks apply. After the date it shows "This QR code has expired".
The link is signed, so the date can't be changed by editing it. Choose a new date and download or
print again to give out a new QR code; QR codes already printed keep their own date.

**An expiry date limits the QR code, not access to the activity.** Someone who scanned the code
before the date ends up on the activity's normal page, and can bookmark or share that address like
any Moodle link. Who can open the activity is still decided by Moodle: enrolment, visibility, access
restrictions and permissions. Use those to control access; use an expiry date to stop a printed or
shared QR code from leading anywhere after an event.

### Downloading

- **PNG:** a picture file, good for documents, slides and most printing.
- **SVG:** a vector file that stays sharp at any size, good for posters and design tools.

The file is named after the activity, e.g. `qrcode-week_1_quiz.png`. A message confirms the
download, or reports a problem.

### Printing

**Print** opens a page with only the activity name, the QR code and the link, and opens your
browser's print dialogue. Print at least 2 cm (about 1 inch) wide; bigger is easier to scan from
a distance.

### What people see when they scan it

The code holds the activity's normal Moodle address, for example
`https://moodle.example.org/mod/quiz/view.php?id=123`. It contains nothing about you or your
session. People who scan it may need to log in, must be enrolled, and must meet any access
restrictions, exactly as if they had clicked a link in the course.

## Technical documentation

### Where the action appears and why

The preferred place, the activity's three-dot menu on the course page, **is not extensible by local
plugins** in any supported version. In Moodle 5.3.1, `core_courseformat\output\local\content\cm\controlmenu::get_cm_control_items()`
builds a fixed list of items. Only course *format* plugins can change it, by overriding that
class. There is no hook in `db/hooks.php` and no callback (compare MDL-74942 and MDL-75366).
Overriding the format's renderers or injecting the item with JavaScript was ruled out as
unsupported and fragile.

The plugin therefore uses the activity's **settings navigation**, through the
`local_resourceqr_extend_settings_navigation()` callback in `lib.php`. That callback is still the
supported mechanism in 5.3; it has no hook replacement (`core\hook\navigation\secondary_extend`
exists but is for site-level secondary navigation). The node is added to the `modulesettings`
branch:

- In Boost it appears in the activity's secondary navigation **More** menu.
- In Classic it appears in the **Administration** block.
- For roles that have no other activity settings, core removes the empty `modulesettings` branch.
  The callback recreates it so the item still shows.

The node is a normal link to `view.php`. `amd/src/qrcode_dialogue.js` listens for clicks on that
link (event delegation on `document`, no DOM changes) and opens a `core/modal` dialogue instead.

### Request flow

```
More > Generate QR code (link to view.php?cmid=N)
  └─ qrcode_dialogue.js intercepts the click
       └─ core/ajax: local_resourceqr_get_qrcode_data(cmid)
            validate_context() → require_login(course, cm)
            access::require_generate(cm)
            output\qrcode_panel → export_for_template()
       └─ Modal.create({title, body: Templates.render('local_resourceqr/qrcode_panel')})
            ├─ <img src="qr.php?cmid=N&format=svg&download=0">         (preview)
            ├─ Download PNG/SVG → fetch(qr.php?…&download=1) → blob    (qrcode_panel.js)
            ├─ Copy link → core/copy_to_clipboard (toast)
            └─ Print → print.php?cmid=N&autoprint=1 (new tab)
```

### Files

| Path | Purpose |
|---|---|
| `lib.php` | Activity and course navigation callbacks, and the Font Awesome icon map. |
| `view.php` | Stand-alone page (no-JavaScript fallback). |
| `go.php` | Public link held by QR codes with an expiry date: redirects to the activity until the date, then shows that the code has expired. |
| `classes/local/expiry.php` | Signs and checks expiring links (HMAC-SHA256 with a site secret), converts dates, replaces the secret to cancel them. |
| `course.php` | Course "QR codes" page listing the course's activities and resources. |
| `print.php` | Print page: name, code and URL only (`embedded` layout plus print CSS). |
| `qr.php` | Image endpoint. `cmid` (`PARAM_INT`), `format` (`PARAM_ALPHA`, `png`/`svg`), `download` (`PARAM_BOOL`). |
| `classes/local/access.php` | All access rules, the encoded URL, warnings and download formats. |
| `classes/local/qr_generator.php` | Gets the module matrix from TCPDF and draws it as SVG, or as PNG with GD or Imagick. |
| `classes/local/image.php` | Checks access, generates the image, triggers the event, sends headers. |
| `classes/local/filename.php` | Download file names from activity names. |
| `classes/output/qrcode_panel.php` | Renderable/templatable for the panel; also the external function's return data. |
| `classes/external/get_qrcode_data.php` | AJAX web service (`ajax => true`, read). |
| `classes/event/qrcode_generated.php` | Log event. |
| `classes/privacy/provider.php` | `null_provider`. |
| `classes/admin/setting_*.php` | Validated admin settings (size, colour, formats, activity types). |
| `amd/src/qrcode_dialogue.js` | Opens the dialogue from the navigation link. |
| `amd/src/qrcode_panel.js` | Downloads with feedback, image error handling, copy link. |
| `amd/src/print.js` | Print button and automatic printing. |
| `templates/qrcode_panel.mustache`, `templates/print.mustache` | Markup. |
| `db/access.php`, `db/services.php` | Capability, web service. |

| `db/install.php`, `db/upgrade.php` | Create the key that signs QR codes with an expiry date (install, and upgrade from 0.2.1 or earlier; an existing key is kept). |
| `resetlinks.php` | Confirmation page for *Cancel all expiring QR codes*. |
| `tests/behat/behat_local_resourceqr.php` | Behat step that opens a valid, expired or forged expiring link. |

There is no `db/install.xml`: the plugin has no tables and stores nothing per QR code.

### Access rule (every entry point)

`view.php`, `print.php`, `qr.php` and the web service all do the following:

1. `require_login($course, false, $cm)` (in the web service: `validate_context()`, which does the same).
2. `access::require_generate($cm)`, which requires:
   - the plugin to be enabled, and the activity type to be enabled and have a view page,
   - `require_capability('local/resourceqr:generate', $modulecontext)`,
   - `$cm->uservisible`, or `moodle/course:viewhiddenactivities` in the activity.

The other entry points:

- `course.php` (course *QR codes* page): `require_login($course)`, the plugin enabled, and
  `local/resourceqr:generate` in the course; each activity listed must then pass
  `access::can_generate()` itself.
- `go.php` (expiring links): public, see *Security model* below.
- `resetlinks.php`: `admin_externalpage_setup()` (`moodle/site:config`), and the change itself
  needs a POST with `sesskey`.

`cmid` is never trusted on its own: the course and activity come from
`get_course_and_cm_from_cmid()` for the current user, then go through the checks above. Image
generation is read-only, so it needs no sesskey.

### What is encoded

`cm_info::get_url()`, which is built from `$CFG->wwwroot` (so HTTPS and custom domains work).
Any `sesskey`, `token`, `wstoken`, `key`, `user`, `userid`, `username` or `session` parameter is
stripped as a precaution; core view URLs contain only `id`. For **URL** resources, the code points
to `/mod/url/view.php?id=…`, not to the external address, so Moodle's checks still apply.

### QR generation

`core_qrcode` is a thin wrapper around `TCPDF2DBarcode`. It always uses error-correction level L
and draws without a quiet zone, which doesn't meet the requirements here. The plugin uses the same
core class directly (`new TCPDF2DBarcode($url, 'QRCODE,M')`), takes the module matrix from
`getBarcodeArray()` and draws it itself:

- a 4-module quiet zone (as the QR standard requires) and whole-pixel modules, centred in the
  requested size;
- the design is built as a list of shapes (rounded rectangles and circles). The SVG, GD and Imagick
  back ends all paint the same list, so PNG and SVG always match;
- GD draws curved designs on a canvas up to 4× larger and scales it down, for anti-aliasing;
- the logo box is a whole number of modules, centred on the module grid, at most 20% of the symbol
  width; its modules are cleared and level H is forced.

SVG responses are sent with a restrictive `Content-Security-Policy` (`default-src 'none'; sandbox`)
and `X-Content-Type-Options: nosniff`. All images are sent with `no-store` cache headers.

### Supporting new or third-party modules

There is nothing to do. Any module that is installed, has a `view.php` URL (`cm_info::get_url()`
not null), does not declare `FEATURE_NO_VIEW_LINK`, does not declare `FEATURE_CAN_DISPLAY => false`
(5.0+), and does not delegate a course section (`mod_subsection`) is supported automatically.

To exclude a module type, untick *All activity types* and leave it out of *Enabled activity types*.

### Building JavaScript

The built files in `amd/build/` are committed. To rebuild them, from the Moodle root (with Moodle's
`npm ci` done):

```bash
npx grunt amd --root=local/resourceqr
```

## Testing

### Automated tests in this repository

- `tests/*_test.php`, `tests/external/`, `tests/privacy/`: PHPUnit. They cover:
  - access rules: users with and without the capability, and users not enrolled in the course;
  - hidden, stealth, hidden-section and restricted activities;
  - disabled plugin and disabled module types;
  - URL resolution for 16 module types, and modules without a view page;
  - the QR renderer, including decoding PNG and SVG output with `zbarimg` (skipped if it isn't
    installed), every design, every error-correction level, and the logo, with GD and with Imagick
    (skipped if the Imagick extension isn't installed), and an SVG logo never reaching ImageMagick;
  - expiring links: signing, dates, resolution, the signing key's lifecycle (created on install,
    never created by checking a link) and cancelling all expiring QR codes;
  - settings validation, the event, the external function, navigation, file names and privacy.
- `tests/behat/resourceqr.feature`: the action for authorised and unauthorised users; the dialogue
  (name, QR, URL, copy, downloads, print); hidden warning; format restriction; disabled plugin;
  no-JavaScript page; Classic theme; mobile viewport; course QR codes page; making a QR code with an
  expiry date; opening a valid, expired and forged expiring link, logged in and logged out (step in
  `tests/behat/behat_local_resourceqr.php`); cancelling all expiring QR codes.
- `.github/workflows/ci.yml`: moodle-plugin-ci on Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 (PHP 8.3, plus
  8.4 on 5.3) with PostgreSQL 17. It runs phplint, phpcs (moodle-cs), phpdoc, validate, savepoints,
  mustache, grunt, PHPUnit and Behat, and installs `zbar-tools` and the Imagick extension for the
  decoding tests.

### What was actually run

Latest audit (0.2.2, see [docs/AUDIT.md](docs/AUDIT.md)):

| Check | Result |
|---|---|
| PHPUnit, Moodle 4.5.16+, PHP 8.3.6, PostgreSQL 16, GD **and** Imagick (ImageMagick 6.9.12), zbarimg | 155 tests, 732 assertions, all pass |
| Upgrade 0.2.1 → 0.2.2 on a real 4.5 site (CLI `upgrade.php`) | Site without a key gets a new 256-bit key; site with a key keeps it |
| `go.php` over HTTP (PHP built-in server) | Valid link: 303 to the activity. Expired: "QR code expired". Forged or junk parameters: "QR code not valid", nothing reflected. No key in the database: refused, and no key created |
| phpcs `moodle-extra` (moodlehq/moodle-cs) on every PHP file | No errors or warnings |
| CI (moodle-plugin-ci: lint, phpcs, phpdoc, validate, savepoints, mustache, grunt, PHPUnit, Behat), run [45184107617](https://github.com/aboarken/moodle-resourceqr/actions/runs/45184107617) | All six jobs pass: Moodle 4.5, 5.0, 5.1, 5.2, 5.3 with PHP 8.3, and 5.3 with PHP 8.4. On 4.5: PHPUnit 155 tests, 732 assertions, none skipped (Imagick included); Behat 17 scenarios pass (5.3: 16 pass, the Classic-theme one is skipped because 5.3 has no Classic) |

Earlier development checks:

| Check | Result |
|---|---|
| Browser check on a real 4.5.16 site (Playwright/Chromium) | Boost, Classic and a 390 px phone screen: menu item, dialogue, preview, PNG/SVG downloads (files decode), copy link toast, Escape closes and returns focus to the More menu, hidden-activity warning, print page, a user without the capability sees no item and gets "no permission" on every page |
| Decoding of 144 generated images (3 designs × with/without logo × levels L/M/Q/H × 200/600/2000 px, PNG and Chromium-rendered SVG) with zbarimg | All decode to the right URL |
| Grunt `amd` (ESLint + build), `stylelint`, `gherkinlint` with Moodle 4.5's tooling | Pass |

**Not run locally** (they run in CI):

- **PHPUnit on Moodle 5.x:** 5.3 needs PostgreSQL 17, and only 16 was available here. The 5.3.1
  source was checked for every API the plugin uses.
- **Behat:** no Selenium/ChromeDriver here.

### Still to check on your own site

1. Install on your Moodle 4.5 site and check the item appears under **More** for a user who has the capability.
2. Scan a printed code with the phones your staff and students use (including the Moodle app, see
   below).
3. If you turn on the site logo, scan a code that has it.

## Security

To report a security problem, see [SECURITY.md](SECURITY.md) (privately, through the repository's
**Security** tab; never in a public issue). The "Release package" workflow runs with read-only
permissions except for the release step, its actions are pinned to exact commits and kept up to
date by Dependabot (`.github/dependabot.yml`), and changes to the workflow and the plugin's access
checks are reviewed by the code owner (`.github/CODEOWNERS`). The workflow does not keep its
GitHub token in the checked-out files. The last full audit, its findings and what was changed are in
[docs/AUDIT.md](docs/AUDIT.md).

### Security model

- **Who can make QR codes:** users with `local/resourceqr:generate` in the activity who can also
  see it (`$cm->uservisible`, or `moodle/course:viewhiddenactivities`). Every page and the web
  service check this for the activity in the request, after `require_login()`; an id in a URL never
  grants anything by itself.
- **What a QR code holds:** only the activity's own address (lifetime), or a signed `go.php` link
  (expiry date). No user, session or token data is ever encoded.
- **`go.php` is public on purpose,** because QR codes are scanned by people who aren't logged in
  yet. It does nothing unless the link carries a valid signature: an HMAC-SHA256 of the activity id
  and expiry time with the site's signing key, truncated to 80 bits and compared in constant time.
  It only ever redirects to the activity's own page on this site, where Moodle's access checks
  apply, so it can't be used to reach anything else or to redirect elsewhere. Checking a link never
  writes to the database.
- **The signing key** is 256 random bits, created on install (or on upgrade), and stored as
  `local_resourceqr/secret` in the site configuration (`config_plugins` table). It is never shown
  in the interface. Anyone who can read the database can make valid expiring links, so protect
  database backups as usual. A copy of the site (for example a test site restored from a backup)
  has the same key: its expiring links also work there. *Cancel all expiring QR codes* replaces the
  key on that site only.
- **Images** are generated on request and never stored. SVG files are sent with
  `Content-Security-Policy: default-src 'none'; sandbox` and `X-Content-Type-Options: nosniff`, and
  hold no script. SVG site logos are embedded in SVG codes only and are never passed to an image
  library.
- **Logs:** each preview, download and print is logged (`\local_resourceqr\event\qrcode_generated`),
  with the activity, format and expiry. Scanning a code is not logged by the plugin; Moodle logs the
  activity view as usual.

## Known limitations

- **Menu location:** the action is in the activity's **More** menu (or the Classic *Administration*
  block), not in the three-dot menu on the course page. Moodle offers local plugins no supported
  way to add to that menu. Users open the activity first, then use **More > Generate QR code**.
- **Activities without their own page** (Text and media area / `mod_label`, subsections /
  `mod_subsection`, question banks / `mod_qbank`) can't have a QR code. The action isn't shown for
  them.
- **Moodle app:** the code is an ordinary web link. Phones open it in the browser. Whether the
  Moodle app takes over depends on the phone and on your site's app settings (app links / universal
  links). The plugin doesn't create `moodlemobile://` links.
- **PHP 8.4 with JIT:** on PHP 8.4 with `opcache.jit` enabled, Moodle core's QR encoder (TCPDF) was
  seen to fail with "Trying to access array offset on null". The plugin switches JIT off while it
  encodes and reports a clean error if the encoder still misbehaves. Other QR codes generated by
  Moodle core on such a server may be affected independently of this plugin.
- **PNG needs GD or Imagick.** Without either, only SVG is offered and administrators see a notice.
- **SVG logos in PNG files:** SVG logos are never passed to GD or Imagick (GD can't read them, and
  ImageMagick's SVG reader can load external files). If your logo is an SVG, PNG codes are made
  without it (the centre stays blank, which still scans). SVG codes include it. Upload a PNG compact
  logo if you want the logo in PNG files.
- **Very small codes** (under 5 pixels per square, e.g. 200 px images of long URLs) are always drawn
  in the square design, whatever the setting, so they stay readable.
- **Expiring QR codes can't be withdrawn one by one.** Nothing is stored per QR code, so a code
  with an expiry date works until that date, unless an administrator uses *Cancel all expiring QR
  codes* (which stops all of them on the site). Hiding or deleting the activity, or removing
  someone's access, still stops them using it, as with any Moodle link.
- Codes for **hidden or restricted** activities can be made by staff who can see them, with a
  warning. People who scan them still need access.
