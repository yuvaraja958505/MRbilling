<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$success = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM document_items WHERE doc_type='proforma' AND doc_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM proforma_invoices WHERE id=?")->execute([$id]);
    header("Location: proforma.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $success = 'Proforma invoice saved successfully!';
    if ($_GET['msg'] === 'deleted') $success = 'Proforma invoice deleted successfully!';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT p.*, c.name AS client_name, c.company_name FROM proforma_invoices p JOIN clients c ON p.client_id = c.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.proforma_number LIKE ? OR c.name LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proformas = $stmt->fetchAll();

// Metrics
$totalProfCount = count($proformas);
$totalProfVal = array_sum(array_column($proformas, 'grand_total'));
$convertedCount = count(array_filter($proformas, fn($p) => $p['status'] === 'Converted'));

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-file-earmark-diff text-warning me-2"></i>Proforma Invoices
        </h3>
        <p class="text-muted mb-0">Manage pre-invoices and issue client quotes with GST and No-GST bill downloads.</p>
    </div>
    <div>
        <a href="proforma-create.php" class="btn btn-warning text-dark rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Create Proforma Invoice
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
    <div class="col-xl-4 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Proformas</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalProfCount; ?></h3>
                </div>
                <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-file-earmark-diff"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Proforma Total Amount</span>
                    <h3 class="fw-bold text-warning mb-0 mt-1">₹<?php echo number_format($totalProfVal, 2); ?></h3>
                </div>
                <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-currency-rupee"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Converted to Invoice</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo $convertedCount; ?></h3>
                </div>
                <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom-0">
        <form action="proforma.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5 col-sm-6">
                <div class="input-group search-input-group">
                    <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control ps-1" placeholder="Search by proforma # or client name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-5">
                <select name="status" class="form-select rounded-pill px-3" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Draft" <?php echo ($statusFilter === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="Sent" <?php echo ($statusFilter === 'Sent') ? 'selected' : ''; ?>>Sent</option>
                    <option value="Converted" <?php echo ($statusFilter === 'Converted') ? 'selected' : ''; ?>>Converted</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto ms-auto">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="proforma.php" class="btn btn-outline-secondary rounded-pill px-3 ms-1">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Proforma #</th>
                    <th>Client</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Tax (GST)</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Download / Print</th>
                    <th class="text-center pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proformas)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="py-4">
                                <i class="bi bi-file-earmark-diff text-muted display-4"></i>
                                <h5 class="fw-bold text-secondary mt-3">No Proforma Invoices Found</h5>
                                <p class="text-muted small">Create a new proforma invoice to send estimated charges to clients.</p>
                                <a href="proforma-create.php" class="btn btn-warning text-dark btn-sm rounded-pill px-4 mt-2 fw-bold">
                                    <i class="bi bi-plus-lg me-1"></i> Create Proforma Invoice
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proformas as $p): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark fs-6">
                                <?php echo htmlspecialchars($p['proforma_number']); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['client_name']); ?></div>
                                <?php if (!empty($p['company_name'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($p['company_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo date('d M Y', strtotime($p['issue_date'])); ?></td>
                            <td class="text-muted"><?php echo $p['due_date'] ? date('d M Y', strtotime($p['due_date'])) : '-'; ?></td>
                            <td class="text-end fw-semibold text-secondary">₹<?php echo number_format($p['subtotal'], 2); ?></td>
                            <td class="text-end text-muted">₹<?php echo number_format($p['tax_amount'], 2); ?></td>
                            <td class="text-end fw-bold text-warning fs-6">₹<?php echo number_format($p['grand_total'], 2); ?></td>
                            <td class="text-center">
                                <?php
                                $statusBadge = 'badge-soft-secondary';
                                if ($p['status'] === 'Converted') $statusBadge = 'badge-soft-primary';
                                if ($p['status'] === 'Sent') $statusBadge = 'badge-soft-info';
                                if ($p['status'] === 'Cancelled') $statusBadge = 'badge-soft-danger';
                                ?>
                                <span class="badge <?php echo $statusBadge; ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                    <?php echo $p['status']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="view-doc.php?type=proforma&id=<?php echo $p['id']; ?>&mode=without_gst" target="_blank" class="bill-pill-btn bill-pill-client" title="Client Version (Without GST)">
                                        <i class="bi bi-file-earmark me-1"></i> Client Bill
                                    </a>
                                    <a href="view-doc.php?type=proforma&id=<?php echo $p['id']; ?>&mode=with_gst" target="_blank" class="bill-pill-btn bill-pill-auditor" title="Auditor Version (With GST)">
                                        <i class="bi bi-file-earmark-check me-1"></i> Auditor Bill
                                    </a>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="proforma-create.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon-action" title="Edit Proforma">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="proforma.php?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon-action" onclick="return confirm('Are you sure you want to delete this proforma invoice?');" title="Delete Proforma">
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

