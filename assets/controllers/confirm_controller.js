/*!
 * Post Office
 * Copyright (C) 2026 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/**
 * Asks for confirmation in a modal dialog (#confirm-modal in base.html.twig) before submitting a form.
 * The confirm button of the dialog repeats the label of the form's submit button.
 *
 * Usage: <form data-controller="confirm" data-confirm-message-value="Are you sure?" data-action="confirm#ask">
 */
export default class extends Controller {
    static values = {
        message: String
    };

    ask(event) {
        // Second pass: the user has confirmed in the dialog, let the submission through.
        if (this.confirmed) {
            this.confirmed = false;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const dialog = document.getElementById('confirm-modal');
        if (!dialog) {
            if (window.confirm(this.messageValue)) {
                this.submit(event.submitter);
            }
            return;
        }

        const modal = Modal.getOrCreateInstance(dialog);
        const accept = dialog.querySelector('[data-confirm-modal-accept]');
        dialog.querySelector('[data-confirm-modal-message]').textContent = this.messageValue;
        accept.innerHTML = event.submitter ? event.submitter.innerHTML : accept.dataset.label;
        accept.onclick = () => {
            accept.onclick = null;
            modal.hide();
            this.submit(event.submitter);
        };
        // Focus "Cancel" so that pressing Enter does not delete anything by accident.
        dialog.addEventListener('shown.bs.modal', () => {
            dialog.querySelector('[data-confirm-modal-cancel]').focus();
        }, { once: true });
        modal.show();
    }

    submit(submitter) {
        this.confirmed = true;
        this.element.requestSubmit(submitter ?? null);
    }
}

// Don't let Turbo cache a page snapshot with an open dialog.
document.addEventListener('turbo:before-cache', () => {
    const dialog = document.getElementById('confirm-modal');
    if (dialog) {
        Modal.getInstance(dialog)?.dispose();
        dialog.classList.remove('show');
        dialog.style.display = '';
        document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
});
