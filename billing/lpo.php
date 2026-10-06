<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$success = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM document_items WHERE doc_type='lpo' AND doc_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM lpos WHERE id=?")->execute([$id]);
    header("Location: lpo.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $success = 'Local Purchase Order (LPO) saved successfully!';
    if ($_GET['msg'] === 'deleted') $success = 'Local Purchase Order (LPO) deleted successfully!';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT l.*, s.name AS supplier_name, s.company_name FROM lpos l JOIN suppliers s ON l.supplier_id = s.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (l.lpo_number LIKE ? OR s.name LIKE ? OR s.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $sql .= " AND l.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY l.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lpos = $stmt->fetchAll();

// Metrics
$totalLpoCount = count($lpos);
$totalLpoVal = array_sum(array_column($lpos, 'grand_total'));
$receivedCount = count(array_filter($lpos, fn($l) => $l['status'] === 'Received'));

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-cart-check text-success me-2"></i>Local Purchase Orders (LPO)
        </h3>
        <p class="text-muted mb-0">Track purchase orders issued to suppliers and manage procurement billing.</p>
    </div>
    <div>
        <a href="lpo-create.php" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Create LPO Order
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
                    <span class="text-muted small text-uppercase fw-bold">Total LPO Orders</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalLpoCount; ?></h3>
                </div>
                <div class="metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cart-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Purchase Value</span>
                    <h3 class="fw-bold text-success mb-0 mt-1">₹<?php echo number_format($totalLpoVal, 2); ?></h3>
                </div>
                <div class="metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-currency-rupee"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Received Orders</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo $receivedCount; ?></h3>
                </div>
                <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom-0">
        <form action="lpo.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5 col-sm-6">
                <div class="input-group search-input-group">
                    <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control ps-1" placeholder="Search by LPO # or supplier name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-5">
                <select name="status" class="form-select rounded-pill px-3" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Issued" <?php echo ($statusFilter === 'Issued') ? 'selected' : ''; ?>>Issued</option>
                    <option value="Received" <?php echo ($statusFilter === 'Received') ? 'selected' : ''; ?>>Received</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto ms-auto">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="lpo.php" class="btn btn-outline-secondary rounded-pill px-3 ms-1">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">LPO #</th>
                    <th>Supplier</th>
                    <th>Issue Date</th>
                    <th>Delivery Date</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Tax (GST)</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Download / Print</th>
                    <th class="text-center pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lpos)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="py-4">
                                <i class="bi bi-cart-x text-muted display-4"></i>
                                <h5 class="fw-bold text-secondary mt-3">No Purchase Orders Found</h5>
                                <p class="text-muted small">Create a new LPO to record supplier orders and purchase billing.</p>
                                <a href="lpo-create.php" class="btn btn-success btn-sm rounded-pill px-4 mt-2 fw-bold">
                                    <i class="bi bi-plus-lg me-1"></i> Create LPO Order
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lpos as $l): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark fs-6">
                                <?php echo htmlspecialchars($l['lpo_number']); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($l['supplier_name']); ?></div>
                                <?php if (!empty($l['company_name'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($l['company_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo date('d M Y', strtotime($l['issue_date'])); ?></td>
                            <td class="text-muted"><?php echo $l['delivery_date'] ? date('d M Y', strtotime($l['delivery_date'])) : '-'; ?></td>
                            <td class="text-end fw-semibold text-secondary">₹<?php echo number_format($l['subtotal'], 2); ?></td>
                            <td class="text-end text-muted">₹<?php echo number_format($l['tax_amount'], 2); ?></td>
                            <td class="text-end fw-bold text-success fs-6">₹<?php echo number_format($l['grand_total'], 2); ?></td>
                            <td class="text-center">
                                <?php
                                $statusBadge = 'badge-soft-primary';
                                if ($l['status'] === 'Received') $statusBadge = 'badge-soft-success';
                                if ($l['status'] === 'Cancelled') $statusBadge = 'badge-soft-danger';
                                ?>
                                <span class="badge <?php echo $statusBadge; ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                    <?php echo $l['status']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="view-doc.php?type=lpo&id=<?php echo $l['id']; ?>&mode=without_gst" target="_blank" class="bill-pill-btn bill-pill-client" title="Supplier Version (Without GST)">
                                        <i class="bi bi-file-earmark me-1"></i> Supplier Bill
                                    </a>
                                    <a href="view-doc.php?type=lpo&id=<?php echo $l['id']; ?>&mode=with_gst" target="_blank" class="bill-pill-btn bill-pill-auditor" title="Auditor Version (With GST)">
                                        <i class="bi bi-file-earmark-check me-1"></i> Auditor Bill
                                    </a>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="lpo-create.php?id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon-action" title="Edit LPO Order">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="lpo.php?action=delete&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon-action" onclick="return confirm('Are you sure you want to delete this purchase order?');" title="Delete LPO Order">
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

