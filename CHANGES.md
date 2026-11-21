# Changes

## 1.0.0 (2026-11-21)

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
- Capability `local/resourceqr:generate` (editing teachers and managers by default).
- Settings: enable, activity types, size, design, colour, error correction, logo, download formats.
- Event `\local_resourceqr\event\qrcode_generated` for the logs. Privacy null provider.
- Supports Moodle 4.5 LTS to 5.3 LTS.
