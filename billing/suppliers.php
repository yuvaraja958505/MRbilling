<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    if ($stmt->execute([$id])) {
        header("Location: suppliers.php?msg=deleted");
        exit;
    }
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gstin = trim($_POST['gstin'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $bank_details = trim($_POST['bank_details'] ?? '');

    if (empty($name)) {
        $error = 'Supplier name is required.';
    } else {
        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE suppliers SET name=?, company_name=?, email=?, phone=?, gstin=?, address=?, bank_details=? WHERE id=?");
            $stmt->execute([$name, $company_name, $email, $phone, $gstin, $address, $bank_details, $id]);
            header("Location: suppliers.php?msg=updated");
            exit;
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, company_name, email, phone, gstin, address, bank_details) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $company_name, $email, $phone, $gstin, $address, $bank_details]);
            header("Location: suppliers.php?msg=created");
            exit;
        }
    }
}

// Fetch edit supplier if requested
$editSupplier = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $editSupplier = $stmt->fetch();
}

// Fetch all suppliers
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR gstin LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
}
$suppliers = $stmt->fetchAll();

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'Supplier added successfully!';
    if ($_GET['msg'] === 'updated') $success = 'Supplier updated successfully!';
    if ($_GET['msg'] === 'deleted') $success = 'Supplier deleted successfully!';
}

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-truck text-success me-2"></i>Supplier Directory</h3>
        <p class="text-muted mb-0">Manage vendor contact details, tax numbers and banking information for LPO generation.</p>
    </div>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <a href="suppliers.php?action=add" class="btn btn-success rounded-pill px-3">
            <i class="bi bi-plus-lg me-1"></i> Add New Supplier
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card card-custom">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><?php echo ($action === 'edit') ? 'Edit Supplier' : 'Add New Supplier'; ?></h5>
            <a href="suppliers.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
        </div>
        <div class="card-body p-4">
            <form action="suppliers.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $editSupplier['id'] ?? 0; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact / Representative Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editSupplier['name'] ?? ''); ?>" required placeholder="e.g. Jane Smith">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supplier Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($editSupplier['company_name'] ?? ''); ?>" placeholder="e.g. Global Supplies Ltd">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editSupplier['email'] ?? ''); ?>" placeholder="supplier@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editSupplier['phone'] ?? ''); ?>" placeholder="+91 98765 12345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">GSTIN / Tax ID</label>
                        <input type="text" name="gstin" class="form-control" value="<?php echo htmlspecialchars($editSupplier['gstin'] ?? ''); ?>" placeholder="33BBBBB0000B1Z2">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Bank & Account Details</label>
                        <input type="text" name="bank_details" class="form-control" value="<?php echo htmlspecialchars($editSupplier['bank_details'] ?? ''); ?>" placeholder="Bank Name, A/C No, IFSC Code">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Full Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Vendor warehouse or office address..."><?php echo htmlspecialchars($editSupplier['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <a href="suppliers.php" class="btn btn-light me-2">Cancel</a>
                    <button type="submit" class="btn btn-success px-4 rounded-pill">
                        <i class="bi bi-save me-1"></i> Save Supplier Record
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- List View -->
    <div class="card card-custom">
        <div class="card-header bg-white">
            <form action="suppliers.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5 col-sm-8">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, company, or GSTIN..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="suppliers.php" class="btn btn-outline-dark ms-1">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Company Name</th>
                        <th>Contact Details</th>
                        <th>GSTIN</th>
                        <th>Bank Details</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No suppliers found. Click "Add New Supplier" to create one.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($supplier['name']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['company_name'] ?: '-'); ?></td>
                                <td>
                                    <div><i class="bi bi-envelope me-1 text-muted"></i><?php echo htmlspecialchars($supplier['email'] ?: '-'); ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($supplier['gstin'])): ?>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($supplier['gstin']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">No GSTIN</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="small text-muted"><?php echo htmlspecialchars($supplier['bank_details'] ?: '-'); ?></span></td>
                                <td class="text-center">
                                    <a href="suppliers.php?action=edit&id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-success me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="suppliers.php?action=delete&id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this supplier?');" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
