<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$id = intval($_GET['id'] ?? 0);
$error = '';

// Handle Conversion to Final Invoice
if (isset($_GET['convert_to']) && $_GET['convert_to'] === 'invoice' && $id > 0) {
    // Fetch Proforma
    $pfStmt = $pdo->prepare("SELECT * FROM proforma_invoices WHERE id = ?");
    $pfStmt->execute([$id]);
    $proforma = $pfStmt->fetch();

    if ($proforma) {
        $iStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type='proforma' AND doc_id=?");
        $iStmt->execute([$id]);
        $items = $iStmt->fetchAll();

        $invNumber = generateDocNumber('INV', 'invoices', 'invoice_number');
        $ins = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, issue_date, due_date, subtotal, tax_amount, grand_total, paid_amount, payment_status, notes) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), ?, ?, ?, 0.00, 'Unpaid', ?)");
        $ins->execute([$invNumber, $proforma['client_id'], $proforma['subtotal'], $proforma['tax_amount'], $proforma['grand_total'], "Converted from Proforma " . $proforma['proforma_number']]);
        $newDocId = $pdo->lastInsertId();

        $itemIns = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('invoice', ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $itemIns->execute([$newDocId, $item['item_description'], $item['hsn_code'], $item['qty'], $item['unit_price'], $item['tax_rate'], $item['tax_amount'], $item['total_amount']]);
        }

        $pdo->prepare("UPDATE proforma_invoices SET status='Converted' WHERE id=?")->execute([$id]);

        header("Location: invoice-create.php?id=" . $newDocId . "&msg=converted");
        exit;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proforma_number = trim($_POST['proforma_number']);
    $client_id = intval($_POST['client_id']);
    $issue_date = $_POST['issue_date'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);

    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);

    $items = $_POST['items'] ?? [];

    if ($client_id <= 0 || empty($issue_date) || empty($items)) {
        $error = 'Please select a client, issue date, and add at least one line item.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE proforma_invoices SET proforma_number=?, client_id=?, issue_date=?, due_date=?, subtotal=?, tax_amount=?, grand_total=?, status=?, notes=? WHERE id=?");
            $stmt->execute([$proforma_number, $client_id, $issue_date, $due_date, $subtotal, $tax_amount, $grand_total, $status, $notes, $id]);

            $pdo->prepare("DELETE FROM document_items WHERE doc_type='proforma' AND doc_id=?")->execute([$id]);
            $docId = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO proforma_invoices (proforma_number, client_id, issue_date, due_date, subtotal, tax_amount, grand_total, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$proforma_number, $client_id, $issue_date, $due_date, $subtotal, $tax_amount, $grand_total, $status, $notes]);
            $docId = $pdo->lastInsertId();
        }

        $itemStmt = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('proforma', ?, ?, ?, ?, ?, ?, ?, ?)");

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

        header("Location: proforma.php?msg=saved");
        exit;
    }
}

// Fetch Proforma data if editing
$proforma = null;
$existingItems = [];
$existingVehicles = [];
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM proforma_invoices WHERE id = ?");
    $stmt->execute([$id]);
    $proforma = $stmt->fetch();

    if ($proforma) {
        $iStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type='proforma' AND doc_id=?");
        $iStmt->execute([$id]);
        $existingItems = $iStmt->fetchAll();

        $vStmt = $pdo->prepare("SELECT * FROM document_vehicles WHERE doc_type='proforma' AND doc_id=?");
        $vStmt->execute([$id]);
        $existingVehicles = $vStmt->fetchAll();

        if (empty($existingVehicles) && (!empty($proforma['vehicle_number']) || !empty($proforma['driver_name']) || !empty($proforma['driver_phone']))) {
            $existingVehicles = [[
                'vehicle_number' => $proforma['vehicle_number'],
                'driver_name' => $proforma['driver_name'],
                'driver_phone' => $proforma['driver_phone']
            ]];
        }
    }
}

$docNumber = $proforma ? $proforma['proforma_number'] : generateDocNumber('PF', 'proforma_invoices', 'proforma_number');
$clients = $pdo->query("SELECT id, name, company_name FROM clients ORDER BY name ASC")->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-diff text-warning me-2"></i><?php echo $id > 0 ? 'Edit Proforma Invoice #' . htmlspecialchars($docNumber) : 'Create New Proforma Invoice'; ?></h3>
        <p class="text-muted mb-0">Issue estimated proforma invoices before final supply delivery.</p>
    </div>
    <div>
        <a href="proforma.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="proforma-create.php<?php echo $id > 0 ? '?id='.$id : ''; ?>" method="POST">
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
                                    <option value="<?php echo $client['id']; ?>" <?php echo ($proforma && $proforma['client_id'] == $client['id']) ? 'selected' : ''; ?>>
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
                        <label class="form-label fw-semibold">Notes / Terms</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Payment terms or proforma conditions..."><?php echo htmlspecialchars($proforma['notes'] ?? 'Proforma invoice issued prior to tax invoice generation.'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header bg-light">
                    <h6 class="fw-bold mb-0">Proforma Summary</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proforma Number</label>
                        <input type="text" name="proforma_number" class="form-control bg-light" value="<?php echo htmlspecialchars($docNumber); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?php echo $proforma ? $proforma['issue_date'] : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?php echo $proforma ? $proforma['due_date'] : date('Y-m-d', strtotime('+15 days')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Draft" <?php echo ($proforma && $proforma['status'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="Sent" <?php echo ($proforma && $proforma['status'] == 'Sent') ? 'selected' : ''; ?>>Sent</option>
                            <option value="Converted" <?php echo ($proforma && $proforma['status'] == 'Converted') ? 'selected' : ''; ?>>Converted</option>
                            <option value="Cancelled" <?php echo ($proforma && $proforma['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal (Excl. Tax):</span>
                        <span class="fw-semibold" id="displaySubtotal">₹<?php echo number_format($proforma['subtotal'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Tax (GST):</span>
                        <span class="fw-semibold" id="displayTax">₹<?php echo number_format($proforma['tax_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 fs-5 fw-bold text-warning">
                        <span>Grand Total:</span>
                        <span id="displayGrandTotal">₹<?php echo number_format($proforma['grand_total'] ?? 0, 2); ?></span>
                    </div>

                    <input type="hidden" name="subtotal" id="inputSubtotal" value="<?php echo $proforma['subtotal'] ?? 0; ?>">
                    <input type="hidden" name="tax_amount" id="inputTaxAmount" value="<?php echo $proforma['tax_amount'] ?? 0; ?>">
                    <input type="hidden" name="grand_total" id="inputGrandTotal" value="<?php echo $proforma['grand_total'] ?? 0; ?>">

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning text-dark fw-bold py-2 rounded-pill">
                            <i class="bi bi-check-circle me-1"></i> Save Proforma Invoice
                        </button>
                        
                        <?php if ($id > 0): ?>
                            <a href="proforma-create.php?id=<?php echo $id; ?>&convert_to=invoice" class="btn btn-outline-primary rounded-pill fw-semibold">
                                <i class="bi bi-receipt me-1"></i> Convert to Final Invoice
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
