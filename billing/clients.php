<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    if ($stmt->execute([$id])) {
        header("Location: clients.php?msg=deleted");
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
    $state = trim($_POST['state'] ?? '');

    if (empty($name)) {
        $error = 'Client name is required.';
    } else {
        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE clients SET name=?, company_name=?, email=?, phone=?, gstin=?, address=?, state=? WHERE id=?");
            $stmt->execute([$name, $company_name, $email, $phone, $gstin, $address, $state, $id]);
            header("Location: clients.php?msg=updated");
            exit;
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO clients (name, company_name, email, phone, gstin, address, state) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $company_name, $email, $phone, $gstin, $address, $state]);
            header("Location: clients.php?msg=created");
            exit;
        }
    }
}

// Fetch edit client if requested
$editClient = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $editClient = $stmt->fetch();
}

// Fetch all clients
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR gstin LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY id DESC");
}
$clients = $stmt->fetchAll();

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'Client added successfully!';
    if ($_GET['msg'] === 'updated') $success = 'Client updated successfully!';
    if ($_GET['msg'] === 'deleted') $success = 'Client deleted successfully!';
}

include_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>Client Directory</h3>
        <p class="text-muted mb-0">Manage client contact details, company information and GSTIN credentials.</p>
    </div>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <a href="clients.php?action=add" class="btn btn-primary rounded-pill px-3">
            <i class="bi bi-plus-lg me-1"></i> Add New Client
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
            <h5 class="fw-bold mb-0"><?php echo ($action === 'edit') ? 'Edit Client' : 'Add New Client'; ?></h5>
            <a href="clients.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
        </div>
        <div class="card-body p-4">
            <form action="clients.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $editClient['id'] ?? 0; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Person Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editClient['name'] ?? ''); ?>" required placeholder="e.g. John Doe">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Company / Business Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($editClient['company_name'] ?? ''); ?>" placeholder="e.g. Acme Enterprises">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editClient['email'] ?? ''); ?>" placeholder="client@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editClient['phone'] ?? ''); ?>" placeholder="+91 98765 43210">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">GSTIN / Tax ID</label>
                        <input type="text" name="gstin" class="form-control" value="<?php echo htmlspecialchars($editClient['gstin'] ?? ''); ?>" placeholder="33AAAAA0000A1Z5">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">State / Region</label>
                        <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($editClient['state'] ?? ''); ?>" placeholder="e.g. Tamil Nadu">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Full Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Street address, City, Pincode..."><?php echo htmlspecialchars($editClient['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <a href="clients.php" class="btn btn-light me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill">
                        <i class="bi bi-save me-1"></i> Save Client Record
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- List View -->
    <div class="card card-custom">
        <div class="card-header bg-white">
            <form action="clients.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5 col-sm-8">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, company or GSTIN..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="clients.php" class="btn btn-outline-dark ms-1">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Company Name</th>
                        <th>Email & Phone</th>
                        <th>GSTIN</th>
                        <th>State</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No clients found. Click "Add New Client" to create one.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($client['name']); ?></td>
                                <td><?php echo htmlspecialchars($client['company_name'] ?: '-'); ?></td>
                                <td>
                                    <div><i class="bi bi-envelope me-1 text-muted"></i><?php echo htmlspecialchars($client['email'] ?: '-'); ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($client['phone'] ?: '-'); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($client['gstin'])): ?>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($client['gstin']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">No GSTIN</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($client['state'] ?: '-'); ?></td>
                                <td class="text-center">
                                    <a href="clients.php?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="clients.php?action=delete&id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this client?');" title="Delete">
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
