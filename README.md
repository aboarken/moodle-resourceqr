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
7. [Known limitations](#known-limitations)

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
| `lib.php` | Settings navigation callback and Font Awesome icon map. |
| `view.php` | Stand-alone page (no-JavaScript fallback). |
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

There is no `db/install.xml` (no tables) and no `db/upgrade.php` (nothing to upgrade).

### Access rule (every entry point)

`view.php`, `print.php`, `qr.php` and the web service all do the following:

1. `require_login($course, false, $cm)` (in the web service: `validate_context()`, which does the same).
2. `access::require_generate($cm)`, which requires:
   - the plugin to be enabled, and the activity type to be enabled and have a view page,
   - `require_capability('local/resourceqr:generate', $modulecontext)`,
   - `$cm->uservisible`, or `moodle/course:viewhiddenactivities` in the activity.

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
    installed), every design, every error-correction level, and the logo;
  - settings validation, the event, the external function, navigation, file names and privacy.
- `tests/behat/resourceqr.feature`: the action for authorised and unauthorised users; the dialogue
  (name, QR, URL, copy, downloads, print); hidden warning; format restriction; disabled plugin; no-JavaScript page; Classic theme; mobile viewport.
- `.github/workflows/ci.yml`: moodle-plugin-ci on Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 (PHP 8.3, plus
  8.4 on 5.3) with PostgreSQL 17. It runs phplint, phpcs (moodle-cs), phpdoc, validate, savepoints,
  mustache, grunt, PHPUnit and Behat, and installs `zbar-tools` for the decoding tests.

### What was actually run during development

| Check | Result |
|---|---|
| PHPUnit, Moodle 4.5.16, PHP 8.3, PostgreSQL 16 | 101 tests pass |
| PHPUnit, Moodle 5.2.5, PHP 8.3, PostgreSQL 16 (PHPUnit 11) | 101 tests pass, no deprecations |
| phpcs `moodle-extra` (moodlehq/moodle-cs) | No errors or warnings |
| Grunt `amd` (ESLint + build), `stylelint`, `gherkinlint` with Moodle 4.5's tooling | Pass |
| Browser check on a real 4.5.16 site (Playwright/Chromium) | Boost, Classic and a 390 px phone screen: menu item, dialogue, preview, PNG/SVG downloads (files decode), copy link toast, Escape closes and returns focus to the More menu, hidden-activity warning, print page, a user without the capability sees no item and gets "no permission" on every page |
| Decoding of 144 generated images (3 designs × with/without logo × levels L/M/Q/H × 200/600/2000 px, PNG and Chromium-rendered SVG) with zbarimg | All decode to the right URL |

**Not run locally:**

- **Moodle 5.3 PHPUnit:** 5.3 requires PostgreSQL 17 (or MariaDB 11.4 / MySQL 8.4), which wasn't
  available in the build environment. The 5.3.1 source was checked for every API the plugin uses.
  The CI workflow runs the suite on 5.3.
- **Behat:** no Selenium/ChromeDriver was available. The CI workflow runs it. The same flows were
  checked by hand-written Playwright scripts on 4.5, as listed above.
- **Imagick back end:** the build environment only had GD. The Imagick code is used only when GD is
  missing.
- **moodle-plugin-ci's `phpdoc`, `validate`, `savepoints` and `mustache` commands** were not run
  locally. They run in CI.

### Still to check on your own site

1. Install on your Moodle 4.5 site and check the item appears under **More** for a user who has the capability.
2. Scan a printed code with the phones your staff and students use (including the Moodle app, see
   below).
3. If you turn on the site logo, scan a code that has it.

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
- **SVG logos in PNG files:** GD can't read SVG. If your logo is an SVG, PNG codes are made without
  it (the centre stays blank, which still scans). SVG codes include it. Upload a PNG compact logo if
  you want the logo in PNG files.
- **Very small codes** (under 5 pixels per square, e.g. 200 px images of long URLs) are always drawn
  in the square design, whatever the setting, so they stay readable.
- Codes for **hidden or restricted** activities can be made by staff who can see them, with a
  warning. People who scan them still need access.
