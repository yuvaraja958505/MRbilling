<?php
if (!function_exists('getCompanySettings')) {
    require_once __DIR__ . '/../config/db.php';
}
if (!isset($company)) {
    $company = getCompanySettings();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar" class="no-print">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand d-flex align-items-center gap-2 text-decoration-none">
            <?php if (!empty($company['logo']) && file_exists(__DIR__ . '/../' . $company['logo'])): ?>
                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Logo" style="max-height: 38px; max-width: 45px; object-fit: contain; border-radius: 4px;" class="bg-white p-1 flex-shrink-0">
            <?php else: ?>
                <i class="bi bi-journal-text fs-3 text-primary flex-shrink-0"></i>
            <?php endif; ?>
            <span class="fw-semibold lh-sm text-wrap" style="font-size: 0.92rem; color: #ffffff;" title="<?php echo htmlspecialchars($company['company_name'] ?? 'Billing System'); ?>">
                <?php echo htmlspecialchars($company['company_name'] ?? 'Billing System'); ?>
            </span>
        </a>
    </div>

    <ul class="list-unstyled components">
        <li class="<?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="<?php echo (in_array($currentPage, ['quotations.php', 'quotation-create.php'])) ? 'active' : ''; ?>">
            <a href="quotations.php"><i class="bi bi-file-earmark-text"></i> Quotations</a>
        </li>
        <li class="<?php echo (in_array($currentPage, ['proforma.php', 'proforma-create.php'])) ? 'active' : ''; ?>">
            <a href="proforma.php"><i class="bi bi-file-earmark-diff"></i> Proforma Invoices</a>
        </li>
        <li class="<?php echo (in_array($currentPage, ['invoices.php', 'invoice-create.php'])) ? 'active' : ''; ?>">
            <a href="invoices.php"><i class="bi bi-receipt"></i> Sales Invoices</a>
        </li>
        <li class="<?php echo (in_array($currentPage, ['lpo.php', 'lpo-create.php'])) ? 'active' : ''; ?>">
            <a href="lpo.php"><i class="bi bi-cart-check"></i> LPOs (Purchase)</a>
        </li>
        <li class="<?php echo ($currentPage == 'clients.php') ? 'active' : ''; ?>">
            <a href="clients.php"><i class="bi bi-people"></i> Clients Directory</a>
        </li>
        <li class="<?php echo ($currentPage == 'suppliers.php') ? 'active' : ''; ?>">
            <a href="suppliers.php"><i class="bi bi-truck"></i> Suppliers Directory</a>
        </li>
        <li class="<?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>">
            <a href="settings.php"><i class="bi bi-gear"></i> Company Settings</a>
        </li>
        <li class="mt-3 border-top border-white border-opacity-10 pt-2">
            <a href="logout.php" class="text-danger-emphasis"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a>
        </li>
    </ul>
</nav>
