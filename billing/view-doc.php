<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$type = strtolower($_GET['type'] ?? 'invoice'); // quotation, proforma, invoice, lpo
$id = intval($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'with_gst'; // with_gst, without_gst

// Fetch Company profile
$company = getCompanySettings();

// Fetch Document Data
$doc = null;
$party = null;
$partyLabel = 'Client';

if ($type === 'quotation') {
    $title = 'QUOTATION';
    $numField = 'quotation_number';
    $dateField = 'quotation_date';
    $backPage = 'quotations.php';
    $stmt = $pdo->prepare("SELECT q.*, c.name AS party_name, c.company_name AS party_company, c.email AS party_email, c.phone AS party_phone, c.gstin AS party_gstin, c.address AS party_address, c.state AS party_state FROM quotations q JOIN clients c ON q.client_id = c.id WHERE q.id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
} elseif ($type === 'proforma') {
    $title = 'PROFORMA INVOICE';
    $numField = 'proforma_number';
    $dateField = 'issue_date';
    $backPage = 'proforma.php';
    $stmt = $pdo->prepare("SELECT p.*, c.name AS party_name, c.company_name AS party_company, c.email AS party_email, c.phone AS party_phone, c.gstin AS party_gstin, c.address AS party_address, c.state AS party_state FROM proforma_invoices p JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
} elseif ($type === 'lpo') {
    $title = 'LOCAL PURCHASE ORDER';
    $numField = 'lpo_number';
    $dateField = 'issue_date';
    $partyLabel = 'Supplier';
    $backPage = 'lpo.php';
    $stmt = $pdo->prepare("SELECT l.*, s.name AS party_name, s.company_name AS party_company, s.email AS party_email, s.phone AS party_phone, s.gstin AS party_gstin, s.address AS party_address, '' AS party_state FROM lpos l JOIN suppliers s ON l.supplier_id = s.id WHERE l.id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
} else {
    // Invoice
    $type = 'invoice';
    $title = 'TAX INVOICE';
    $numField = 'invoice_number';
    $dateField = 'issue_date';
    $backPage = 'invoices.php';
    $stmt = $pdo->prepare("SELECT i.*, c.name AS party_name, c.company_name AS party_company, c.email AS party_email, c.phone AS party_phone, c.gstin AS party_gstin, c.address AS party_address, c.state AS party_state FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
}

if (!$doc) {
    die("<div style='padding:50px; text-align:center;'><h2>Document Not Found</h2><p><a href='index.php'>Return to Dashboard</a></p></div>");
}

// Fetch Items
$itemsStmt = $pdo->prepare("SELECT * FROM document_items WHERE doc_type = ? AND doc_id = ?");
$itemsStmt->execute([$type, $id]);
$items = $itemsStmt->fetchAll();

// Fetch Vehicles
$vehStmt = $pdo->prepare("SELECT * FROM document_vehicles WHERE doc_type = ? AND doc_id = ?");
$vehStmt->execute([$type, $id]);
$vehicles = $vehStmt->fetchAll();

if (empty($vehicles) && (!empty($doc['vehicle_number']) || !empty($doc['driver_name']) || !empty($doc['driver_phone']))) {
    $vehicles = [[
        'vehicle_number' => $doc['vehicle_number'],
        'driver_name' => $doc['driver_name'],
        'driver_phone' => $doc['driver_phone']
    ]];
}

// Determine if Inter-state tax applies (e.g. check State code)
$companyState = $company['state'] ?? 'Tamil Nadu';
$partyState = $doc['party_state'] ?? '';
$isInterstate = (!empty($partyState) && strcasecmp(trim($companyState), trim($partyState)) !== 0);

// Helper function to resolve GST State Code
if (!function_exists('getStateCode')) {
    function getStateCode($stateName, $gstin = '') {
        if (!empty($gstin) && strlen(trim($gstin)) >= 2 && ctype_digit(substr(trim($gstin), 0, 2))) {
            return substr(trim($gstin), 0, 2);
        }
        $stateMap = [
            'tamil nadu' => '33', 'delhi' => '07', 'maharashtra' => '27', 'karnataka' => '29',
            'kerala' => '32', 'andhra pradesh' => '37', 'telangana' => '36', 'west bengal' => '19',
            'gujarat' => '24', 'haryana' => '06', 'punjab' => '03', 'uttar pradesh' => '09',
            'rajasthan' => '08', 'madhya pradesh' => '23', 'bihar' => '10', 'goa' => '30'
        ];
        $cleanState = strtolower(trim($stateName));
        return $stateMap[$cleanState] ?? '33';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title . ' - ' . htmlspecialchars($doc[$numField]); ?> (<?php echo ($mode === 'with_gst') ? 'With GST - Auditor' : 'Without GST - Client'; ?>)</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
        }
        .invoice-paper {
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            max-width: 900px;
            margin: 20px auto;
            color: #1e293b;
            font-size: 0.95rem;
        }
        .auditor-paper {
            background: #ffffff;
            padding: 20px;
            border: 1.5px solid #000;
            max-width: 900px;
            margin: 20px auto;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.3;
        }
        .invoice-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .doc-title {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 1px;
            color: #0f172a;
        }
        .table-invoice th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        @media print {
            body { background: #fff !important; }
            .invoice-paper, .auditor-paper { box-shadow: none !important; margin: 0 !important; padding: 0 !important; max-width: 100% !important; border: none !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Mode Toggle Bar -->
<div class="container max-width-900 mt-4 no-print" style="max-width: 900px;">
    <div class="mode-toggle-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="fw-bold me-2"><i class="bi bi-layers-half text-primary me-1"></i> Bill Download Version:</span>
            <div class="btn-group" role="group">
                <a href="view-doc.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&mode=without_gst" class="btn btn-sm <?php echo ($mode === 'without_gst') ? 'btn-success fw-bold' : 'btn-outline-success'; ?>">
                    <i class="bi bi-file-earmark me-1"></i> Client Version (Without GST)
                </a>
                <a href="view-doc.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&mode=with_gst" class="btn btn-sm <?php echo ($mode === 'with_gst') ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark'; ?>">
                    <i class="bi bi-file-earmark-check me-1"></i> Auditor Version (With GST)
                </a>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button onclick="downloadPDF(event)" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-3">
                <i class="bi bi-printer me-1"></i> Print / Save PDF
            </button>
        </div>
    </div>
</div>

<?php if ($mode === 'with_gst'): ?>
<!-- AUDITOR GST BILL FORMAT (EXCLUSIVELY FOR AUDITOR VERSION) -->
<div class="auditor-paper" id="printableBill">
    <!-- Header Block -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
        <tr>
            <td style="width: 55%; vertical-align: top; border: 1px solid #000; padding: 8px;">
                <?php if (!empty($company['logo']) && file_exists(__DIR__ . '/' . $company['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" style="max-height: 45px; max-width: 150px; margin-bottom: 6px; display: block; object-fit: contain;">
                <?php endif; ?>
                <div style="font-size: 15px; font-weight: bold; font-family: Arial, sans-serif; margin-bottom: 4px;">
                    <?php echo htmlspecialchars($company['company_name']); ?>
                </div>
                <div style="font-size: 11px; font-family: Arial, sans-serif; line-height: 1.4;">
                    <?php echo nl2br(htmlspecialchars($company['address'])); ?><br>
                    State Name : <?php echo htmlspecialchars($company['state']); ?>, Code : <?php echo getStateCode($company['state'], $company['gstin']); ?><br>
                    <strong>GSTIN/UIN : <?php echo htmlspecialchars($company['gstin']); ?></strong><br>
                    Email : <?php echo htmlspecialchars($company['email']); ?>
                </div>
            </td>
            <td style="width: 45%; vertical-align: top; border: 1px solid #000; padding: 8px;">
                <div style="font-size: 16px; font-weight: bold; font-family: Arial, sans-serif; margin-bottom: 8px;">
                    <?php echo $title; ?>
                </div>
                <table style="width: 100%; font-size: 11px; font-family: Arial, sans-serif; border-collapse: collapse;">
                    <tr>
                        <td style="width: 40%; padding: 2px 0;">Invoice No -</td>
                        <td style="padding: 2px 0;">: <strong><?php echo htmlspecialchars($doc[$numField]); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Invoice Date</td>
                        <td style="padding: 2px 0;">: <?php echo date('d-m-Y', strtotime($doc[$dateField])); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Copy Type</td>
                        <td style="padding: 2px 0;">: </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Bill Type</td>
                        <td style="padding: 2px 0;">: <?php echo htmlspecialchars($doc['payment_status'] ?? 'Credit'); ?></td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Party Details Section -->
        <tr>
            <td style="width: 55%; vertical-align: top; border: 1px solid #000; padding: 6px 8px; min-height: 130px; height: 130px;">
                <div style="font-size: 11px; color: #222; margin-bottom: 4px;">Details of Receiver ( Bill to )</div>
                <div style="font-size: 12px; font-weight: bold; font-family: Arial, sans-serif; margin-bottom: 3px;">
                    <?php 
                    $clientName = trim($doc['party_name'] ?? '');
                    $companyName = trim($doc['party_company'] ?? '');
                    if (!empty($companyName) && !empty($clientName) && strcasecmp($companyName, $clientName) !== 0) {
                        echo htmlspecialchars($companyName) . ' (' . htmlspecialchars($clientName) . ')';
                    } else {
                        echo htmlspecialchars($companyName ?: $clientName);
                    }
                    ?>
                </div>
                <div style="font-size: 11px; font-family: Arial, sans-serif; line-height: 1.35; margin-bottom: 4px;">
                    <?php echo nl2br(htmlspecialchars($doc['party_address'])); ?>
                </div>
                <div style="font-size: 11px; font-family: Arial, sans-serif;">
                    State Name : <?php echo htmlspecialchars($doc['party_state'] ?: $company['state']); ?>, Code : <?php echo getStateCode($doc['party_state'] ?: $company['state'], $doc['party_gstin']); ?><br>
                    <strong>GSTIN/UIN : <?php echo htmlspecialchars(!empty($doc['party_gstin']) ? $doc['party_gstin'] : '-'); ?></strong><br>
                    Contact Details - <?php echo htmlspecialchars($doc['party_phone'] ?: '-'); ?>
                </div>
            </td>
            <td style="width: 45%; vertical-align: top; border: 1px solid #000; padding: 6px 8px; position: relative; min-height: 130px; height: 130px;">
                <div style="font-size: 11px; color: #222; font-weight: bold; margin-bottom: 4px;">Details of Consignee ( Shipped to )</div>
                <?php if ($type === 'invoice' && !empty($vehicles)): ?>
                    <div style="font-size: 10px; font-family: Arial, sans-serif; line-height: 1.3;">
                        <?php foreach ($vehicles as $vIdx => $vItem): ?>
                            <div style="<?php echo $vIdx > 0 ? 'margin-top: 3px; border-top: 1px dashed #ccc; padding-top: 3px;' : ''; ?>">
                                <strong>Veh #<?php echo ($vIdx + 1); ?>:</strong> <?php echo htmlspecialchars($vItem['vehicle_number'] ?: '-'); ?><br>
                                <strong>Driver:</strong> <?php echo htmlspecialchars($vItem['driver_name'] ?: '-'); ?> | <strong>Ph:</strong> <?php echo htmlspecialchars($vItem['driver_phone'] ?: '-'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="font-size: 11px; font-family: Arial, sans-serif; color: #555;">
                        Same as Receiver Address
                    </div>
                <?php endif; ?>
                <div style="position: absolute; bottom: 6px; left: 8px; font-size: 10px; font-weight: normal;">
                    Shipped through - <?php echo ($type === 'invoice' && !empty($vehicles)) ? 'Road Transport (' . count($vehicles) . ' Vehicle' . (count($vehicles) > 1 ? 's' : '') . ')' : '-'; ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table style="width: 100%; border-collapse: collapse; margin-top: -1px;">
        <thead>
            <tr style="background-color: #ffffff;">
                <th style="border: 1px solid #000; padding: 4px 3px; font-size: 10px; width: 4%; text-align: center;">Sl<br>No</th>
                <th style="border: 1px solid #000; padding: 4px 6px; font-size: 10px; text-align: center;">Description of Goods</th>
                <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 9%; text-align: center;">HSN/SAC</th>
                <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 5%; text-align: center;">Qty</th>
                <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 5%; text-align: center;">Unit</th>
                <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 10%; text-align: center;">Price Per<br>Unit</th>
                <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 10%; text-align: center;">Taxable<br>Amount</th>
                <?php if (!$isInterstate): ?>
                    <th style="border: 1px solid #000; padding: 4px 3px; font-size: 10px; width: 6%; text-align: center;">CGST<br>Rate</th>
                    <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 9%; text-align: center;">CGST<br>Amount</th>
                    <th style="border: 1px solid #000; padding: 4px 3px; font-size: 10px; width: 6%; text-align: center;">SGST<br>Rate</th>
                    <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 9%; text-align: center;">SGST<br>Amount</th>
                <?php else: ?>
                    <th style="border: 1px solid #000; padding: 4px 3px; font-size: 10px; width: 10%; text-align: center;">IGST<br>Rate</th>
                    <th style="border: 1px solid #000; padding: 4px 4px; font-size: 10px; width: 14%; text-align: center;">IGST<br>Amount</th>
                <?php endif; ?>
                <th style="border: 1px solid #000; padding: 4px 6px; font-size: 10px; width: 11%; text-align: center;">Net Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $companyCgst = (float)($company['cgst_rate'] ?? 9.00);
            $companySgst = (float)($company['sgst_rate'] ?? 9.00);
            $companyTotalTax = $companyCgst + $companySgst;

            $sl = 1;
            $totalTaxable = 0;
            $totalCgst = 0;
            $totalSgst = 0;
            $totalIgst = 0;
            $totalNet = 0;

            foreach ($items as $item): 
                $qty = (float)$item['qty'];
                $unitPrice = (float)$item['unit_price'];
                $taxableAmt = $qty * $unitPrice;
                $taxRate = (float)$item['tax_rate'];

                if ($taxRate == 18.00 || $taxRate == 0 || abs($taxRate - $companyTotalTax) < 0.001) {
                    $cgstRate = $companyCgst;
                    $sgstRate = $companySgst;
                } else {
                    $cgstRate = $taxRate / 2;
                    $sgstRate = $taxRate / 2;
                }

                $cgstAmt = $taxableAmt * ($cgstRate / 100);
                $sgstAmt = $taxableAmt * ($sgstRate / 100);
                $taxAmt = $cgstAmt + $sgstAmt;
                $netAmt = $taxableAmt + $taxAmt;

                $totalTaxable += $taxableAmt;
                $totalCgst += $cgstAmt;
                $totalSgst += $sgstAmt;
                $totalIgst += $taxAmt;
                $totalNet += $netAmt;

                $unitStr = $item['unit'] ?? ($qty == floor($qty) && $qty > 1 ? 'EA' : 'Set');
            ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 4px 3px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo $sl++; ?></td>
                    <td style="border: 1px solid #000; padding: 4px 6px; font-size: 11px; vertical-align: top; font-weight: 500;"><?php echo htmlspecialchars($item['item_description']); ?></td>
                    <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo htmlspecialchars($item['hsn_code']); ?></td>
                    <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo (float)$qty; ?></td>
                    <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo htmlspecialchars($unitStr); ?></td>
                    <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: right; vertical-align: top;"><?php echo number_format($unitPrice, 2, '.', ''); ?></td>
                    <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: right; vertical-align: top;"><?php echo number_format($taxableAmt, 2, '.', ''); ?></td>
                    <?php if (!$isInterstate): ?>
                        <td style="border: 1px solid #000; padding: 4px 3px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo (float)$cgstRate; ?>%</td>
                        <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: right; vertical-align: top;"><?php echo number_format($cgstAmt, 2, '.', ''); ?></td>
                        <td style="border: 1px solid #000; padding: 4px 3px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo (float)$sgstRate; ?>%</td>
                        <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: right; vertical-align: top;"><?php echo number_format($sgstAmt, 2, '.', ''); ?></td>
                    <?php else: ?>
                        <td style="border: 1px solid #000; padding: 4px 3px; font-size: 11px; text-align: center; vertical-align: top;"><?php echo (float)($cgstRate + $sgstRate); ?>%</td>
                        <td style="border: 1px solid #000; padding: 4px 4px; font-size: 11px; text-align: right; vertical-align: top;"><?php echo number_format($taxAmt, 2, '.', ''); ?></td>
                    <?php endif; ?>
                    <td style="border: 1px solid #000; padding: 4px 6px; font-size: 11px; text-align: right; vertical-align: top; font-weight: 500;"><?php echo number_format($netAmt, 2, '.', ''); ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Padding rows for visually complete grid -->
            <?php 
            $minRows = 12;
            $fillCount = $minRows - count($items);
            if ($fillCount < 3) $fillCount = 3;
            for ($i = 0; $i < $fillCount; $i++): 
            ?>
                <tr style="height: 22px;">
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                    <?php if (!$isInterstate): ?>
                        <td style="border: 1px solid #000; padding: 3px;"></td>
                        <td style="border: 1px solid #000; padding: 3px;"></td>
                        <td style="border: 1px solid #000; padding: 3px;"></td>
                        <td style="border: 1px solid #000; padding: 3px;"></td>
                    <?php else: ?>
                        <td style="border: 1px solid #000; padding: 3px;"></td>
                        <td style="border: 1px solid #000; padding: 3px;"></td>
                    <?php endif; ?>
                    <td style="border: 1px solid #000; padding: 3px;"></td>
                </tr>
            <?php endfor; ?>

            <!-- Total Row -->
            <tr style="font-weight: bold; background-color: #ffffff;">
                <td colspan="6" style="border: 1px solid #000; padding: 5px 6px; font-size: 11px;">Total</td>
                <td style="border: 1px solid #000; padding: 5px 4px; font-size: 11px; text-align: right;"><?php echo number_format($totalTaxable, 2, '.', ''); ?></td>
                <?php if (!$isInterstate): ?>
                    <td style="border: 1px solid #000; padding: 5px 3px;"></td>
                    <td style="border: 1px solid #000; padding: 5px 4px; font-size: 11px; text-align: right;"><?php echo number_format($totalCgst, 2, '.', ''); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 3px;"></td>
                    <td style="border: 1px solid #000; padding: 5px 4px; font-size: 11px; text-align: right;"><?php echo number_format($totalSgst, 2, '.', ''); ?></td>
                <?php else: ?>
                    <td style="border: 1px solid #000; padding: 5px 3px;"></td>
                    <td style="border: 1px solid #000; padding: 5px 4px; font-size: 11px; text-align: right;"><?php echo number_format($totalIgst, 2, '.', ''); ?></td>
                <?php endif; ?>
                <td style="border: 1px solid #000; padding: 4px 6px; font-size: 11px; text-align: right;"><?php echo number_format($totalNet, 2, '.', ''); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Rounding & Grand Total Section -->
    <?php 
    $rawGrandTotal = $totalNet;
    $roundedGrandTotal = round($rawGrandTotal);
    $roundOff = round($roundedGrandTotal - $rawGrandTotal, 2);
    ?>
    <table style="width: 100%; border-collapse: collapse; margin-top: -1px;">
        <tr>
            <td style="border: 1px solid #000; border-top: none; text-align: right; padding: 4px 10px; font-size: 11px; font-weight: normal;" colspan="2">
                Rounded off
            </td>
            <td style="border: 1px solid #000; border-top: none; width: 11%; text-align: right; padding: 4px 6px; font-size: 11px; font-weight: normal;">
                <?php echo number_format($roundOff, 2, '.', ''); ?>
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; text-align: right; padding: 6px 12px; font-size: 13px; font-weight: bold;" colspan="2">
                Grand Total
            </td>
            <td style="border: 1px solid #000; width: 11%; text-align: right; padding: 6px 6px; font-size: 13px; font-weight: bold;">
                <?php echo number_format($roundedGrandTotal, 2, '.', ''); ?>
            </td>
        </tr>
    </table>

    <!-- Tax Summary Section -->
    <div style="border: 1px solid #000; border-top: none; padding: 4px 0 0 0; background: #fff;">
        <div style="font-size: 12px; font-weight: bold; text-align: center; margin-bottom: 4px;">
            Tax Summary
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-top: -1px;">
            <thead>
                <tr style="background-color: #fff;">
                    <th style="border: 1px solid #000; padding: 4px; text-align: center;">HSN Code</th>
                    <th style="border: 1px solid #000; padding: 4px; text-align: center;">Taxable Value</th>
                    <?php if (!$isInterstate): ?>
                        <th style="border: 1px solid #000; padding: 4px; text-align: center;">CGST %</th>
                        <th style="border: 1px solid #000; padding: 4px; text-align: center;">SGST %</th>
                        <th style="border: 1px solid #000; padding: 4px; text-align: center;">CGST Amount</th>
                        <th style="border: 1px solid #000; padding: 4px; text-align: center;">SGST Amount</th>
                    <?php else: ?>
                        <th style="border: 1px solid #000; padding: 4px; text-align: center;">IGST %</th>
                        <th style="border: 1px solid #000; padding: 4px; text-align: center;">IGST Amount</th>
                    <?php endif; ?>
                    <th style="border: 1px solid #000; padding: 4px; text-align: center;">Total Tax Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Group items by HSN / Tax rate
                $taxGroups = [];
                foreach ($items as $itm) {
                    $hsn = $itm['hsn_code'] ?: '';
                    $rate = (float)$itm['tax_rate'];
                    $key = $hsn . '_' . $rate;
                    if (!isset($taxGroups[$key])) {
                        $taxGroups[$key] = [
                            'hsn' => $hsn,
                            'rate' => $rate,
                            'taxable' => 0,
                            'tax' => 0
                        ];
                    }
                    $taxGroups[$key]['taxable'] += ($itm['qty'] * $itm['unit_price']);
                    $taxGroups[$key]['tax'] += $itm['tax_amount'];
                }

                $sumTaxable = 0;
                $sumCgst = 0;
                $sumSgst = 0;
                $sumIgst = 0;
                $sumTax = 0;

                foreach ($taxGroups as $grp):
                    $tVal = $grp['taxable'];
                    $tAmt = $grp['tax'];
                    $r = (float)$grp['rate'];

                    if ($r == 18.00 || $r == 0 || abs($r - $companyTotalTax) < 0.001) {
                        $cgstR = $companyCgst;
                        $sgstR = $companySgst;
                    } else {
                        $cgstR = $r / 2;
                        $sgstR = $r / 2;
                    }

                    $cgstA = $tVal * ($cgstR / 100);
                    $sgstA = $tVal * ($sgstR / 100);
                    $tAmt = $cgstA + $sgstA;

                    $sumTaxable += $tVal;
                    $sumCgst += $cgstA;
                    $sumSgst += $sgstA;
                    $sumIgst += $tAmt;
                    $sumTax += $tAmt;
                ?>
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center;"><?php echo htmlspecialchars($grp['hsn']); ?></td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($tVal, 2, '.', ''); ?></td>
                        <?php if (!$isInterstate): ?>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;"><?php echo (float)$cgstR; ?>%</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;"><?php echo (float)$sgstR; ?>%</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($cgstA, 2, '.', ''); ?></td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($sgstA, 2, '.', ''); ?></td>
                        <?php else: ?>
                            <td style="border: 1px solid #000; padding: 4px; text-align: center;"><?php echo (float)$r; ?>%</td>
                            <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($tAmt, 2, '.', ''); ?></td>
                        <?php endif; ?>
                        <td style="border: 1px solid #000; padding: 4px; text-align: right; font-weight: 500;"><?php echo number_format($tAmt, 2, '.', ''); ?></td>
                    </tr>
                <?php endforeach; ?>

                <!-- Tax Summary Totals -->
                <tr style="font-weight: bold; background-color: #fff;">
                    <td style="border: 1px solid #000; padding: 4px; text-align: center;">Total</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($sumTaxable, 2, '.', ''); ?></td>
                    <?php if (!$isInterstate): ?>
                        <td style="border: 1px solid #000; padding: 4px;"></td>
                        <td style="border: 1px solid #000; padding: 4px;"></td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($sumCgst, 2, '.', ''); ?></td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($sumSgst, 2, '.', ''); ?></td>
                    <?php else: ?>
                        <td style="border: 1px solid #000; padding: 4px;"></td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($sumIgst, 2, '.', ''); ?></td>
                    <?php endif; ?>
                    <td style="border: 1px solid #000; padding: 4px; text-align: right;"><?php echo number_format($sumTax, 2, '.', ''); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Bank Details & Declaration Footer -->
    <table style="width: 100%; border-collapse: collapse; margin-top: -1px; font-size: 11px;">
        <tr>
            <td style="width: 60%; vertical-align: top; border: 1px solid #000; padding: 6px 8px;">
                <div style="font-weight: bold; margin-bottom: 3px;">Company's Bank Details</div>
                <div>Bank Name : <?php echo htmlspecialchars($company['bank_name']); ?></div>
                <div>A/c No. : <?php echo htmlspecialchars($company['account_number']); ?></div>
                <div>Branch & IFS Code : <?php echo htmlspecialchars($company['ifsc_code']); ?></div>
            </td>
            <td style="width: 40%; vertical-align: top; border: 1px solid #000; padding: 6px 8px; position: relative; min-height: 80px; height: 80px;">
                <div style="font-weight: bold; text-align: right;">for <?php echo htmlspecialchars($company['company_name']); ?></div>
                <div style="position: absolute; bottom: 6px; right: 8px; font-weight: bold;">
                    Authorised Signature
                </div>
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 6px 8px;" colspan="1">
                <div style="text-align: center; font-weight: bold; text-decoration: underline; margin-bottom: 4px;">Declaration</div>
                <div style="font-size: 10px; line-height: 1.35;">
                    We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct
                </div>
            </td>
            <td style="border: 1px solid #000; padding: 6px 8px;" colspan="1"></td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; font-size: 10px;" colspan="2">
                SUBJECT TO <?php echo strtoupper(htmlspecialchars($company['state'] ?? 'CHENNAI')); ?> JURISDICTION
            </td>
        </tr>
    </table>
</div>

<?php else: ?>
<!-- CLIENT VERSION (WITHOUT GST DETAILS) - UNCHANGED -->
<div class="invoice-paper" id="printableBill">
    <div class="invoice-header d-flex justify-content-between align-items-start">
        <div>
            <h2 class="doc-title mb-1"><?php echo ($type === 'invoice') ? 'BILL / INVOICE' : $title; ?></h2>
            <div class="text-muted fw-semibold">Ref #: <span class="text-dark"><?php echo htmlspecialchars($doc[$numField]); ?></span></div>
            <div class="mt-2">
                <span class="badge gst-badge-client px-3 py-2 rounded-pill fs-7">
                    <i class="bi bi-person-check-fill me-1"></i> CLIENT VERSION (WITHOUT GST DETAILS)
                </span>
            </div>
        </div>
        <div class="text-end">
            <?php if (!empty($company['logo']) && file_exists(__DIR__ . '/' . $company['logo'])): ?>
                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" style="max-height: 50px; max-width: 160px; margin-bottom: 8px; display: inline-block; object-fit: contain;"><br>
            <?php endif; ?>
            <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($company['company_name']); ?></h4>
            <div class="text-muted small"><?php echo nl2br(htmlspecialchars($company['address'])); ?></div>
            <div class="small text-muted">Phone: <?php echo htmlspecialchars($company['phone']); ?> | Email: <?php echo htmlspecialchars($company['email']); ?></div>
        </div>
    </div>

    <!-- Billed To / Dates Section -->
    <div class="row mb-4">
        <div class="col-7">
            <h6 class="text-uppercase text-muted fw-bold small mb-2"><?php echo ($type === 'lpo') ? 'PURCHASE FROM (SUPPLIER):' : 'BILLED TO (CLIENT):'; ?></h6>
            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($doc['party_name']); ?></h5>
            <?php if (!empty($doc['party_company'])): ?>
                <div class="fw-semibold text-secondary"><?php echo htmlspecialchars($doc['party_company']); ?></div>
            <?php endif; ?>
            <div class="text-muted small"><?php echo nl2br(htmlspecialchars($doc['party_address'])); ?></div>
            <div class="small text-muted">Phone: <?php echo htmlspecialchars($doc['party_phone'] ?: '-'); ?> | Email: <?php echo htmlspecialchars($doc['party_email'] ?: '-'); ?></div>
        </div>
        <div class="col-5 text-end">
            <div class="bg-light p-3 rounded-3 border">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Issue Date:</span>
                    <span class="fw-bold"><?php echo date('d M Y', strtotime($doc[$dateField])); ?></span>
                </div>
                <?php if (!empty($doc['due_date'])): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Due Date:</span>
                        <span class="fw-bold"><?php echo date('d M Y', strtotime($doc['due_date'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($doc['valid_until'])): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Valid Until:</span>
                        <span class="fw-bold"><?php echo date('d M Y', strtotime($doc['valid_until'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($doc['payment_status'])): ?>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <span class="text-muted">Payment Status:</span>
                        <span class="badge <?php echo ($doc['payment_status'] === 'Paid') ? 'bg-success' : (($doc['payment_status'] === 'Partial') ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                            <?php echo $doc['payment_status']; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($type === 'invoice' && !empty($vehicles)): ?>
        <div class="p-3 mb-4 bg-light rounded-3 border">
            <h6 class="text-uppercase text-muted fw-bold small mb-2"><i class="bi bi-truck text-primary me-1"></i> Transport & Delivery Details (<?php echo count($vehicles); ?> Vehicle<?php echo count($vehicles) > 1 ? 's' : ''; ?>):</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 bg-white small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;" class="text-center">#</th>
                            <th style="width: 35%;">Vehicle Number</th>
                            <th style="width: 35%;">Driver Name</th>
                            <th style="width: 25%;">Driver Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vIdx => $vItem): ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?php echo $vIdx + 1; ?></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($vItem['vehicle_number'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($vItem['driver_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($vItem['driver_phone'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-invoice align-middle">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 42%;">Item Description</th>
                    <th class="text-center" style="width: 12%;">HSN/SAC</th>
                    <th class="text-center" style="width: 10%;">Qty</th>
                    <th class="text-end" style="width: 15%;">Unit Price (₹)</th>
                    <th class="text-end" style="width: 16%;">Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                foreach ($items as $item): 
                    $displayPrice = $item['total_amount'] / ($item['qty'] > 0 ? $item['qty'] : 1);
                ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($item['item_description']); ?></td>
                        <td class="text-center text-muted fw-medium"><?php echo htmlspecialchars(!empty($item['hsn_code']) ? $item['hsn_code'] : '-'); ?></td>
                        <td class="text-center"><?php echo $item['qty']; ?></td>
                        <td class="text-end">₹<?php echo number_format($displayPrice, 2); ?></td>
                        <td class="text-end fw-bold">₹<?php echo number_format($item['total_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals & Bank Summary -->
    <div class="row">
        <div class="col-6">
            <?php if (!empty($doc['notes'])): ?>
                <div class="p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold small text-muted text-uppercase mb-1">Notes / Terms:</h6>
                    <p class="small text-muted mb-0"><?php echo nl2br(htmlspecialchars($doc['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <h6 class="fw-bold small text-muted text-uppercase mb-1">Bank Payment Details:</h6>
                <div class="small text-muted">
                    <div><strong>Bank:</strong> <?php echo htmlspecialchars($company['bank_name']); ?></div>
                    <div><strong>A/C No:</strong> <?php echo htmlspecialchars($company['account_number']); ?></div>
                    <div><strong>IFSC Code:</strong> <?php echo htmlspecialchars($company['ifsc_code']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="bg-light p-3 rounded-3 border">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal Value:</span>
                    <span class="fw-semibold">₹<?php echo number_format($doc['grand_total'], 2); ?></span>
                </div>

                <div class="d-flex justify-content-between pt-2 border-top fs-5 fw-bold text-dark">
                    <span>Grand Total:</span>
                    <span class="text-primary">₹<?php echo number_format($doc['grand_total'], 2); ?></span>
                </div>

                <?php if (isset($doc['paid_amount']) && $doc['paid_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mt-2 pt-2 border-top text-success fw-semibold">
                        <span>Paid Amount:</span>
                        <span>₹<?php echo number_format($doc['paid_amount'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mt-1 text-danger fw-bold">
                        <span>Balance Due:</span>
                        <span>₹<?php echo number_format($doc['grand_total'] - $doc['paid_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row mt-5 pt-4 text-center">
        <div class="col-6 ms-auto">
            <div class="border-top pt-2 fw-bold text-dark" style="width: 220px; margin: 0 auto;">
                Authorized Signatory
                <div class="small text-muted fw-normal"><?php echo htmlspecialchars($company['company_name']); ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- html2pdf Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF(e) {
    const btn = e ? e.currentTarget : (window.event ? window.event.currentTarget : null);
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating PDF...';
        btn.disabled = true;
    }

    const element = document.getElementById('printableBill');
    const filename = '<?php echo htmlspecialchars($doc[$numField]); ?>_<?php echo ($mode === "without_gst" ? "Client_Bill" : "Auditor_Bill"); ?>.pdf';

    const opt = {
        margin:       [8, 8, 8, 8],
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // Generate PDF payload and serve via native HTTP download to prevent browser .tmp file residue
    html2pdf().set(opt).from(element).outputPdf('datauristring').then(function(dataUri) {
        const base64Data = dataUri.split(',')[1] || '';
        if (!base64Data) {
            throw new Error('Failed to encode PDF payload.');
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'export-pdf.php';
        form.style.display = 'none';

        const nameInput = document.createElement('input');
        nameInput.type = 'hidden';
        nameInput.name = 'filename';
        nameInput.value = filename;
        form.appendChild(nameInput);

        const dataInput = document.createElement('input');
        dataInput.type = 'hidden';
        dataInput.name = 'pdf_data';
        dataInput.value = base64Data;
        form.appendChild(dataInput);

        document.body.appendChild(form);
        form.submit();

        setTimeout(function() {
            if (document.body.contains(form)) {
                document.body.removeChild(form);
            }
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }, 1000);
    }).catch(function(err) {
        console.error('PDF Generation Error:', err);
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
        window.print();
    });
}
</script>

</body>
</html>
