<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$success = '';
$error = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM document_items WHERE doc_type='quotation' AND doc_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    header("Location: quotations.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $success = 'Quotation saved successfully!';
    if ($_GET['msg'] === 'deleted') $success = 'Quotation deleted successfully!';
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT q.*, c.name AS client_name, c.company_name FROM quotations q JOIN clients c ON q.client_id = c.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (q.quotation_number LIKE ? OR c.name LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $sql .= " AND q.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY q.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotations = $stmt->fetchAll();

// Summary metrics
$totalCount = count($quotations);
$totalVal = array_sum(array_column($quotations, 'grand_total'));
$acceptedCount = count(array_filter($quotations, fn($q) => $q['status'] === 'Accepted' || $q['status'] === 'Converted'));
$pendingCount = count(array_filter($quotations, fn($q) => $q['status'] === 'Sent' || $q['status'] === 'Draft'));

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-file-earmark-text text-primary me-2"></i>Quotations Management
        </h3>
        <p class="text-muted mb-0">Manage customer price proposals with dual bill generation options.</p>
    </div>
    <div>
        <a href="quotation-create.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Create Quotation
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
                    <span class="text-muted small text-uppercase fw-bold">Total Quotes</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalCount; ?></h3>
                </div>
                <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Proposed Value</span>
                    <h3 class="fw-bold text-success mb-0 mt-1">₹<?php echo number_format($totalVal, 2); ?></h3>
                </div>
                <div class="metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-currency-rupee"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Accepted & Converted</span>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?php echo $acceptedCount; ?></h3>
                </div>
                <div class="metric-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-check-all"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Pending / Sent</span>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?php echo $pendingCount; ?></h3>
                </div>
                <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom-0">
        <form action="quotations.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5 col-sm-7">
                <div class="input-group search-input-group">
                    <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control ps-1" placeholder="Search by quote # or client name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-5">
                <select name="status" class="form-select rounded-pill px-3" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Draft" <?php echo ($statusFilter === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="Sent" <?php echo ($statusFilter === 'Sent') ? 'selected' : ''; ?>>Sent</option>
                    <option value="Accepted" <?php echo ($statusFilter === 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                    <option value="Rejected" <?php echo ($statusFilter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Converted" <?php echo ($statusFilter === 'Converted') ? 'selected' : ''; ?>>Converted</option>
                </select>
            </div>
            <div class="col-auto ms-auto">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="quotations.php" class="btn btn-outline-secondary rounded-pill px-3 ms-1">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Quote Ref</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Valid Until</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Tax (GST)</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Download / Print</th>
                    <th class="text-center pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="py-4">
                                <i class="bi bi-file-earmark-x text-muted display-4"></i>
                                <h5 class="fw-bold text-secondary mt-3">No Quotations Found</h5>
                                <p class="text-muted small">Create a new proposal to start generating dual GST bills.</p>
                                <a href="quotation-create.php" class="btn btn-primary btn-sm rounded-pill px-4 mt-2">
                                    <i class="bi bi-plus-lg me-1"></i> Create Quotation
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark fs-6">
                                <?php echo htmlspecialchars($q['quotation_number']); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($q['client_name']); ?></div>
                                <?php if (!empty($q['company_name'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($q['company_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo date('d M Y', strtotime($q['quotation_date'])); ?></td>
                            <td class="text-muted"><?php echo $q['valid_until'] ? date('d M Y', strtotime($q['valid_until'])) : '-'; ?></td>
                            <td class="text-end fw-semibold text-secondary">₹<?php echo number_format($q['subtotal'], 2); ?></td>
                            <td class="text-end text-muted">₹<?php echo number_format($q['tax_amount'], 2); ?></td>
                            <td class="text-end fw-bold text-primary fs-6">₹<?php echo number_format($q['grand_total'], 2); ?></td>
                            <td class="text-center">
                                <?php
                                $statusBadge = 'badge-soft-secondary';
                                if ($q['status'] === 'Accepted') $statusBadge = 'badge-soft-success';
                                if ($q['status'] === 'Sent') $statusBadge = 'badge-soft-info';
                                if ($q['status'] === 'Rejected') $statusBadge = 'badge-soft-danger';
                                if ($q['status'] === 'Converted') $statusBadge = 'badge-soft-primary';
                                ?>
                                <span class="badge <?php echo $statusBadge; ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                    <?php echo $q['status']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="view-doc.php?type=quotation&id=<?php echo $q['id']; ?>&mode=without_gst" target="_blank" class="bill-pill-btn bill-pill-client" title="Client Version (Without GST)">
                                        <i class="bi bi-file-earmark me-1"></i> Client Bill
                                    </a>
                                    <a href="view-doc.php?type=quotation&id=<?php echo $q['id']; ?>&mode=with_gst" target="_blank" class="bill-pill-btn bill-pill-auditor" title="Auditor Version (With GST)">
                                        <i class="bi bi-file-earmark-check me-1"></i> Auditor Bill
                                    </a>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="quotation-create.php?id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-primary btn-icon-action" title="Edit Quotation">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="quotations.php?action=delete&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger btn-icon-action" onclick="return confirm('Are you sure you want to delete this quotation?');" title="Delete Quotation">
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

