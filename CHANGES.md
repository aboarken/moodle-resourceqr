# Changes

## 0.2.3 (2026-11-26)

Hardening and tests from a second, Moodle-specific review (see docs/AUDIT.md, "Second pass"). No
change to how the plugin is used, and no database change.

- The image endpoint releases the session lock before it generates the image, so a user's other
  requests are not held up while a QR code is drawn.
- The web service and the stand-alone page accept only letters, digits, "-" and "_" in the expiry
  date (it was checked before, but only after being accepted as raw text).
- An expiring link to an activity that is waiting to be deleted now shows "QR code not valid"
  instead of sending people to an error page.
- Tests: opening each plugin page directly as a student, a non-editing teacher or a user who is not
  enrolled is refused; a teacher cannot cancel expiring QR codes; the plugin's pages pass Moodle's
  accessibility checks (axe, WCAG 2.2 AA).

## 0.2.2 (2026-11-25)

Fixes from a full code and security audit (see docs/AUDIT.md). No change to how the plugin is used.

- **Signing key:** created on install, and on upgrade for sites that don't have one yet, so
  checking an expiring link (which anyone can do) never writes to the database and two first
  requests can't create two different keys. New keys are 256 random bits. Existing keys are kept,
  so QR codes already given out keep working.
- **Imagick:** SVG site logos are no longer passed to ImageMagick (whose SVG reader can load
  external files), as was already the case with GD. PNG files from Imagick are drawn in one pass
  instead of one pass per square, which is much faster.
- **Logs:** the event description gave the expiry time in the server's time zone but labelled it
  UTC; it is now really UTC.
- The *Print* link opens with `rel="noopener"`.
- Tests: the Imagick back end and the SVG output of every design are now decoded in the tests
  (CI installs Imagick); the public expiring link is tested in a browser (valid, expired, forged,
  logged out); the signing key's lifecycle is tested.
- Documentation: security model, what an expiry date does and doesn't do, the audit report.

## 0.2.1 (2026-11-24)

- **Cancel all expiring QR codes:** a new button at the bottom of the plugin settings (site
  administrators, after a confirmation) stops every QR code with an expiry date from working
  straight away, for example if one was shared with the wrong people. People who scan one see
  "QR code not valid". Lifetime QR codes are not affected. The settings page shows when it was
  last done, and it is recorded in the configuration changes report.
- The "Release package" workflow no longer keeps its GitHub token in the checked-out files.

## 0.2.0 (2026-11-23)

- **Lifetime or expiry date:** under **Validity** in the QR code dialogue, choose *Lifetime (never
  expires)* or *Valid until* a date. A QR code with an expiry date holds a signed link
  (`go.php`) that takes people to the activity until the end of that day and then shows that the
  code has expired. Lifetime QR codes are unchanged. The downloads, print page and file names show
  the expiry date.

## 0.1.1 (2026-11-22)

- New **QR codes** page for each course, in the course's **More** menu (Course administration in
  Classic). It lists the course's activities and resources by section, each with a **Generate QR
  code** button, so QR codes are easier to find. It also covers files set to open or download
  straight away, whose own page teachers never see.
- The "Generate QR code" menu icon is now a Font Awesome icon, so it takes the theme's icon colour
  like Moodle's own menu items. Previously it was a fixed-colour image.

## 0.1.0 (2026-11-21)

First release.

- "Generate QR code" in the activity's settings navigation (the **More** menu in Boost, the
  *Administration* block in Classic). It opens a dialogue with the activity name, the QR code,
  the link, warnings for hidden, stealth or restricted activities, and buttons to download PNG or
  SVG, print and copy the link.
- No-JavaScript fallback page and a print page.
- QR codes are generated on the server with Moodle core's TCPDF encoder, with a proper quiet zone.
- Designs: rounded (default), dots, square. The colour can be configured, and colours too light to
  scan are refused.
- Optional site logo in the centre, with error-correction level H.
- Capability `local/resourceqr:generate` controls who can generate QR codes.
- Settings: enable, activity types, size, design, colour, error correction, logo, download formats.
- Event `\local_resourceqr\event\qrcode_generated` for the logs. Privacy null provider.
- Supports Moodle 4.5 LTS to 5.3 LTS.
