<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

// Metrics calculations
$totalRevenue = $pdo->query("SELECT SUM(paid_amount) FROM invoices")->fetchColumn() ?: 0;
$totalOutstanding = $pdo->query("SELECT SUM(grand_total - paid_amount) FROM invoices WHERE payment_status != 'Paid'")->fetchColumn() ?: 0;
$countInvoices = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$countQuotations = $pdo->query("SELECT COUNT(*) FROM quotations")->fetchColumn();
$countProforma = $pdo->query("SELECT COUNT(*) FROM proforma_invoices")->fetchColumn();
$countLPO = $pdo->query("SELECT COUNT(*) FROM lpos")->fetchColumn();
$countClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$countSuppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

// Recent Invoices
$recentInvoices = $pdo->query("SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON i.client_id = c.id ORDER BY i.id DESC LIMIT 5")->fetchAll();

// Recent Quotations
$recentQuotations = $pdo->query("SELECT q.*, c.name AS client_name FROM quotations q JOIN clients c ON q.client_id = c.id ORDER BY q.id DESC LIMIT 5")->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Executive Billing Dashboard</h3>
        <p class="text-muted mb-0">Overview of client invoices, quotes, supplier LPOs and dual tax bill generation.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="invoice-create.php" class="btn btn-primary rounded-pill px-3 fw-bold">
            <i class="bi bi-plus-lg me-1"></i> New Invoice
        </a>
        <a href="quotation-create.php" class="btn btn-info text-white rounded-pill px-3 fw-bold">
            <i class="bi bi-file-earmark-text me-1"></i> New Quotation
        </a>
    </div>
</div>

<!-- Key Performance Indicators Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Sales Collected</span>
                    <h3 class="fw-bold text-success mb-0 mt-1">₹<?php echo number_format($totalRevenue, 2); ?></h3>
                </div>
                <div class="metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Outstanding Balance</span>
                    <h3 class="fw-bold text-danger mb-0 mt-1">₹<?php echo number_format($totalOutstanding, 2); ?></h3>
                </div>
                <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Sales Invoices</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo $countInvoices; ?></h3>
                </div>
                <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card metric-card p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Quotations & Proformas</span>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?php echo ($countQuotations + $countProforma); ?></h3>
                </div>
                <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-custom p-3 mb-0 text-center">
            <i class="bi bi-people fs-2 text-primary mb-1"></i>
            <h5 class="fw-bold mb-0"><?php echo $countClients; ?></h5>
            <span class="text-muted small">Registered Clients</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom p-3 mb-0 text-center">
            <i class="bi bi-truck fs-2 text-success mb-1"></i>
            <h5 class="fw-bold mb-0"><?php echo $countSuppliers; ?></h5>
            <span class="text-muted small">Registered Suppliers</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom p-3 mb-0 text-center">
            <i class="bi bi-cart-check fs-2 text-info mb-1"></i>
            <h5 class="fw-bold mb-0"><?php echo $countLPO; ?></h5>
            <span class="text-muted small">Purchase Orders (LPOs)</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom p-3 mb-0 text-center">
            <i class="bi bi-file-earmark-diff fs-2 text-warning mb-1"></i>
            <h5 class="fw-bold mb-0"><?php echo $countProforma; ?></h5>
            <span class="text-muted small">Proforma Invoices</span>
        </div>
    </div>
</div>

<!-- Dual Tax Mode Highlight Banner -->
<div class="card bg-gradient text-white p-4 mb-4 rounded-4 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #312e81 100%);">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Dual Bill Generation Feature</h5>
            <p class="mb-0 text-white-50">Single bill entry can be exported in 2 distinct versions: <strong>Without GST for Clients</strong> and <strong>With GST Breakdown for Auditors</strong>.</p>
        </div>
        <div>
            <a href="invoices.php" class="btn btn-warning text-dark font-bold rounded-pill px-4">Try Dual Export <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </div>
</div>

<!-- Recent Invoices & Recent Quotations Tables -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card card-custom">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-receipt text-primary me-2"></i>Recent Sales Invoices</h6>
                <a href="invoices.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentInvoices)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No recent invoices found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentInvoices as $inv): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                                    <td class="fw-bold text-primary">₹<?php echo number_format($inv['grand_total'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($inv['payment_status'] === 'Paid') ? 'bg-success' : (($inv['payment_status'] === 'Partial') ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                            <?php echo $inv['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-doc.php?type=invoice&id=<?php echo $inv['id']; ?>&mode=without_gst" target="_blank" class="btn btn-sm btn-outline-success" title="Client Bill">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-custom">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-info me-2"></i>Recent Quotations</h6>
                <a href="quotations.php" class="btn btn-sm btn-outline-info">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentQuotations)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No recent quotations found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentQuotations as $q): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($q['quotation_number']); ?></td>
                                    <td><?php echo htmlspecialchars($q['client_name']); ?></td>
                                    <td class="fw-bold text-info">₹<?php echo number_format($q['grand_total'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($q['status'] === 'Accepted') ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $q['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-doc.php?type=quotation&id=<?php echo $q['id']; ?>&mode=without_gst" target="_blank" class="btn btn-sm btn-outline-success" title="Client Quote">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
