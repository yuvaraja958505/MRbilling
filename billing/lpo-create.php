<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$id = intval($_GET['id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lpo_number = trim($_POST['lpo_number']);
    $supplier_id = intval($_POST['supplier_id']);
    $issue_date = $_POST['issue_date'];
    $delivery_date = $_POST['delivery_date'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);

    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);

    $items = $_POST['items'] ?? [];

    if ($supplier_id <= 0 || empty($issue_date) || empty($items)) {
        $error = 'Please select a supplier, issue date, and add at least one line item.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE lpos SET lpo_number=?, supplier_id=?, issue_date=?, delivery_date=?, subtotal=?, tax_amount=?, grand_total=?, status=?, notes=? WHERE id=?");
            $stmt->execute([$lpo_number, $supplier_id, $issue_date, $delivery_date, $subtotal, $tax_amount, $grand_total, $status, $notes, $id]);

            $pdo->prepare("DELETE FROM document_items WHERE doc_type='lpo' AND doc_id=?")->execute([$id]);
            $docId = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO lpos (lpo_number, supplier_id, issue_date, delivery_date, subtotal, tax_amount, grand_total, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$lpo_number, $supplier_id, $issue_date, $delivery_date, $subtotal, $tax_amount, $grand_total, $status, $notes]);
            $docId = $pdo->lastInsertId();
        }

        $itemStmt = $pdo->prepare("INSERT INTO document_items (doc_type, doc_id, item_description, hsn_code, qty, unit_price, tax_rate, tax_amount, total_amount) VALUES ('lpo', ?, ?, ?, ?, ?, ?, ?, ?)");

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

        header("Location: lpo.php?msg=saved");
        exit;
    }
}

// Fetch LPO data if editing
$lpo = null;
$existingItems = [];
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM lpos WHERE id = ?");
    $stmt->execute([$id]);
    $lpo = $stmt->fetch();

    if ($lpo) {
        $iStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type='lpo' AND doc_id=?");
        $iStmt->execute([$id]);
        $existingItems = $iStmt->fetchAll();
    }
}

$docNumber = $lpo ? $lpo['lpo_number'] : generateDocNumber('LPO', 'lpos', 'lpo_number');
$suppliers = $pdo->query("SELECT id, name, company_name FROM suppliers ORDER BY name ASC")->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-cart-check text-success me-2"></i><?php echo $id > 0 ? 'Edit LPO #' . htmlspecialchars($docNumber) : 'Create New Local Purchase Order (LPO)'; ?></h3>
        <p class="text-muted mb-0">Issue official purchase orders to vendors and suppliers.</p>
    </div>
    <div>
        <a href="lpo.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="lpo-create.php<?php echo $id > 0 ? '?id='.$id : ''; ?>" method="POST">
    <div class="row g-4">
        <!-- Main Form Left -->
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>" <?php echo ($lpo && $lpo['supplier_id'] == $sup['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sup['name']); ?> <?php echo $sup['company_name'] ? '('.htmlspecialchars($sup['company_name']).')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 text-end d-flex align-items-end justify-content-end">
                            <a href="suppliers.php?action=add" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-plus"></i> Add New Supplier</a>
                        </div>
                    </div>

                    <!-- Line Items Table -->
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Itemized Purchase Items</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Description</th>
                                    <th style="width: 15%;">HSN/SAC</th>
                                    <th style="width: 12%;">Qty</th>
                                    <th style="width: 15%;">Unit Cost (₹)</th>
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
                                        <td><input type="text" name="items[0][description]" class="form-control" placeholder="Item description / Raw material name" required></td>
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
                        <label class="form-label fw-semibold">Delivery Instructions & Purchase Terms</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Expected delivery time, shipping details, or terms..."><?php echo htmlspecialchars($lpo['notes'] ?? 'Please deliver goods as per agreed purchase specifications.'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header bg-light">
                    <h6 class="fw-bold mb-0">LPO Summary</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">LPO Reference Number</label>
                        <input type="text" name="lpo_number" class="form-control bg-light" value="<?php echo htmlspecialchars($docNumber); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?php echo $lpo ? $lpo['issue_date'] : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expected Delivery Date</label>
                        <input type="date" name="delivery_date" class="form-control" value="<?php echo $lpo ? $lpo['delivery_date'] : date('Y-m-d', strtotime('+10 days')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">LPO Status</label>
                        <select name="status" class="form-select">
                            <option value="Issued" <?php echo ($lpo && $lpo['status'] == 'Issued') ? 'selected' : ''; ?>>Issued</option>
                            <option value="Received" <?php echo ($lpo && $lpo['status'] == 'Received') ? 'selected' : ''; ?>>Received</option>
                            <option value="Cancelled" <?php echo ($lpo && $lpo['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal (Excl. Tax):</span>
                        <span class="fw-semibold" id="displaySubtotal">₹<?php echo number_format($lpo['subtotal'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Tax (GST):</span>
                        <span class="fw-semibold" id="displayTax">₹<?php echo number_format($lpo['tax_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 fs-5 fw-bold text-success">
                        <span>Grand Total:</span>
                        <span id="displayGrandTotal">₹<?php echo number_format($lpo['grand_total'] ?? 0, 2); ?></span>
                    </div>

                    <input type="hidden" name="subtotal" id="inputSubtotal" value="<?php echo $lpo['subtotal'] ?? 0; ?>">
                    <input type="hidden" name="tax_amount" id="inputTaxAmount" value="<?php echo $lpo['tax_amount'] ?? 0; ?>">
                    <input type="hidden" name="grand_total" id="inputGrandTotal" value="<?php echo $lpo['grand_total'] ?? 0; ?>">

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success fw-bold py-2 rounded-pill">
                            <i class="bi bi-save me-1"></i> Save Purchase Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
