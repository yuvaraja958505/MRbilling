<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/db.php';
}
$company = getCompanySettings();
$userName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company['company_name'] ?? 'Billing System'); ?> | Enterprise Billing</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div id="wrapper">
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <nav class="navbar navbar-expand-lg top-navbar no-print">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-light d-lg-none me-2">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-flex align-items-center me-auto gap-2">
                    <span class="fw-bold text-dark fs-5 mb-0"><?php echo htmlspecialchars($company['company_name'] ?? 'Billing System'); ?></span>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 fs-7">
                        <i class="bi bi-shield-check me-1"></i> GST & Auditor Ready
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown me-1">
                        <button class="btn btn-primary btn-sm dropdown-toggle rounded-pill px-3 shadow-sm fw-semibold" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-plus-circle me-1"></i> Quick Action
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2">
                            <li><a class="dropdown-item py-2" href="invoice-create.php"><i class="bi bi-receipt text-primary me-2 fs-6"></i> New Invoice</a></li>
                            <li><a class="dropdown-item py-2" href="quotation-create.php"><i class="bi bi-file-earmark-text text-info me-2 fs-6"></i> New Quotation</a></li>
                            <li><a class="dropdown-item py-2" href="proforma-create.php"><i class="bi bi-file-earmark-diff text-warning me-2 fs-6"></i> New Proforma</a></li>
                            <li><a class="dropdown-item py-2" href="lpo-create.php"><i class="bi bi-cart-check text-success me-2 fs-6"></i> New LPO</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="clients.php?action=add"><i class="bi bi-person-plus text-secondary me-2 fs-6"></i> Add Client</a></li>
                            <li><a class="dropdown-item py-2" href="suppliers.php?action=add"><i class="bi bi-building-add text-secondary me-2 fs-6"></i> Add Supplier</a></li>
                        </ul>
                    </div>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm fw-semibold d-inline-flex align-items-center gap-1" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="p-4 flex-grow-1">
