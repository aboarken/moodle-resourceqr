# Changes

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
