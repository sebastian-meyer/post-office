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
 */
export default class extends Controller {
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
}
