// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Print page behaviour.
 *
 * @module     local_resourceqr/print
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wait for the QR image, then print.
 *
 * @param {boolean} autoprint Open the print dialogue straight away.
 */
export const init = (autoprint) => {
    const root = document.querySelector('[data-region="local-resourceqr-print"]');
    if (!root) {
        return;
    }
    root.querySelector('[data-action="local-resourceqr-printnow"]')?.addEventListener('click', () => window.print());
    if (!autoprint) {
        return;
    }
    const image = root.querySelector('[data-region="local-resourceqr-image"]');
    if (!image || image.complete) {
        window.print();
    } else {
        image.addEventListener('load', () => window.print(), {once: true});
    }
};
