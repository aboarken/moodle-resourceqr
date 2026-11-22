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
 * Behaviour of the QR code panel: validity (lifetime or expiry date), downloads with
 * feedback, image errors and copy link.
 *
 * @module     local_resourceqr/qrcode_panel
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import 'core/copy_to_clipboard';
import Ajax from 'core/ajax';
import Pending from 'core/pending';
import Templates from 'core/templates';
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
 * Reload a panel for the validity chosen in its form.
 *
 * @param {HTMLFormElement} form The panel's validity form.
 * @param {string} focuscontrol Which control to focus afterwards (lifetime, until, date or apply).
 */
const applyValidity = async(form, focuscontrol) => {
    const panel = form.closest('[data-region="local-resourceqr-panel"]');
    const until = form.querySelector('[data-validity-control="until"]').checked;
    const date = form.querySelector('[data-validity-control="date"]').value;
    if (!panel || (until && !date)) {
        return;
    }
    const pending = new Pending('local_resourceqr/qrcode_panel:validity');
    try {
        const data = await Ajax.call([{
            methodname: 'local_resourceqr_get_qrcode_data',
            args: {cmid: parseInt(panel.dataset.cmid, 10), expirydate: until ? date : ''},
        }])[0];
        const {html, js} = await Templates.renderForPromise('local_resourceqr/qrcode_panel', data);
        const newpanel = Templates.replaceNode(panel, html, js)
            .find((node) => node.nodeType === Node.ELEMENT_NODE && node.matches('[data-region="local-resourceqr-panel"]'));
        if (newpanel) {
            enhance(newpanel);
            newpanel.querySelector(`[data-validity-control="${focuscontrol}"]`)?.focus();
        }
    } catch (error) {
        const message = error?.message || await getString('error:expirydate', 'local_resourceqr');
        addToast(message, {type: 'danger'});
    }
    pending.resolve();
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

    // Validity: apply as soon as it changes; the Apply button is for use without JavaScript.
    const validityForm = (target) => target.closest('[data-region="local-resourceqr-validity"]');
    document.addEventListener('change', (e) => {
        const form = validityForm(e.target);
        if (!form) {
            return;
        }
        const control = e.target.dataset.validityControl;
        if (control === 'date') {
            form.querySelector('[data-validity-control="until"]').checked = true;
        }
        applyValidity(form, control);
    });
    document.addEventListener('submit', (e) => {
        const form = validityForm(e.target);
        if (form) {
            e.preventDefault();
            applyValidity(form, 'apply');
        }
    });

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
