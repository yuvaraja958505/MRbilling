<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$id = intval($_GET['id'] ?? 0);
$error = '';

// Handle Conversion to Proforma or Invoice
if (isset($_GET['convert_to']) && $id > 0) {
    $target = $_GET['convert_to']; // 'proforma' or 'invoice'

    // Fetch quotation
    $qStmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $qStmt->execute([$id]);
    $quotation = $qStmt->fetch();

    if ($quotation) {
        // Fetch items
        $iStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type='quotation' AND doc_id=?");
        $iStmt->execute([$id]);
        $items = $iStmt->fetchAll();

        if ($target === 'proforma') {
            $pfNumber = generateDocNumber('PF', 'proforma_invoices', 'proforma_number');
            $ins = $pdo->prepare("INSERT INTO proforma_invoices (proforma_number, client_id, issue_date, due_date, subtotal, tax_amount, grand_total, status, notes) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), ?, ?, ?, 'Draft', ?)");
            $ins->execute([$pfNumber, $quotation['client_id'], $quotation['subtotal'], $quotation['tax_amount'], $quotation['grand_total'], "Converted from Quotation " . $quotation['quotation_number']]);
            $newDocId = $pdo->lastInsertId();

            // Insert line items
            $itemIns = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('proforma', ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $itemIns->execute([$newDocId, $item['item_description'], $item['hsn_code'], $item['qty'], $item['unit_price'], $item['tax_rate'], $item['tax_amount'], $item['total_amount']]);
            }

            // Mark quotation status Converted
            $pdo->prepare("UPDATE quotations SET status='Converted' WHERE id=?")->execute([$id]);

            header("Location: proforma-create.php?id=" . $newDocId . "&msg=converted");
            exit;

        } elseif ($target === 'invoice') {
            $invNumber = generateDocNumber('INV', 'invoices', 'invoice_number');
            $ins = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, issue_date, due_date, subtotal, tax_amount, grand_total, paid_amount, payment_status, notes) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), ?, ?, ?, 0.00, 'Unpaid', ?)");
            $ins->execute([$invNumber, $quotation['client_id'], $quotation['subtotal'], $quotation['tax_amount'], $quotation['grand_total'], "Converted from Quotation " . $quotation['quotation_number']]);
            $newDocId = $pdo->lastInsertId();

            // Insert line items
            $itemIns = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('invoice', ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $itemIns->execute([$newDocId, $item['item_description'], $item['hsn_code'], $item['qty'], $item['unit_price'], $item['tax_rate'], $item['tax_amount'], $item['total_amount']]);
            }

            // Mark quotation status Converted
            $pdo->prepare("UPDATE quotations SET status='Converted' WHERE id=?")->execute([$id]);

            header("Location: invoice-create.php?id=" . $newDocId . "&msg=converted");
            exit;
        }
    }
}

// Handle Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotation_number = trim($_POST['quotation_number']);
    $client_id = intval($_POST['client_id']);
    $quotation_date = $_POST['quotation_date'];
    $valid_until = $_POST['valid_until'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);

    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);

    $items = $_POST['items'] ?? [];

    if ($client_id <= 0 || empty($quotation_date) || empty($items)) {
        $error = 'Please select a client, quotation date, and add at least one line item.';
    } else {
        if ($id > 0) {
            // Update Quotation
            $stmt = $pdo->prepare("UPDATE quotations SET quotation_number=?, client_id=?, quotation_date=?, valid_until=?, subtotal=?, tax_amount=?, grand_total=?, status=?, notes=? WHERE id=?");
            $stmt->execute([$quotation_number, $client_id, $quotation_date, $valid_until, $subtotal, $tax_amount, $grand_total, $status, $notes, $id]);

            // Clear old items
            $pdo->prepare("DELETE FROM document_items WHERE doc_type='quotation' AND doc_id=?")->execute([$id]);
            $docId = $id;
        } else {
            // Insert Quotation
            $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, client_id, quotation_date, valid_until, subtotal, tax_amount, grand_total, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_number, $client_id, $quotation_date, $valid_until, $subtotal, $tax_amount, $grand_total, $status, $notes]);
            $docId = $pdo->lastInsertId();
        }

        // Insert new items
        $itemStmt = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('quotation', ?, ?, ?, ?, ?, ?, ?, ?)");

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

        header("Location: quotations.php?msg=saved");
        exit;
    }
}

// Fetch Quotation data if editing
$quotation = null;
$existingItems = [];
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$id]);
    $quotation = $stmt->fetch();

    if ($quotation) {
        $iStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type='quotation' AND doc_id=?");
        $iStmt->execute([$id]);
        $existingItems = $iStmt->fetchAll();
    }
}

$docNumber = $quotation ? $quotation['quotation_number'] : generateDocNumber('QT', 'quotations', 'quotation_number');

// Fetch clients
$clients = $pdo->query("SELECT id, name, company_name FROM clients ORDER BY name ASC")->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-text text-info me-2"></i><?php echo $id > 0 ? 'Edit Quotation #' . htmlspecialchars($docNumber) : 'Create New Quotation'; ?></h3>
        <p class="text-muted mb-0">Prepare price estimates and quotes for clients.</p>
    </div>
    <div>
        <a href="quotations.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="quotation-create.php<?php echo $id > 0 ? '?id='.$id : ''; ?>" method="POST">
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
                                    <option value="<?php echo $client['id']; ?>" <?php echo ($quotation && $quotation['client_id'] == $client['id']) ? 'selected' : ''; ?>>
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
                        <label class="form-label fw-semibold">Terms & Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Special terms or conditions..."><?php echo htmlspecialchars($quotation['notes'] ?? 'Quote valid for 30 days from date of issue.'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header bg-light">
                    <h6 class="fw-bold mb-0">Quotation Summary</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quotation Number</label>
                        <input type="text" name="quotation_number" class="form-control bg-light" value="<?php echo htmlspecialchars($docNumber); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date of Issue</label>
                        <input type="date" name="quotation_date" class="form-control" value="<?php echo $quotation ? $quotation['quotation_date'] : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Valid Until</label>
                        <input type="date" name="valid_until" class="form-control" value="<?php echo $quotation ? $quotation['valid_until'] : date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Draft" <?php echo ($quotation && $quotation['status'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="Sent" <?php echo ($quotation && $quotation['status'] == 'Sent') ? 'selected' : ''; ?>>Sent</option>
                            <option value="Accepted" <?php echo ($quotation && $quotation['status'] == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                            <option value="Rejected" <?php echo ($quotation && $quotation['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            <option value="Converted" <?php echo ($quotation && $quotation['status'] == 'Converted') ? 'selected' : ''; ?>>Converted</option>
                        </select>
                    </div>

                    <hr>

                    <!-- Calculated Totals -->
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal (Excl. Tax):</span>
                        <span class="fw-semibold" id="displaySubtotal">₹<?php echo number_format($quotation['subtotal'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Tax (GST):</span>
                        <span class="fw-semibold" id="displayTax">₹<?php echo number_format($quotation['tax_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 fs-5 fw-bold text-primary">
                        <span>Grand Total:</span>
                        <span id="displayGrandTotal">₹<?php echo number_format($quotation['grand_total'] ?? 0, 2); ?></span>
                    </div>

                    <input type="hidden" name="subtotal" id="inputSubtotal" value="<?php echo $quotation['subtotal'] ?? 0; ?>">
                    <input type="hidden" name="tax_amount" id="inputTaxAmount" value="<?php echo $quotation['tax_amount'] ?? 0; ?>">
                    <input type="hidden" name="grand_total" id="inputGrandTotal" value="<?php echo $quotation['grand_total'] ?? 0; ?>">

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-info text-white fw-bold py-2 rounded-pill">
                            <i class="bi bi-check-circle me-1"></i> Save Quotation
                        </button>
                        
                        <?php if ($id > 0): ?>
                            <div class="dropdown d-grid">
                                <button class="btn btn-outline-dark dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-lightning-fill text-warning me-1"></i> Convert Document
                                </button>
                                <ul class="dropdown-menu w-100 shadow border-0">
                                    <li><a class="dropdown-item" href="quotation-create.php?id=<?php echo $id; ?>&convert_to=proforma"><i class="bi bi-file-earmark-diff text-warning me-2"></i> Convert to Proforma Invoice</a></li>
                                    <li><a class="dropdown-item" href="quotation-create.php?id=<?php echo $id; ?>&convert_to=invoice"><i class="bi bi-receipt text-primary me-2"></i> Convert to Final Invoice</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
