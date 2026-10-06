<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$id = intval($_GET['id'] ?? 0);
$error = '';
$msg = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'converted') {
    $msg = 'Document converted to Invoice successfully!';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_number = trim($_POST['invoice_number']);
    $client_id = intval($_POST['client_id']);
    $issue_date = $_POST['issue_date'];
    $due_date = $_POST['due_date'];
    $payment_status = $_POST['payment_status'];
    $notes = trim($_POST['notes']);

    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);

    // Auto update payment status based on paid amount
    if ($paid_amount >= $grand_total && $grand_total > 0) {
        $payment_status = 'Paid';
    } elseif ($paid_amount > 0 && $paid_amount < $grand_total) {
        $payment_status = 'Partial';
    }

    $items = $_POST['items'] ?? [];

    $enable_transport = isset($_POST['enable_transport']) ? 1 : 0;
    $vehicles = $_POST['vehicles'] ?? [];

    $firstVehicleNo = '';
    $firstDriverName = '';
    $firstDriverPhone = '';
    $validVehicles = [];

    foreach ($vehicles as $v) {
        $vNo = trim($v['number'] ?? '');
        $vDriver = trim($v['driver'] ?? '');
        $vPhone = trim($v['phone'] ?? '');
        if (!empty($vNo) || !empty($vDriver) || !empty($vPhone)) {
            $validVehicles[] = [
                'number' => $vNo,
                'driver' => $vDriver,
                'phone' => $vPhone
            ];
        }
    }

    if (!empty($validVehicles)) {
        $firstVehicleNo = $validVehicles[0]['number'];
        $firstDriverName = $validVehicles[0]['driver'];
        $firstDriverPhone = $validVehicles[0]['phone'];
        $enable_transport = 1;
    }

    if ($client_id <= 0 || empty($issue_date) || empty($items)) {
        $error = 'Please select a client, issue date, and add at least one line item.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE invoices SET invoice_number=?, client_id=?, issue_date=?, due_date=?, subtotal=?, tax_amount=?, grand_total=?, paid_amount=?, payment_status=?, notes=?, enable_transport=?, vehicle_number=?, driver_name=?, driver_phone=? WHERE id=?");
            $stmt->execute([$invoice_number, $client_id, $issue_date, $due_date, $subtotal, $tax_amount, $grand_total, $paid_amount, $payment_status, $notes, $enable_transport, $firstVehicleNo, $firstDriverName, $firstDriverPhone, $id]);

            $pdo->prepare("DELETE FROM document_items WHERE doc_type='invoice' AND doc_id=?")->execute([$id]);
            $docId = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, issue_date, due_date, subtotal, tax_amount, grand_total, paid_amount, payment_status, notes, enable_transport, vehicle_number, driver_name, driver_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invoice_number, $client_id, $issue_date, $due_date, $subtotal, $tax_amount, $grand_total, $paid_amount, $payment_status, $notes, $enable_transport, $firstVehicleNo, $firstDriverName, $firstDriverPhone]);
            $docId = $pdo->lastInsertId();
        }

        // Save multiple vehicles
        $pdo->prepare("DELETE FROM document_vehicles WHERE doc_type='invoice' AND doc_id=?")->execute([$docId]);
        if (!empty($validVehicles)) {
            $vInsStmt = $pdo->prepare("INSERT INTO document_vehicles (doc_type, doc_id, vehicle_number, driver_name, driver_phone) VALUES ('invoice', ?, ?, ?, ?)");
            foreach ($validVehicles as $vItem) {
                $vInsStmt->execute([$docId, $vItem['number'], $vItem['driver'], $vItem['phone']]);
            }
        }

        $itemStmt = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('invoice', ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $desc = trim($item['description']);
            if (empty($desc)) continue;
            $hsn = trim($item['hsn'] ?? '');
            $qty = max(1, intval($item['qty'] ?? 1));
            $price = floatval($item['price'] ?? 0);
            $taxRate = floatval($item['tax_rate'] ?? 18);

            $lineSub = $qty * $price;
            $lineTax = ($lineSub * $taxRate) / 100;
            $lineTotal = $lineSub + $lineTax;

            $itemStmt->execute([$docId, $desc, $hsn, $qty, $price, $taxRate, $lineTax, $lineTotal]);
        }

        header("Location: invoices.php?msg=saved");
        exit;
    }
}

// Fetch Invoice data if editing
$invoice = null;
$existingItems = [];
$existingVehicles = [];
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();

    if ($invoice) {
        $iStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type='invoice' AND doc_id=?");
        $iStmt->execute([$id]);
        $existingItems = $iStmt->fetchAll();

        $vStmt = $pdo->prepare("SELECT * FROM document_vehicles WHERE doc_type='invoice' AND doc_id=?");
        $vStmt->execute([$id]);
        $existingVehicles = $vStmt->fetchAll();

        if (empty($existingVehicles) && (!empty($invoice['vehicle_number']) || !empty($invoice['driver_name']) || !empty($invoice['driver_phone']))) {
            $existingVehicles = [[
                'vehicle_number' => $invoice['vehicle_number'],
                'driver_name' => $invoice['driver_name'],
                'driver_phone' => $invoice['driver_phone']
            ]];
        }
    }
}

$docNumber = $invoice ? $invoice['invoice_number'] : generateDocNumber('INV', 'invoices', 'invoice_number');
$clients = $pdo->query("SELECT id, name, company_name FROM clients ORDER BY name ASC")->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-2"></i><?php echo $id > 0 ? 'Edit Sales Invoice #' . htmlspecialchars($docNumber) : 'Create New Sales Invoice'; ?></h3>
        <p class="text-muted mb-0">Generate final tax invoices and record payments.</p>
    </div>
    <div>
        <a href="invoices.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="invoice-create.php<?php echo $id > 0 ? '?id='.$id : ''; ?>" method="POST">
    <div class="row g-4">
        <!-- Main Form Left -->
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Client <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-select" required>
                                <option value="">-- Choose Client --</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo ($invoice && $invoice['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?> <?php echo $client['company_name'] ? '('.htmlspecialchars($client['company_name']).')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 text-end d-flex align-items-end justify-content-end">
                            <a href="clients.php?action=add" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> Add New Client</a>
                        </div>
                    </div>

                    <!-- Line Items Table -->
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Itemized Line Items</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Description</th>
                                    <th style="width: 15%;">HSN/SAC</th>
                                    <th style="width: 12%;">Qty</th>
                                    <th style="width: 15%;">Unit Price (₹)</th>
                                    <th style="width: 13%;">Tax Rate</th>
                                    <th style="width: 15%; text-align: right;">Total (₹)</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($existingItems)): ?>
                                    <?php foreach ($existingItems as $idx => $item): ?>
                                        <tr>
                                            <td><input type="text" name="items[<?php echo $idx; ?>][description]" class="form-control" value="<?php echo htmlspecialchars($item['item_description']); ?>" required></td>
                                            <td><input type="text" name="items[<?php echo $idx; ?>][hsn]" class="form-control" value="<?php echo htmlspecialchars($item['hsn_code']); ?>"></td>
                                            <td><input type="text" inputmode="numeric" name="items[<?php echo $idx; ?>][qty]" class="form-control item-qty" value="<?php echo (int)$item['qty']; ?>" required></td>
                                            <td><input type="text" inputmode="decimal" name="items[<?php echo $idx; ?>][price]" class="form-control item-price" value="<?php echo $item['unit_price']; ?>" required></td>
                                            <td><input type="text" inputmode="decimal" name="items[<?php echo $idx; ?>][tax_rate]" class="form-control item-tax-rate" value="<?php echo htmlspecialchars($item['tax_rate']); ?>" placeholder="18" required></td>
                                            <td class="text-end fw-bold"><span class="row-total">₹<?php echo number_format($item['total_amount'], 2); ?></span></td>
                                            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-trash"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><input type="text" name="items[0][description]" class="form-control" placeholder="Item description / Service name" required></td>
                                        <td><input type="text" name="items[0][hsn]" class="form-control" placeholder="HSN/SAC"></td>
                                        <td><input type="text" inputmode="numeric" name="items[0][qty]" class="form-control item-qty" value="1" required></td>
                                        <td><input type="text" inputmode="decimal" name="items[0][price]" class="form-control item-price" placeholder="0.00" value="" required></td>
                                        <td><input type="text" inputmode="decimal" name="items[0][tax_rate]" class="form-control item-tax-rate" value="18" placeholder="18" required></td>
                                        <td class="text-end fw-bold"><span class="row-total">₹0.00</span></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" id="addRowBtn" class="btn btn-sm btn-outline-success mt-2"><i class="bi bi-plus-circle me-1"></i> Add Another Item</button>

                    <div class="mt-4">
                        <label class="form-label fw-semibold">Invoice Terms & Bank Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Payment terms or instructions..."><?php echo htmlspecialchars($invoice['notes'] ?? 'Thank you for your business. Please make payments to the provided bank account.'); ?></textarea>
                    </div>

                    <!-- Transport & Vehicle Details Section -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="enable_transport" name="enable_transport" value="1" <?php echo (!empty($invoice['enable_transport']) || !empty($existingVehicles)) ? 'checked' : ''; ?> onchange="toggleTransportFields()">
                            <label class="form-check-label fw-bold text-dark" for="enable_transport">
                                <i class="bi bi-truck text-primary me-1"></i> Add Transport / Delivery Details (Vehicle & Driver Info)
                            </label>
                        </div>

                        <div id="transportFieldsContainer" class="p-3 bg-light rounded-3 border <?php echo (!empty($invoice['enable_transport']) || !empty($existingVehicles)) ? '' : 'd-none'; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold small text-secondary">Vehicle & Driver Details</span>
                                <span class="text-muted small">You can add multiple vehicles for this shipment</span>
                            </div>

                            <div id="vehicleRowsContainer">
                                <?php if (!empty($existingVehicles)): ?>
                                    <?php foreach ($existingVehicles as $vIdx => $vItem): ?>
                                        <div class="row g-2 mb-2 align-items-center vehicle-row">
                                            <div class="col-md-4">
                                                <input type="text" name="vehicles[<?php echo $vIdx; ?>][number]" class="form-control" placeholder="Vehicle No (e.g. TN 01 AB 1234)" value="<?php echo htmlspecialchars($vItem['vehicle_number']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" name="vehicles[<?php echo $vIdx; ?>][driver]" class="form-control" placeholder="Driver Name" value="<?php echo htmlspecialchars($vItem['driver_name']); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <input type="text" name="vehicles[<?php echo $vIdx; ?>][phone]" class="form-control" placeholder="Driver Phone" value="<?php echo htmlspecialchars($vItem['driver_phone']); ?>">
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-vehicle-btn" onclick="removeVehicleRow(this)"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="row g-2 mb-2 align-items-center vehicle-row">
                                        <div class="col-md-4">
                                            <input type="text" name="vehicles[0][number]" class="form-control" placeholder="Vehicle No (e.g. TN 01 AB 1234)">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" name="vehicles[0][driver]" class="form-control" placeholder="Driver Name">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" name="vehicles[0][phone]" class="form-control" placeholder="Driver Phone">
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-vehicle-btn" onclick="removeVehicleRow(this)"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addVehicleRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Another Vehicle
                            </button>
                        </div>
                    </div>

                    <script>
                    function toggleTransportFields() {
                        const chk = document.getElementById('enable_transport');
                        const container = document.getElementById('transportFieldsContainer');
                        if (chk && container) {
                            if (chk.checked) {
                                container.classList.remove('d-none');
                            } else {
                                container.classList.add('d-none');
                            }
                        }
                    }

                    function addVehicleRow() {
                        const container = document.getElementById('vehicleRowsContainer');
                        const count = container.querySelectorAll('.vehicle-row').length;
                        const newRow = document.createElement('div');
                        newRow.className = 'row g-2 mb-2 align-items-center vehicle-row';
                        newRow.innerHTML = `
                            <div class="col-md-4">
                                <input type="text" name="vehicles[${count}][number]" class="form-control" placeholder="Vehicle No (e.g. TN 01 AB 1234)">
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="vehicles[${count}][driver]" class="form-control" placeholder="Driver Name">
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="vehicles[${count}][phone]" class="form-control" placeholder="Driver Phone">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-vehicle-btn" onclick="removeVehicleRow(this)"><i class="bi bi-trash"></i></button>
                            </div>
                        `;
                        container.appendChild(newRow);
                    }

                    function removeVehicleRow(btn) {
                        const container = document.getElementById('vehicleRowsContainer');
                        const rows = container.querySelectorAll('.vehicle-row');
                        if (rows.length > 1) {
                            btn.closest('.vehicle-row').remove();
                            reindexVehicleRows();
                        } else {
                            const row = btn.closest('.vehicle-row');
                            row.querySelectorAll('input').forEach(i => i.value = '');
                        }
                    }

                    function reindexVehicleRows() {
                        const rows = document.querySelectorAll('.vehicle-row');
                        rows.forEach((row, index) => {
                            row.querySelectorAll('input').forEach(input => {
                                const name = input.getAttribute('name');
                                if (name) {
                                    input.setAttribute('name', name.replace(/vehicles\[\d+\]/, `vehicles[${index}]`));
                                }
                            });
                        });
                    }
                    </script>
                </div>
            </div>
        </div>

        <!-- Right Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header bg-light">
                    <h6 class="fw-bold mb-0">Invoice Summary</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Invoice Number</label>
                        <input type="text" name="invoice_number" class="form-control bg-light" value="<?php echo htmlspecialchars($docNumber); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Invoice Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?php echo $invoice ? $invoice['issue_date'] : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?php echo $invoice ? $invoice['due_date'] : date('Y-m-d', strtotime('+15 days')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <option value="Unpaid" <?php echo ($invoice && $invoice['payment_status'] == 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="Partial" <?php echo ($invoice && $invoice['payment_status'] == 'Partial') ? 'selected' : ''; ?>>Partial</option>
                            <option value="Paid" <?php echo ($invoice && $invoice['payment_status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount Received / Paid (₹)</label>
                        <input type="text" inputmode="decimal" name="paid_amount" class="form-control" value="<?php echo $invoice ? $invoice['paid_amount'] : '0.00'; ?>">
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal (Excl. Tax):</span>
                        <span class="fw-semibold" id="displaySubtotal">₹<?php echo number_format($invoice['subtotal'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Tax (GST):</span>
                        <span class="fw-semibold" id="displayTax">₹<?php echo number_format($invoice['tax_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 fs-5 fw-bold text-primary">
                        <span>Grand Total:</span>
                        <span id="displayGrandTotal">₹<?php echo number_format($invoice['grand_total'] ?? 0, 2); ?></span>
                    </div>

                    <input type="hidden" name="subtotal" id="inputSubtotal" value="<?php echo $invoice['subtotal'] ?? 0; ?>">
                    <input type="hidden" name="tax_amount" id="inputTaxAmount" value="<?php echo $invoice['tax_amount'] ?? 0; ?>">
                    <input type="hidden" name="grand_total" id="inputGrandTotal" value="<?php echo $invoice['grand_total'] ?? 0; ?>">

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold py-2 rounded-pill">
                            <i class="bi bi-save me-1"></i> Save Sales Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
