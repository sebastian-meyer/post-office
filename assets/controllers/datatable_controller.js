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
import DataTable from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

/**
 * Turns a server-rendered <table> into a DataTable (sorting, searching and paging in the browser).
 *
 * Usage: {% import 'macros/datatable.html.twig' as datatable %}
 *        <div {{ datatable.attributes({order: [[0, 'asc']]}) }}><table class="table">...</table></div>
 *
 * The controller must be attached to a wrapper element, not to the table itself: DataTables moves the table into its
 * own container, which Stimulus would treat as removing and re-adding the element, i.e. disconnecting (destroying the
 * table) and connecting (initializing it again) in an endless loop.
 *
 * Column options can be set on the header cells, e.g. <th data-orderable="false" data-searchable="false">.
 *
 * Rows can be marked with checkboxes to submit them to bulk action forms (see templates/macros/datatable.html.twig):
 * - a checkbox per row: <input type="checkbox" value="ID" data-datatable-row data-action="datatable#update">
 * - a checkbox in the header marks or unmarks all rows matching the search: <input type="checkbox"
 *   data-datatable-target="all" data-action="datatable#toggleAll">
 * - forms inside the wrapper with data-action="datatable#submit" get the marked values as hidden "selection[]"
 *   fields when submitted (rows on other pages included, which DataTables removes from the document)
 * - elements with data-datatable-target="count" show the number of marked rows, buttons with
 *   data-datatable-target="action" are disabled while no row is marked, and forms with a
 *   data-datatable-confirm-template attribute get it as confirmation message, with %count% replaced.
 */
export default class extends Controller {
    static targets = ['all', 'count', 'action'];

    static values = {
        language: Object,
        options: Object
    };

    connect() {
        const table = this.element.querySelector('table');

        if (table === null || DataTable.isDataTable(table)) {
            return;
        }

        this.table = new DataTable(table, {
            autoWidth: false,
            pageLength: 50,
            lengthMenu: [10, 25, 50, 100, 250],
            language: this.languageValue,
            ...this.optionsValue
        });

        // The header checkbox reflects the rows matching the current search.
        this.table.on('draw', () => this.update());
        this.update();

        // Restore the plain table before Turbo caches the page, so it isn't initialized twice when restored.
        this.beforeCache = () => this.destroy();
        document.addEventListener('turbo:before-cache', this.beforeCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.destroy();
    }

    destroy() {
        this.table?.destroy();
        this.table = null;
    }

    /**
     * Marks or unmarks all rows matching the current search (on all pages).
     */
    toggleAll(event) {
        this.checkboxes({ search: 'applied' }).forEach((checkbox) => {
            checkbox.checked = event.target.checked;
        });
        this.update();
    }

    /**
     * Updates the header checkbox, the counters, the action buttons and the confirmation messages.
     */
    update() {
        const count = this.marked().length;
        const visible = this.checkboxes({ search: 'applied' });
        const visibleMarked = visible.filter((checkbox) => checkbox.checked).length;

        this.allTargets.forEach((checkbox) => {
            checkbox.checked = visible.length > 0 && visibleMarked === visible.length;
            checkbox.indeterminate = visibleMarked > 0 && visibleMarked < visible.length;
        });
        this.countTargets.forEach((element) => {
            element.textContent = count;
        });
        this.actionTargets.forEach((button) => {
            button.disabled = count === 0;
        });
        this.element.querySelectorAll('form[data-datatable-confirm-template]').forEach((form) => {
            form.dataset.confirmMessageValue = form.dataset.datatableConfirmTemplate.replace('%count%', count);
        });
    }

    /**
     * Adds the marked values to the submitted form.
     */
    submit(event) {
        const form = event.target;
        const values = this.marked().map((checkbox) => checkbox.value);

        if (values.length === 0) {
            event.preventDefault();
            return;
        }

        form.querySelectorAll('input[data-datatable-selection]').forEach((input) => input.remove());
        values.forEach((value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selection[]';
            input.value = value;
            input.dataset.datatableSelection = '';
            form.append(input);
        });
    }

    /**
     * Returns the row checkboxes, including those of rows on other pages.
     */
    checkboxes(selector = {}) {
        if (!this.table) {
            return [...this.element.querySelectorAll('input[data-datatable-row]')];
        }

        return this.table.rows(selector).nodes().toArray()
            .map((row) => row.querySelector('input[data-datatable-row]'))
            .filter((checkbox) => checkbox !== null && !checkbox.disabled);
    }

    /**
     * Returns the marked row checkboxes.
     */
    marked() {
        return this.checkboxes().filter((checkbox) => checkbox.checked);
    }
}
