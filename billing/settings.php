<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $state = trim($_POST['state'] ?? 'Tamil Nadu');
    $gstin = trim($_POST['gstin'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc_code = trim($_POST['ifsc_code'] ?? '');
    $cgst_rate = floatval($_POST['cgst_rate'] ?? 9.00);
    $sgst_rate = floatval($_POST['sgst_rate'] ?? 9.00);

    $company = getCompanySettings();
    $logo_path = $company['logo'] ?? null;

    // Handle Remove Logo
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        if (!empty($logo_path) && file_exists(__DIR__ . '/' . $logo_path)) {
            @unlink(__DIR__ . '/' . $logo_path);
        }
        $logo_path = null;
    }

    // Handle Upload Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName = $_FILES['logo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'logo_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Delete previous logo file if exists
                if (!empty($company['logo']) && file_exists(__DIR__ . '/' . $company['logo'])) {
                    @unlink(__DIR__ . '/' . $company['logo']);
                }
                $logo_path = 'uploads/' . $newFileName;
            } else {
                $error = 'Failed to move uploaded logo file.';
            }
        } else {
            $error = 'Invalid image file format. Allowed formats: JPG, PNG, GIF, WEBP, SVG.';
        }
    }

    if (empty($company_name)) {
        $error = 'Company name is required.';
    } elseif (empty($error)) {
        $stmt = $pdo->prepare("UPDATE company_settings SET company_name=?, email=?, phone=?, address=?, state=?, gstin=?, bank_name=?, account_number=?, ifsc_code=?, logo=?, cgst_rate=?, sgst_rate=? WHERE id=1");
        $stmt->execute([$company_name, $email, $phone, $address, $state, $gstin, $bank_name, $account_number, $ifsc_code, $logo_path, $cgst_rate, $sgst_rate]);
        $message = 'Company profile and settings updated successfully!';
        $company = getCompanySettings(true);
    }
}

$company = getCompanySettings();

include_once __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-gear text-dark me-2"></i>Company Settings & Billing Profile</h3>
    <p class="text-muted mb-0">Customize your business branding, logo, contact details, GST registration, CGST & SGST tax rates, and payment bank account.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Business Profile & Branding</h5>
    </div>
    <div class="card-body p-4">
        <form action="settings.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="remove_logo" id="remove_logo_input" value="0">

            <div class="row g-3">
                <!-- Company Logo Upload & Preview -->
                <div class="col-12 mb-2">
                    <label class="form-label fw-semibold">Company Logo</label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="border rounded p-2 bg-light text-center" style="width: 140px; height: 90px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <?php if (!empty($company['logo']) && file_exists(__DIR__ . '/' . $company['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" id="logoPreview" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <div id="noLogoPlaceholder" class="text-muted small">
                                    <i class="bi bi-image fs-3 d-block mb-1"></i> No Logo
                                </div>
                                <img src="" alt="Company Logo" id="logoPreview" style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;">
                            <?php endif; ?>
                        </div>
                        <div>
                            <input type="file" name="logo" id="logoInput" class="form-control form-control-sm mb-2" accept="image/*" onchange="previewImage(this)">
                            <div class="form-text small text-muted mb-2">Supported formats: PNG, JPG, JPEG, WEBP, SVG (Max recommended height: 100px).</div>
                            <?php if (!empty($company['logo'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeLogo()">
                                    <i class="bi bi-trash me-1"></i> Remove Logo
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company GSTIN Number</label>
                    <input type="text" name="gstin" class="form-control" value="<?php echo htmlspecialchars($company['gstin'] ?? ''); ?>" placeholder="e.g. 33ABCDE1234F1Z5">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Phone / Mobile</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Company State (for GST)</label>
                    <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($company['state'] ?? 'Tamil Nadu'); ?>" placeholder="e.g. Tamil Nadu">
                </div>

                <!-- Default CGST & SGST Rate Fields -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Default CGST Rate (%)</label>
                    <div class="input-group">
                        <input type="text" inputmode="decimal" name="cgst_rate" class="form-control" value="<?php echo htmlspecialchars($company['cgst_rate'] ?? '9.00'); ?>" placeholder="e.g. 9.00">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Default SGST Rate (%)</label>
                    <div class="input-group">
                        <input type="text" inputmode="decimal" name="sgst_rate" class="form-control" value="<?php echo htmlspecialchars($company['sgst_rate'] ?? '9.00'); ?>" placeholder="e.g. 9.00">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Company Registered Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                </div>
            </div>

            <h5 class="fw-bold mt-4 mb-3 border-top pt-3 text-secondary">Bank Details for Bill Payment</h5>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($company['bank_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Account Number</label>
                    <input type="text" name="account_number" class="form-control" value="<?php echo htmlspecialchars($company['account_number'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" value="<?php echo htmlspecialchars($company['ifsc_code'] ?? ''); ?>">
                </div>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary px-4 rounded-pill">
                    <i class="bi bi-check-lg me-1"></i> Update Company Profile
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('logoPreview');
    const placeholder = document.getElementById('noLogoPlaceholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function removeLogo() {
    if (confirm('Are you sure you want to remove the company logo?')) {
        document.getElementById('remove_logo_input').value = '1';
        document.forms[0].submit();
    }
}
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
