# Changes

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
