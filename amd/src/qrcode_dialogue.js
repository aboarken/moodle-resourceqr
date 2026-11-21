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
 * Opens the QR code dialogue from the "Generate QR code" navigation item.
 *
 * The navigation item is an ordinary link to the stand-alone page, so the action
 * still works without JavaScript. This module only listens for clicks on that
 * link (event delegation); it does not change the page structure.
 *
 * @module     local_resourceqr/qrcode_dialogue
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {enhance} from 'local_resourceqr/qrcode_panel';

const LINK_SELECTOR = 'a[href*="/local/resourceqr/view.php?cmid="]';

let initialised = false;

/**
 * Course module id from a navigation link.
 *
 * @param {HTMLAnchorElement} link
 * @returns {number|null}
 */
const getCmid = (link) => {
    if (link.dataset.localResourceqrCmid) {
        return parseInt(link.dataset.localResourceqrCmid, 10);
    }
    const cmid = parseInt(new URL(link.href, window.location.href).searchParams.get('cmid'), 10);
    return Number.isNaN(cmid) ? null : cmid;
};

/**
 * Element to focus when the dialogue closes: the menu toggle if the link was in a dropdown.
 *
 * @param {HTMLElement} link
 * @returns {HTMLElement}
 */
const getReturnElement = (link) => {
    const dropdown = link.closest('.dropdown, .dropup, .moremenu');
    const toggle = dropdown?.querySelector('[data-bs-toggle="dropdown"], [data-toggle="dropdown"]');
    return toggle || link;
};

/**
 * Show the dialogue for a course module.
 *
 * @param {number} cmid
 * @param {HTMLElement} returnElement
 * @returns {Promise<Modal>}
 */
export const show = async(cmid, returnElement) => {
    const pending = new Pending('local_resourceqr/qrcode_dialogue:show');
    try {
        const data = await Ajax.call([{
            methodname: 'local_resourceqr_get_qrcode_data',
            args: {cmid},
        }])[0];
        const modal = await Modal.create({
            title: getString('qrcodetitle', 'local_resourceqr', data.name),
            body: Templates.render('local_resourceqr/qrcode_panel', data),
            large: false,
            removeOnClose: true,
            returnElement,
        });
        modal.getRoot()[0].classList.add('local-resourceqr-modal');
        await modal.getBodyPromise();
        enhance(modal.getBody()[0]);
        modal.show();
        pending.resolve();
        return modal;
    } catch (error) {
        pending.resolve();
        Notification.exception(error);
        return null;
    }
};

/**
 * Listen for clicks on the navigation item.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('click', (e) => {
        const link = e.target.closest(LINK_SELECTOR);
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        const cmid = getCmid(link);
        if (!cmid) {
            return;
        }
        e.preventDefault();
        show(cmid, getReturnElement(link));
    });
};
