<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$success = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM document_items WHERE doc_type='invoice' AND doc_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
    header("Location: invoices.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $success = 'Sales invoice saved successfully!';
    if ($_GET['msg'] === 'deleted') $success = 'Sales invoice deleted successfully!';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT i.*, c.name AS client_name, c.company_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (i.invoice_number LIKE ? OR c.name LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $sql .= " AND i.payment_status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY i.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Summary metrics
$totalInvoicesCount = count($invoices);
$totalInvoicedVal = array_sum(array_column($invoices, 'grand_total'));
$totalCollectedVal = array_sum(array_column($invoices, 'paid_amount'));
$totalDueVal = $totalInvoicedVal - $totalCollectedVal;

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-receipt text-primary me-2"></i>Sales Invoices Management
        </h3>
        <p class="text-muted mb-0">Manage customer billing and export bills as Client (No GST) or Auditor (With GST).</p>
    </div>
    <div>
        <a href="invoice-create.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Create Sales Invoice
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Invoices</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalInvoicesCount; ?></h3>
                </div>
                <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Invoiced Amount</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1">₹<?php echo number_format($totalInvoicedVal, 2); ?></h3>
                </div>
                <div class="metric-icon bg-indigo bg-opacity-10 text-primary">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Collected Amount</span>
                    <h3 class="fw-bold text-success mb-0 mt-1">₹<?php echo number_format($totalCollectedVal, 2); ?></h3>
                </div>
                <div class="metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Balance Due</span>
                    <h3 class="fw-bold text-danger mb-0 mt-1">₹<?php echo number_format($totalDueVal, 2); ?></h3>
                </div>
                <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom-0">
        <form action="invoices.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5 col-sm-6">
                <div class="input-group search-input-group">
                    <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control ps-1" placeholder="Search by invoice # or client..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-5">
                <select name="status" class="form-select rounded-pill px-3" onchange="this.form.submit()">
                    <option value="">All Payment Statuses</option>
                    <option value="Paid" <?php echo ($statusFilter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Partial" <?php echo ($statusFilter === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                    <option value="Unpaid" <?php echo ($statusFilter === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-auto ms-auto">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="invoices.php" class="btn btn-outline-secondary rounded-pill px-3 ms-1">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Invoice #</th>
                    <th>Client</th>
                    <th>Issue Date</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">GST Tax</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Paid Amount</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Download / Print</th>
                    <th class="text-center pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="py-4">
                                <i class="bi bi-receipt-cutoff text-muted display-4"></i>
                                <h5 class="fw-bold text-secondary mt-3">No Invoices Found</h5>
                                <p class="text-muted small">Create your first sales invoice to record billing and payments.</p>
                                <a href="invoice-create.php" class="btn btn-primary btn-sm rounded-pill px-4 mt-2">
                                    <i class="bi bi-plus-lg me-1"></i> Create Sales Invoice
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark fs-6">
                                <?php echo htmlspecialchars($inv['invoice_number']); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($inv['client_name']); ?></div>
                                <?php if (!empty($inv['company_name'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($inv['company_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo date('d M Y', strtotime($inv['issue_date'])); ?></td>
                            <td class="text-end fw-semibold text-secondary">₹<?php echo number_format($inv['subtotal'], 2); ?></td>
                            <td class="text-end text-muted">₹<?php echo number_format($inv['tax_amount'], 2); ?></td>
                            <td class="text-end fw-bold text-primary fs-6">₹<?php echo number_format($inv['grand_total'], 2); ?></td>
                            <td class="text-end fw-bold text-success">₹<?php echo number_format($inv['paid_amount'], 2); ?></td>
                            <td class="text-center">
                                <?php
                                $statusBadge = 'badge-soft-danger';
                                if ($inv['payment_status'] === 'Paid') $statusBadge = 'badge-soft-success';
                                if ($inv['payment_status'] === 'Partial') $statusBadge = 'badge-soft-warning';
                                ?>
                                <span class="badge <?php echo $statusBadge; ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                    <?php echo $inv['payment_status']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="view-doc.php?type=invoice&id=<?php echo $inv['id']; ?>&mode=without_gst" target="_blank" class="bill-pill-btn bill-pill-client" title="Client Version (Without GST)">
                                        <i class="bi bi-file-earmark me-1"></i> Client Bill
                                    </a>
                                    <a href="view-doc.php?type=invoice&id=<?php echo $inv['id']; ?>&mode=with_gst" target="_blank" class="bill-pill-btn bill-pill-auditor" title="Auditor Version (With GST)">
                                        <i class="bi bi-file-earmark-check me-1"></i> Auditor Bill
                                    </a>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="invoice-create.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon-action" title="Edit Invoice">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="invoices.php?action=delete&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon-action" onclick="return confirm('Are you sure you want to delete this invoice?');" title="Delete Invoice">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

