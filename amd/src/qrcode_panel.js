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
 * Behaviour of the QR code panel: downloads with feedback, image errors and copy link.
 *
 * @module     local_resourceqr/qrcode_panel
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import 'core/copy_to_clipboard';
import Pending from 'core/pending';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

let listening = false;

/**
 * File name from a Content-Disposition header.
 *
 * @param {string|null} header
 * @param {string} fallback
 * @returns {string}
 */
const getFilename = (header, fallback) => {
    if (!header) {
        return fallback;
    }
    const encoded = header.match(/filename\*=UTF-8''([^;]+)/i);
    if (encoded) {
        try {
            return decodeURIComponent(encoded[1]);
        } catch (e) {
            // Fall through to the plain file name.
        }
    }
    const plain = header.match(/filename="([^"]+)"/i);
    return plain ? plain[1] : fallback;
};

/**
 * Download a QR code with fetch() so that errors can be reported without leaving the page.
 *
 * @param {HTMLAnchorElement} link
 */
const download = async(link) => {
    const pending = new Pending('local_resourceqr/qrcode_panel:download');
    const format = link.dataset.format;
    try {
        const response = await fetch(link.href, {credentials: 'same-origin'});
        const type = response.headers.get('Content-Type') || '';
        if (!response.ok || !type.startsWith('image/')) {
            throw new Error('Unexpected response');
        }
        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = getFilename(response.headers.get('Content-Disposition'), 'qrcode.' + format);
        anchor.hidden = true;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
        addToast(await getString('downloadsuccess', 'local_resourceqr', format.toUpperCase()), {type: 'success'});
    } catch (error) {
        addToast(await getString('error:download', 'local_resourceqr'), {type: 'danger'});
    }
    pending.resolve();
};

/**
 * Show an error in the panel if the preview image fails to load.
 *
 * @param {HTMLImageElement} image
 */
const handleImageError = async(image) => {
    const message = document.createElement('div');
    message.className = 'alert alert-danger';
    message.setAttribute('role', 'alert');
    message.textContent = await getString('error:preview', 'local_resourceqr');
    image.replaceWith(message);
};

/**
 * Add behaviour to a panel.
 *
 * @param {HTMLElement} container Element containing the panel.
 */
export const enhance = (container) => {
    const image = container.querySelector('[data-region="local-resourceqr-image"]');
    if (image) {
        if (image.complete && image.naturalWidth === 0) {
            handleImageError(image);
        } else {
            image.addEventListener('error', () => handleImageError(image), {once: true});
        }
    }
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-region="local-resourceqr-panel"] [data-action="local-resourceqr-download"]');
        if (!link || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        if (typeof window.fetch !== 'function' || typeof URL.createObjectURL !== 'function') {
            // Old browser: let the normal link download the file.
            return;
        }
        e.preventDefault();
        download(link);
    });
};

/**
 * Initialise panels on the stand-alone page.
 */
export const init = () => {
    document.querySelectorAll('[data-region="local-resourceqr-panel"]').forEach((panel) => enhance(panel));
};
