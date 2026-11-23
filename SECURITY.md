# Security policy

## Supported versions

Only the latest release of `local_resourceqr` receives security fixes. Install the package from the
[latest release](https://github.com/aboarken/moodle-resourceqr/releases/latest) through
**Site administration → Plugins → Install plugins**.

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Report it privately through GitHub: on this repository, open the **Security** tab and choose
**Report a vulnerability**. Include:

- the version of `local_resourceqr`, Moodle and PHP;
- what an attacker can do, and the steps to reproduce it;
- whether it needs a logged-in user, a teacher or an administrator.

Please do not include real student data, passwords or site secrets in the report.

You will get an answer within a few days. Once a fix is released, the report is published as a
security advisory with credit to the reporter, unless you prefer otherwise.

## Scope

- Making a QR code for an activity the user may not generate one for, or reading an activity's
  name or link without access to it, is in scope.
- Forging or extending an expiry link (`go.php`) without the site secret is in scope. The link
  only redirects; Moodle still checks access to the activity itself.
- Not a vulnerability: someone who scanned an expiring QR code before its date knows the
  activity's normal address afterwards. An expiry date limits the QR code, not access to the
  activity; Moodle's own access rules decide who can open it.
- Moodle itself and the bundled TCPDF library are out of scope; report those to their own
  projects.
