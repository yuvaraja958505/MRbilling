// Global Calculation Function for Line Items & Totals
function calculateTotals() {
    const itemsTable = document.getElementById('itemsTable');
    if (!itemsTable) return;
    
    const rows = itemsTable.querySelectorAll('tbody tr');
    let subtotal = 0;
    let totalTax = 0;

    rows.forEach(row => {
        const qtyEl = row.querySelector('.item-qty');
        const priceEl = row.querySelector('.item-price');
        const taxRateEl = row.querySelector('.item-tax-rate');
        const totalSpan = row.querySelector('.row-total');

        const qty = qtyEl ? (parseFloat(qtyEl.value) || 0) : 0;
        const price = priceEl ? (parseFloat(priceEl.value) || 0) : 0;
        const taxRate = taxRateEl ? (parseFloat(taxRateEl.value) || 0) : 0;

        const baseAmount = qty * price;
        const taxAmount = (baseAmount * taxRate) / 100;
        const lineTotal = baseAmount + taxAmount;

        if (totalSpan) {
            totalSpan.textContent = '₹' + lineTotal.toFixed(2);
        }

        subtotal += baseAmount;
        totalTax += taxAmount;
    });

    const grandTotal = subtotal + totalTax;

    const displaySubtotal = document.getElementById('displaySubtotal');
    const displayTax = document.getElementById('displayTax');
    const displayGrandTotal = document.getElementById('displayGrandTotal');

    if (displaySubtotal) displaySubtotal.textContent = '₹' + subtotal.toFixed(2);
    if (displayTax) displayTax.textContent = '₹' + totalTax.toFixed(2);
    if (displayGrandTotal) displayGrandTotal.textContent = '₹' + grandTotal.toFixed(2);

    const inputSubtotal = document.getElementById('inputSubtotal');
    const inputTaxAmount = document.getElementById('inputTaxAmount');
    const inputGrandTotal = document.getElementById('inputGrandTotal');

    if (inputSubtotal) inputSubtotal.value = subtotal.toFixed(2);
    if (inputTaxAmount) inputTaxAmount.value = totalTax.toFixed(2);
    if (inputGrandTotal) inputGrandTotal.value = grandTotal.toFixed(2);
}

// Expose calculateTotals globally
window.calculateTotals = calculateTotals;

document.addEventListener('DOMContentLoaded', function () {
    const itemsTable = document.getElementById('itemsTable');

    // Auto-select text on focus for quick entry
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.matches('.item-qty, .item-price, .item-tax-rate')) {
            e.target.select();
        }
    });

    // Sanitize input values and recalculate on input, change, keyup
    const handleInputCalc = function (e) {
        if (e.target && e.target.matches('.item-qty, .item-price, .item-tax-rate')) {
            if (e.target.classList.contains('item-qty')) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            } else {
                e.target.value = e.target.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            }
            calculateTotals();
        }
    };

    document.addEventListener('input', handleInputCalc);
    document.addEventListener('change', handleInputCalc);
    document.addEventListener('keyup', handleInputCalc);

    // Minimum Qty check on blur
    document.addEventListener('blur', function (e) {
        if (e.target && e.target.classList.contains('item-qty')) {
            if (!e.target.value || parseInt(e.target.value, 10) < 1) {
                e.target.value = '1';
                calculateTotals();
            }
        }
    }, true);

    // Dynamic Add Row Button
    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn && itemsTable) {
        addRowBtn.addEventListener('click', function () {
            const tbody = itemsTable.querySelector('tbody');
            const rowCount = tbody.querySelectorAll('tr').length;
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <input type="text" name="items[${rowCount}][description]" class="form-control" placeholder="Item description" required>
                </td>
                <td>
                    <input type="text" name="items[${rowCount}][hsn]" class="form-control" placeholder="HSN/SAC">
                </td>
                <td>
                    <input type="text" inputmode="numeric" name="items[${rowCount}][qty]" class="form-control item-qty" value="1" required>
                </td>
                <td>
                    <input type="text" inputmode="decimal" name="items[${rowCount}][price]" class="form-control item-price" placeholder="0.00" value="" required>
                </td>
                <td>
                    <input type="text" inputmode="decimal" name="items[${rowCount}][tax_rate]" class="form-control item-tax-rate" value="18" placeholder="18" required>
                </td>
                <td class="text-end fw-bold">
                    <span class="row-total">₹0.00</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-trash"></i></button>
                </td>
            `;
            tbody.appendChild(newRow);
            calculateTotals();
        });
    }

    // Dynamic Remove Row Event Delegation
    if (itemsTable) {
        itemsTable.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.remove-row-btn');
            if (removeBtn) {
                const tbody = itemsTable.querySelector('tbody');
                if (tbody.querySelectorAll('tr').length > 1) {
                    removeBtn.closest('tr').remove();
                    reindexRows();
                    calculateTotals();
                } else {
                    alert('At least one item line is required.');
                }
            }
        });
    }

    function reindexRows() {
        if (!itemsTable) return;
        const rows = itemsTable.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.querySelectorAll('input, select').forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/items\[\d+\]/, `items[${index}]`));
                }
            });
        });
    }

    // Initial calculation run
    calculateTotals();
});

// Function to print or save document
function printDocument() {
    window.print();
}

