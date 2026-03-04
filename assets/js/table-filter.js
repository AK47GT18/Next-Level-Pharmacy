/**
 * Modular Table Filtering Utility
 *
 * This utility can be attached to any page with a filterable table.
 * It supports multiple text and select inputs for combined filtering.
 *
 * How to use:
 * 1. Add `data-table-filter-container` to a parent element containing your filters and table.
 * 2. Add `data-filter-input` to your text search <input> elements.
 * 3. Add `data-filter-select` to your <select> elements.
 * 4. Add `data-filter-table` to your <table> element.
 * 5. Add `data-filter-row` to each <tr> in the <tbody> you want to filter.
 * 6. For select filters, add `data-filter-key="columnName"` to the <select> and `data-column-name="value"` to the <tr>.
 */
export class TableFilter {
    constructor(container) {
        this.container = container;
        this.textInputs = Array.from(container.querySelectorAll('[data-filter-input]'));
        this.selectInputs = Array.from(container.querySelectorAll('[data-filter-select]'));
        this.table = container.querySelector('[data-filter-table]');
        this.rows = Array.from(this.table.querySelectorAll('tbody [data-filter-row]'));

        this.attachEventListeners();
    }

    attachEventListeners() {
        this.textInputs.forEach(input => {
            input.addEventListener('input', () => this.filter());
        });
        this.selectInputs.forEach(select => {
            select.addEventListener('change', () => this.filter());
        });
    }

    filter() {
        const textFilters = this.textInputs.map(input => input.value.toLowerCase());
        const selectFilters = this.selectInputs.map(select => ({
            key: select.dataset.filterKey,
            value: select.value.toLowerCase()
        }));

        this.rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();

            const textMatch = textFilters.every(filterText => filterText === '' || rowText.includes(filterText));

            const selectMatch = selectFilters.every(filter => {
                if (filter.value === '') return true;
                const rowDataValue = row.dataset[this.camelCase(filter.key)]?.toLowerCase();
                return rowDataValue === filter.value;
            });

            row.style.display = textMatch && selectMatch ? '' : 'none';
        });
    }

    camelCase(str) {
        return str.replace(/-([a-z])/g, g => g[1].toUpperCase());
    }
}