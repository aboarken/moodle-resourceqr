# Audit report: local_resourceqr 0.2.2

Full code, design and security audit of the Resource QR Code plugin, done on version 0.2.1 (commit
`d78ce74`, the merge of pull request #9). The fixes are in 0.2.2. This report covers what was
reviewed, what was found, what was changed and what was tested, including what could not be.

## 1. Executive summary

The plugin is small and well structured. It has 14 PHP classes, 6 entry scripts, 3 AMD modules and
3 templates. Every entry point re-checks access for the activity in the request, it uses only
Moodle's own APIs and core's bundled QR encoder (TCPDF), and it has no tables and no third-party
dependencies. No critical, high or medium security vulnerability was found. The review looked for
injection, XSS, CSRF, IDOR, open redirects, SSRF, path traversal, header injection and information
disclosure, and none of these are exploitable in the current code.

The audit found and fixed:

| # | Severity | Finding |
|---|---|---|
| BUG-1 | **Medium** (functional) | PNG files from the Imagick back end (used when GD is missing) did not scan in the square design, or at small sizes (for example every 200 px code). |
| SEC-1 | Low | An anonymous request to `go.php` could create the site's signing key (a database write by an unauthenticated user), and two first requests could race and invalidate a just-issued QR code. |
| SEC-2 | Low | The Imagick back end passed SVG site logos to ImageMagick's SVG reader, which can load external resources. |
| BUG-2 | Low | The log event's description labelled the expiry time "UTC" but used the server's time zone. |
| PERF-1 | Low | The Imagick back end painted each square in a separate pass: about 1 s per image instead of 0.03 to 0.3 s. |
| SEC-3 to SEC-7 | Informational | Documented, or hardened where cheap: what an expiry date does and doesn't protect, where the signing key lives, link strength, key generation, `rel="noopener"`. |

None of the fixes change how the plugin is used. QR codes already given out, lifetime or dated,
keep working.

## 2. Repository and architecture

**Purpose:** let staff with `local/resourceqr:generate` make a QR code for any activity or resource
with its own page (from the activity's *More* menu or the course's *QR codes* page), preview,
download (PNG/SVG) and print it, with a lifetime link or one that stops working after a date.

**Technologies:** PHP 8.1+ (Moodle 4.5 LTS to 5.3 LTS), Mustache templates, ES6 AMD modules built
with Moodle's Grunt, TCPDF's 2D barcode encoder from Moodle core, GD or Imagick for PNG.
PostgreSQL/MySQL/MariaDB are used only through Moodle's DML. CI uses GitHub Actions with
moodle-plugin-ci.

**Users and roles:** staff with the capability (editing teachers and managers by default) generate
codes. Site administrators configure the plugin and can cancel all expiring codes. Anyone,
including people who aren't logged in, can scan a code: a lifetime code is the activity's own
address, and an expiring code goes through the public `go.php`.

**Layers:**

| Layer | Files | Assessment |
|---|---|---|
| Entry points | `view.php`, `qr.php`, `print.php`, `course.php`, `go.php`, `resetlinks.php`, the web service `local_resourceqr_get_qrcode_data` | Thin: parameters, `require_login`, one access call, then rendering. Good. |
| Access rules | `classes/local/access.php` | Single source of truth (`can_generate()` / `require_generate()`), used by navigation, pages, images and the web service. Good separation. |
| Expiring links | `classes/local/expiry.php` | Signing, checking, dates, key management. Stateless: nothing stored per code. |
| Rendering | `classes/local/qr_generator.php`, `image.php`, `filename.php` | The generator turns the matrix into a list of shapes, painted by SVG, GD and Imagick back ends, so all formats match. A clear design. |
| Output | `classes/output/*`, `templates/*`, `amd/src/*` | Renderable/templatable with example contexts; JS only enhances, and the no-JS page works. |
| Navigation | `lib.php` | Documented legacy callbacks (still the supported extension points in 5.3, verified in core source). |
| Admin | `settings.php`, `classes/admin/*` | Settings validate their input (size range, contrast, formats, module list). |
| Data | none (`config_plugins` only) | No tables. The signing key and settings are in plugin config; generation is logged with a standard event. |

No unnecessary abstraction or duplicated logic of note. The one small duplicate (formatting an
activity name twice per course-page row) was removed.

## 3. Documentation and standards checked

Checked against the Moodle 4.5.16+ (`MOODLE_405_STABLE`) and 5.3.1+ (`MOODLE_503_STABLE`) source,
not only the documentation, because a call that works on 4.5 can be deprecated on 5.3:

- **Plugin structure and version file** (moodledev.io, *Plugin files* / *version.php*): `component`,
  `version`, `requires` 2024100700 (4.5.0), `supported` [405, 503], `maturity`. Correct.
- **Navigation API:** `local_<name>_extend_settings_navigation()` and
  `local_<name>_extend_navigation_course()` are still called by core in 5.3
  (`public/lib/classes/navigation/settings_navigation.php`, the `extend_navigation_course` and
  `extend_settings_navigation` plugin-function loops). They have not moved to hooks: `lib/db/hooks.php`
  has no replacement for them. The course page's activity menu still has no extension point for
  local plugins (documented in the README).
- **Icons:** `<component>_get_fontawesome_icon_map()` is read by
  `core\output\icon_system_fontawesome` in both versions. Correct.
- **Access API:** capability in `db/access.php` (CONTEXT_MODULE, `read`, no risks, archetypes),
  `require_login($course, false, $cm)`, `require_capability`, `has_capability`. Correct.
- **External functions** (`core_external\external_api`): parameters, `validate_parameters`,
  `validate_context` before any check, typed return structure, `ajax => true`,
  `capabilities` declared. Correct.
- **Events:** `crud`, `edulevel`, `objecttable`, `validate_data`, object id mapping. Correct
  (BUG-2 fixed).
- **Privacy API:** `null_provider`. Correct: the plugin stores no personal data; the event goes to
  Moodle's standard logstore, which handles its own privacy.
- **Upgrade API:** `db/install.php` and `db/upgrade.php` with `upgrade_plugin_savepoint`. Added in
  0.2.2 and tested on a real upgrade.
- **Admin settings and external pages:** `admin_setting_*`, `admin_externalpage` with
  `moodle/site:config`, `admin_externalpage_setup()`. Correct.
- **Output:** templates with example context (linted by `moodle-plugin-ci mustache`),
  `single_button::BUTTON_DANGER` (present in 4.5 and 5.3), `confirm()`, `notification`.
- **Security guidelines** (moodledev.io, *Security*): `required_param`/`optional_param` with
  types, `sesskey` for state changes, `format_string` for output, no `$_GET`/`$_POST`, no raw SQL.
  Met.
- **Coding style:** moodle-cs `moodle-extra` reports no errors or warnings.
- **OWASP Top 10 (2021)** and **OWASP ASVS 4.0** (V1 architecture, V3 session, V4 access control,
  V5 validation and encoding, V6 cryptography, V7 errors and logging, V8 data protection, V12 files,
  V14 configuration) were used as the checklist for section 5.
- **ISO/IEC 18004** (QR code: 4-module quiet zone, version sizes 21 + 4k) and **WCAG 2.x** contrast
  formula (colour setting). Correct.

Nothing deprecated or removed in 5.3 is used. The only deprecated-looking hit,
`core_component::get_plugin_list()`, is the supported static method, not the deprecated global
function.

## 4. Design and implementation findings

### BUG-1 (Medium, functional): Imagick PNGs that don't scan

- **Where:** `classes/local/qr_generator.php`, `png_imagick()`.
- **What:** shapes were drawn with `setStrokeOpacity(0)`. In ImageMagick 6 that still draws a
  stroke, and it clips about one pixel from the top of every filled shape. ImageMagick also puts
  pixel centres on whole coordinates, while the shapes use pixel edges. The result was a white line
  between every row of modules. In the square design, and whenever modules are under 5 px (when
  the square design is always used, for example 200 px codes), the codes did not decode.
- **Impact:** only on servers without GD (Imagick is the fallback). PNG downloads there could
  produce QR codes that phones can't read. It went unnoticed because no test exercised Imagick: the
  test machines had only GD.
- **Fix:** no stroke (`setStrokeColor('none')`), a −0.5 px translation so shapes land on whole
  pixels, and no anti-aliasing for the square design, so it is as sharp as with GD. New tests
  render every design × size × logo with Imagick and decode the result with `zbarimg`. CI now
  installs Imagick so these tests run there too.

### BUG-2 (Low): event description time zone

- **Where:** `classes/event/qrcode_generated.php`.
- **What:** `date()` (server time zone) followed by the text "UTC".
- **Fix:** `gmdate()`. The test sets a non-UTC server time zone and checks the exact text.

## 5. Security assessment

### SEC-1 (Low): unauthenticated request could create the signing key

- **Where:** `classes/local/expiry.php` (`secret()`, `verify()`), reached from the public `go.php`.
- **What:** the key was created on first use, including first use by `verify()`. An anonymous
  visitor to `go.php` on a site that had never signed a link caused a `set_config()` write. Two
  simultaneous first uses could each create a key; the last write wins, and a QR code signed with
  the other key would be invalid from the start.
- **Exploitation:** limited to one unwanted configuration write. It doesn't give an attacker a key
  or a valid link. The race needs two first-ever requests at the same moment.
- **Fix:** the key is created on install (`db/install.php`) and on upgrade for sites without one
  (`db/upgrade.php`). An existing key is kept, so QR codes already given out stay valid. `verify()`
  never creates a key, and a missing key means "not valid". Signing still creates one if it was
  deleted by hand. Tested in PHPUnit, by a real 0.2.1 → 0.2.2 upgrade (with and without a key), and
  over HTTP (an anonymous request with no key gets "not valid" and nothing is written).

### SEC-2 (Low): SVG logos passed to ImageMagick

- **Where:** `qr_generator::png_imagick()`.
- **What:** on servers without GD, the site logo (uploaded by an administrator under *Appearance >
  Logos*) was read with `Imagick::readImageBlob()`, including SVG logos. ImageMagick's SVG readers
  can follow external references; this is the class of issue behind ImageTragick (CVE-2016-3714
  and related).
- **Exploitation:** someone with administrator rights, or a stolen administrator session, uploads an
  SVG logo that references an internal URL or local file; a teacher's PNG download then makes the
  server fetch it. Administrator rights are needed, and most hosts' ImageMagick policies already
  restrict this, hence Low.
- **Fix:** SVG logos are never given to ImageMagick, as was already the case with GD. PNG codes are
  made without an SVG logo (the centre stays blank, which still scans); SVG codes still embed it,
  where browsers draw it without scripts or loading. Tested with an SVG logo that references an
  external URL.

### SEC-3 (Informational): an expiry date limits the QR code, not access

Someone who scanned an expiring code before its date ends up on the activity's normal address and
can bookmark or share it. That is by design: access to the activity is Moodle's job (enrolment,
visibility, restrictions, permissions). It is now stated in the README (user guide and security
model) and in `SECURITY.md`, so nobody relies on it as access control.

### SEC-4 (Informational): signing key storage

The key is in `config_plugins` (`local_resourceqr/secret`). Anyone who can read the database or its
backups can make valid expiring links, and a copy of the site (for example a restored test site)
shares the key. Documented, with the existing *Cancel all expiring QR codes* as the remedy on each
site.

### SEC-5 (Informational): link strength and rate limiting

Signatures are HMAC-SHA256 truncated to 80 bits and compared with `hash_equals()`. `go.php` has no
rate limit (Moodle offers none for public scripts), but guessing a signature online needs about
2^79 requests, and an invalid request costs one HMAC and no database query. Accepted as is.

### SEC-6 (Informational): key generation

New keys were made with `random_string(64)`: about 380 bits, with a small modulo bias. They are now
`bin2hex(random_bytes(32))`: 256 bits from the operating system's CSPRNG, without bias. Existing
keys are unaffected.

### SEC-7 (Informational): `target="_blank"` without `rel="noopener"`

The *Print* link opens a same-origin page, so reverse tabnabbing does not apply. `rel="noopener"`
was added anyway.

### Checked and not vulnerable

| Area | How it was checked | Result |
|---|---|---|
| Authentication and session | Every page except `go.php` calls `require_login()` (the web service uses `validate_context()`); `qr.php` closes the session before sending the image | OK |
| Authorization and IDOR | Every `cmid`/`id` is resolved with `get_course_and_cm_from_cmid()`/`get_fast_modinfo()` for the current user and then goes through `access::require_generate()` (enabled, module type, capability in the module, `uservisible` or `viewhiddenactivities`); the course page re-checks each listed activity | OK, covered by PHPUnit (users with and without the capability, not enrolled, hidden) and Behat |
| Privilege escalation | Cancelling keys needs `moodle/site:config` (`admin_externalpage_setup`) and a POST with `sesskey` | OK |
| SQL injection | No raw SQL; DML calls with constant SQL or bound parameters | OK |
| XSS | Templates escape by default; the four unescaped `{{{name}}}` outputs are activity names from `cm_info::get_formatted_name()` (`format_string()`) and section names from the course format's `get_section_name()`, which core formats pass through `format_string()` and core itself prints the same way; the SVG title is `htmlspecialchars(ENT_XML1)`; JS uses `textContent`, never `innerHTML`; `go.php` parameters are `PARAM_INT`/`PARAM_ALPHANUM` and were checked over HTTP with `<script>` values (not reflected) | OK |
| CSRF | Only `resetlinks.php` changes state (POST + `sesskey`); everything else is read-only GET (it only logs) | OK |
| Open redirect | `go.php` only redirects to `access::get_target_url()` of an existing activity on this site, after a valid signature | OK |
| SSRF | No outbound requests (SEC-2 fixed the only indirect path) | OK |
| Path traversal and file handling | No file paths from input; download file names are cleaned (`PARAM_FILE`, then letters, digits, `.-_`); no uploads | OK |
| Header injection | `Content-Disposition` uses a cleaned ASCII name and an RFC 5987 `filename*` | OK |
| Command injection and deserialization | None (`exec()` only in tests, with `escapeshellarg()`); no `unserialize()` | OK |
| Information disclosure | The image endpoint defines `NO_DEBUG_DISPLAY` and discards stray output; `go.php` reveals nothing without a valid signature; errors use language strings | OK |
| SVG content | Own shapes only; sent with `Content-Security-Policy: default-src 'none'; sandbox` and `nosniff` | OK |
| Sensitive data in QR codes | Session/user/token parameters are stripped from the encoded URL | OK |
| Dependencies | No Composer/npm runtime dependencies; TCPDF comes from Moodle core; GitHub Actions pinned to commits, updated by Dependabot | OK |
| CI and supply chain | Read-only token except the release job; no token kept in checkouts; `pull_request` (not `pull_request_target`); release notes passed via environment, not interpolated into scripts | OK |

## 6. Performance and code quality

- **PERF-1 (fixed):** Imagick painted each shape with its own `ImagickDraw` and `drawImage()`, so
  one full pass over the image per module. It is now a single pass. Measured on this machine
  (ImageMagick 6.9.12):

  | Design, size | Before | After |
  |---|---|---|
  | square 600 px | 1.03 s | 0.04 s |
  | rounded 600 px | 1.00 s | 0.04 s |
  | dots 600 px | 0.28 s | 0.03 s |
  | rounded 2000 px | 1.31 s | 0.31 s |

- **Database:** no N+1 pattern. The course page uses cached `get_fast_modinfo()`; access checks use
  cached contexts and capabilities. Settings are read with `get_config()` (cached).
- **Images:** generated on request and not cached. GD takes 15 to 200 ms, and only staff request
  them. Caching (MUC) would add invalidation complexity (settings, logo, activity URL) for little
  gain, so it was not added.
- **Logging:** every preview, download and print is logged. That is intended (audit trail); there
  is no logging per scan.
- **Code quality:** consistent naming and docblocks, no dead code (every language string and method
  is used, some through computed names), no warnings from moodle-cs. One duplicated
  `get_formatted_name()` call removed.

## 7. Files changed

| File | Change |
|---|---|
| `classes/local/expiry.php` | Key created on install/upgrade, never by checking; 256-bit keys; separate HMAC helper; length check before comparing |
| `classes/local/qr_generator.php` | Imagick: no stroke, half-pixel alignment, square design without anti-aliasing, single drawing pass, no SVG logos; shared `module_style()` also used for the SVG `shape-rendering` hint |
| `classes/event/qrcode_generated.php` | UTC time really in UTC |
| `classes/output/course_list.php` | Activity name formatted once per row |
| `templates/qrcode_panel.mustache` | `rel="noopener"` on the Print link |
| `db/install.php` (new) | Creates the signing key |
| `db/upgrade.php` (new) | Creates the signing key on sites without one (2026112500) |
| `version.php` | 2026112500, release 0.2.2 |
| `tests/qr_generator_test.php` | Imagick back end for every design/size/logo; SVG logo never given to ImageMagick; SVG output of every design decoded |
| `tests/expiry_test.php` | Signing key lifecycle; exact UTC text in the log description under a non-UTC time zone |
| `tests/behat/behat_local_resourceqr.php` (new) | Step that opens a valid, expired or forged expiring link |
| `tests/behat/resourceqr.feature` | Scenarios for scanning expiring links, logged in and logged out |
| `.github/workflows/ci.yml` | Installs the Imagick extension so its tests run |
| `README.md` | Security model, expiry limits, entry points, files, tests, what was run |
| `SECURITY.md` | Expiry is not access control |
| `CHANGES.md` | 0.2.2 |
| `docs/AUDIT.md` (new) | This report |
| `dist/local_resourceqr.zip` | Rebuilt |

No files were removed.

## 8. Tests performed

| Test | Environment | Result |
|---|---|---|
| Full PHPUnit suite | Moodle 4.5.16+, PHP 8.3.6, PostgreSQL 16, GD + Imagick 6.9.12, zbarimg | **155 tests, 732 assertions, all pass** |
| New Imagick tests before the fix | same | 10 of 18 failed (all square and 200 px cases), which confirmed BUG-1 on the original code |
| Upgrade 0.2.1 → 0.2.2 | real 4.5 site, `admin/cli/upgrade.php` | Without a key: one created (64 hex characters). With a key: kept unchanged |
| `go.php` over HTTP | PHP built-in server | Valid → 303 to `/mod/page/view.php?id=…`; expired → "QR code expired"; forged → "QR code not valid"; junk/`<script>` parameters → "not valid", nothing reflected; no key in the database → "not valid" and no key created |
| Visual check of Imagick output | square, rounded, dots at 600 px | Match the GD designs; all decode |
| Imagick timing | before/after, 600 and 2000 px | See section 6 |
| moodle-cs `moodle-extra` | every PHP file | 0 errors, 0 warnings |
| Language strings | every `get_string`/`{{#str}}`/exception key | All defined |
| CI (moodle-plugin-ci), [run 45184107617](https://github.com/aboarken/moodle-resourceqr/actions/runs/45184107617) | Moodle 4.5, 5.0, 5.1, 5.2, 5.3 with PHP 8.3; 5.3 with PHP 8.4 | **All six jobs pass**: lint, phpcs and phpdoc (0 warnings), validate, savepoints, mustache, grunt, PHPUnit (155 tests, 732 assertions, none skipped, Imagick included), Behat (17 scenarios; on 5.3 the Classic-theme one is skipped because 5.3 has no Classic) |

**Not run locally:** PHPUnit on Moodle 5.x (5.3 needs PostgreSQL 17; only 16 was available) and
Behat (no Selenium/ChromeDriver). Both ran in CI on every supported version and passed, including
the new Behat step and scenarios.

## 9. Remaining risks and limitations

- An expiry date is not access control (SEC-3), and expiring codes can only be cancelled all
  together, not one by one: nothing is stored per code. Per-code revocation would need a table
  and is a feature decision.
- Anyone who can read the database can make expiring links (SEC-4).
- `go.php` has no rate limit (SEC-5). Brute force is infeasible, but a flood of requests costs CPU
  like any public page; that belongs to the web server or WAF.
- The repository's GitHub settings (branch ruleset for `main`, private vulnerability reporting,
  secret scanning, push protection) can only be checked and set by the owner in GitHub.
- `dist/local_resourceqr.zip` is committed for convenience and duplicates the release asset; it
  must be rebuilt with every change.

## 10. Recommendations

1. Turn on the GitHub settings above if not done yet.
2. If staff need to withdraw a single code, add per-code records (a small table with an id in the
   link) instead of reusing the site key. That is a deliberate design change, not a fix.
3. Consider dropping `dist/` from the repository now that every release publishes the ZIP.
4. Keep the CI matrix in step with Moodle's supported branches (add 5.4 when it is released).
5. Re-run this audit when adding features that store data or add entry points.

## 11. Second pass (0.2.3)

A second review two days after the first, with a Moodle reviewer's checklist: entry points,
capabilities and contexts, web service types, sessions, scale, accessibility, right-to-left layout
and the upgrade path. It was done on 0.2.2 (commit `3ebfa5b`). It found **no new vulnerability and
no functional bug**. The changes are small hardening steps and tests that lock in what was checked
by hand.

### What was checked, and how

| Area | How | Result |
|---|---|---|
| Authorization on every entry point | Each page and both image formats requested directly as a student, a non-editing teacher, a non-enrolled user, an editing teacher and logged out, on a real 4.5 site | Only the editing teacher gets pages and images; the others get "no permission", the enrolment page or the login page. `resetlinks.php` refuses everyone but administrators |
| CSRF on the only state-changing page | `resetlinks.php` as administrator: GET with `confirm=1` and no `sesskey`, POST with a wrong `sesskey`, POST with the right one | Error; confirmation page shown again and key unchanged; key replaced |
| Scale | Course page for a course with 300 activities, database reads counted | 4 reads, 0.08 s: module contexts are preloaded by `get_fast_modinfo()`, so there is no query per activity |
| Response integrity | Image endpoint with `zlib.output_compression=On` and `Accept-Encoding: gzip` | Not compressed, `Content-Length` matches the bytes sent |
| Accessibility | axe (the copy bundled with Moodle, same tags as `behat_accessibility`: WCAG 2.0, 2.1, 2.2 A and AA) on view page, dialogue, course list, print page and the "expired" page, at 1280 px and 390 px | 0 violations |
| Right-to-left | Pages rendered with a test language pack (`thisdirection = rtl`) | Layout mirrors; the encoded link stays `dir="ltr"` |
| Moodle APIs | Callbacks, `db/*.php` files, privacy provider, event, upgrade savepoints, external function definition re-read against 4.5 and 5.3 | No deprecated or misused API. No tasks, observers, caches, messages, hooks or tables exist, and none are needed |

### Changes

| # | Severity | Change |
|---|---|---|
| H-1 | Low (performance) | `qr.php` calls `\core\session\manager::write_close()` before generating the image, as Moodle's own file-serving scripts do. The session lock was held while the image was drawn (up to 0.3 s), which holds up the same user's other requests. |
| H-2 | Low (hardening) | The expiry date is declared `PARAM_ALPHANUMEXT` in the web service (parameter and return values) and in `view.php`, instead of `PARAM_RAW`. It was already validated by a strict pattern before use, so this was not exploitable; the type now refuses anything else at the door, which is what Moodle's guidelines ask for. |
| H-3 | Low (behaviour) | `expiry::resolve()` treats an activity with `deletioninprogress` (queued for deletion with the recycle bin on) as gone, so the link shows "QR code not valid" instead of redirecting to an error page. |
| T-1 | Tests | Behat: every plugin page opened directly without the capability (student, non-editing teacher), by a non-enrolled user, and the cancel page by a teacher. New page resolvers in `behat_local_resourceqr` (`"page1" "local_resourceqr > QR code" page`) and a step that expects the refusal (`opening the local_resourceqr "QR code" page of "page1" should fail with "..."`), because Moodle's Behat fails any ordinary step that ends on an error page. |
| T-2 | Tests | Behat `@accessibility` scenario: view page, course list, dialogue, print page and "expired" page must meet accessibility standards. |
| T-3 | Tests | PHPUnit: deletion in progress; markup in the expiry date refused by parameter validation. |
| D-1 | Docs | README: what turning the plugin off does to codes already given out; what was run in this pass. |

### Tests run in this pass

- PHPUnit on Moodle 4.5.16+, PHP 8.3.6, PostgreSQL 16, GD and Imagick: 155 tests, 734 assertions,
  all pass.
- The manual checks in the table above, on a real 4.5 site with Chromium.
- phpcs `moodle-extra`: clean.
- Behat on Moodle 4.5 locally, the 19 scenarios that need no JavaScript (BrowserKit driver, no
  Selenium needed): all pass, 219 steps. The refusal step was also run against a page that is
  *not* refused and against a wrong message, and failed both times as it must.
- CI (moodle-plugin-ci on Moodle 4.5 to 5.3, PHPUnit and Behat) runs on the pull request. The
  JavaScript scenarios, including the accessibility one, run only there. The first CI run of this
  pass failed on all versions: the first version of the refusal scenarios used ordinary navigation
  steps, which Behat fails on an error page even when the error is the expected result. The pages
  themselves refused access correctly; the scenarios were rewritten with the step above.

### Not changed, and why

- **`readonlysession` for the web service:** it would save little (the dialogue makes one call) and
  read-only sessions are off by default.
- **Image caching:** see section 6; still not worth the invalidation logic.
- **`go.php` when the plugin is disabled:** it keeps redirecting, on purpose, so printed codes don't
  break. Now documented.

### Open point for the owner: licence file

The repository's `LICENSE` file is the text of the **GNU GPL version 2** (added with the initial
commit), while every source file's header says **GNU GPL v3 or later**, which is also Moodle's own
licence and what the Moodle plugins directory expects. The two should agree. Replacing `LICENSE`
with the GPL version 3 text (the same file as Moodle's `COPYING.txt`) would do it. Choosing a
licence is the copyright holder's decision, so the file was left as it is.
