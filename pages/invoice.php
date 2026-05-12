<?php
require_once '../includes/auth.php';

if (!isset($_GET['id'])) {
    die("Invoice ID is missing.");
}

$fee_id = $_GET['id'];

// Fetch fee and student details
$query = "
    SELECT f.*, s.student_name, s.father_name, s.contact, s.email, s.college, s.duration, c.course_name, 
           u.username as collector_name,
           b.branch_name, b.location, b.phone as branch_phone, b.email as branch_email, b.logo_url, b.description 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users u ON f.collected_by = u.id 
    WHERE f.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $fee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Invoice not found.");
}

$invoice = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $fee_id; ?> - NETCODER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        :root {
            --primary: #FF8C00;
            --secondary: #1a1a2e;
        }
        body { 
            background: #f8fafc; 
            font-family: 'Outfit', sans-serif;
            color: #334155;
            -webkit-print-color-adjust: exact;
        }
        .invoice-card {
            background: #fff;
            border-radius: 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            padding: 50px;
            margin-top: 30px;
            border: 2px solid var(--primary);
            position: relative;
            overflow: hidden;
            max-width: 800px;
        }
        .header-logo img {
            max-width: 180px;
        }
        .table thead th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            padding: 15px;
        }
        .table tbody td {
            padding: 20px 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 2px;
        }
        @media print {
            body { background: white; padding: 0; }
            .invoice-card { box-shadow: none; border: 2px solid var(--primary); margin: 0 auto; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container mb-5">
    <div class="invoice-card mx-auto">
        <div class="row mb-5 align-items-center">
            <div class="col-6">
                <div class="header-logo">
                    <?php if(!empty($invoice['logo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($invoice['logo_url']); ?>" alt="Logo" crossorigin="anonymous" style="max-width:180px; max-height:80px; object-fit:contain;">
                    <?php else: ?>
                        <img src="https://www.netcoder.in/images/logo.png" alt="NETCODER" crossorigin="anonymous">
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-dark"><?php echo strtoupper(htmlspecialchars($invoice['branch_name'] ?? 'DHARAMSHALA BRANCH OFFICE')); ?></span><br>
                    <?php if (!empty($invoice['location'])): ?>
                        <?php echo nl2br(htmlspecialchars($invoice['location'])); ?><br>
                    <?php else: ?>
                        Near Govt. ITI, above Gramin Bank Dari,<br>
                        Dharamshala, Himachal Pradesh (176057)<br>
                    <?php endif; ?>
                    <i class="fas fa-phone-alt me-1 text-primary"></i> <?php echo htmlspecialchars($invoice['branch_phone'] ?? '+91 9816732055, 7590832055'); ?><br>
                    <i class="fas fa-envelope me-1 text-primary"></i> <?php echo htmlspecialchars($invoice['branch_email'] ?? 'info@netcoder.in'); ?>
                    <?php if (!empty($invoice['description'])): ?>
                        <div class="mt-2 text-muted" style="font-size:0.7rem; font-style:italic; opacity:0.8;"><?php echo htmlspecialchars($invoice['description']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 text-end">
                <h2 class="fw-bold mb-0 text-primary">RECEIPT</h2>
                <div class="mt-2">
                    <div class="info-label">Invoice Number</div>
                    <div class="fw-bold text-dark">#NC-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-4 border-end">
                <div class="info-label">Student Details</div>
                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($invoice['student_name']); ?></h6>
                <div class="small text-muted">S/o: <?php echo htmlspecialchars($invoice['father_name']); ?></div>
                <div class="small text-muted">Contact: <?php echo htmlspecialchars($invoice['contact']); ?></div>
            </div>
            <div class="col-4 border-end ps-4">
                <div class="info-label">Course & Duration</div>
                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($invoice['course_name'] ?? 'General'); ?></h6>
                <span class="badge bg-light text-dark border-0 small px-0"><?php echo str_replace('_', ' ', $invoice['duration']); ?></span>
            </div>
            <div class="col-4 ps-4 text-end">
                <div class="info-label">Payment Date</div>
                <div class="fw-bold text-dark"><?php echo date('d F Y', strtotime($invoice['date_collected'])); ?></div>
                <div class="small text-muted"><?php echo date('h:i A', strtotime($invoice['date_collected'])); ?></div>
            </div>
        </div>

        <div class="table-responsive mb-5">
            <table class="table">
                <thead>
                    <tr>
                        <th width="60%">Service Description</th>
                        <th class="text-center">Mode</th>
                        <th class="text-end">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo ucfirst($invoice['fee_type']); ?> Fee Payment</div>
                            <div class="small text-muted">Course enrollment and technology access fees.</div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary border-0 px-3"><?php echo strtoupper($invoice['payment_mode']); ?></span>
                        </td>
                        <td class="text-end fw-bold text-dark">₹<?php echo number_format($invoice['amount'], 2); ?></td>
                    </tr>
                    <?php if($invoice['utr_number']): ?>
                    <tr>
                        <td colspan="3" class="bg-light-subtle py-2">
                            <div class="small text-muted">
                                <i class="fas fa-info-circle me-1"></i> Transaction Reference: <strong><?php echo $invoice['utr_number']; ?></strong>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="border-0">
                        <td colspan="2" class="text-end border-0 pt-4"><h5 class="fw-bold mb-0">Grand Total:</h5></td>
                        <td class="text-end border-0 pt-4"><h3 class="fw-bold text-primary mb-0">₹<?php echo number_format($invoice['amount'], 2); ?></h3></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row mt-5 pt-4 border-top">
            <div class="col-7">
                <div class="p-3 bg-light rounded-4 border border-dashed">
                    <h6 class="info-label mb-2">Terms & Conditions</h6>
                    <ul class="mb-0 ps-3 small text-muted">
                        <li>Fees once paid are non-refundable and non-transferable.</li>
                        <li>This is a computer-generated receipt, signature not required.</li>
                        <li>Please keep this receipt for future academic references.</li>
                    </ul>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="mb-3">
                    <?php 
                        $qr_data = "INV-" . $invoice['id'] . "|Amt:" . $invoice['amount'] . "|Date:" . date('Y-m-d', strtotime($invoice['date_collected'])); 
                    ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?php echo urlencode($qr_data); ?>" class="rounded border p-1" alt="Verification QR">
                </div>
                <div class="info-label">Authorized By</div>
                <div class="fw-bold text-dark"><?php echo strtoupper($invoice['collector_name']); ?></div>
                <div class="small text-muted">NETCODER Administration</div>
            </div>
        </div>
    </div>
</div>

<div class="text-center mb-5 no-print">
    <div class="btn-group shadow-lg rounded-pill p-1 bg-white border">
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-print me-2"></i>Print
        </button>
        <button onclick="downloadPDF()" class="btn btn-outline-danger border-0 rounded-pill px-4">
            <i class="fas fa-file-pdf me-2"></i>PDF
        </button>
        <button onclick="downloadPNG()" class="btn btn-outline-success border-0 rounded-pill px-4">
            <i class="fas fa-image me-2"></i>PNG
        </button>
    </div>
    <div class="mt-3">
        <button onclick="window.close()" class="btn btn-link text-muted text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i> Return to Dashboard
        </button>
    </div>
</div>

<script>
async function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const element = document.querySelector('.invoice-card');
    
    // Ensure images are loaded
    const images = element.getElementsByTagName('img');
    await Promise.all(Array.from(images).map(img => {
        if (img.complete) return Promise.resolve();
        return new Promise(resolve => { img.onload = resolve; img.onerror = resolve; });
    }));

    const canvas = await html2canvas(element, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        logging: false
    });
    
    const imgData = canvas.toDataURL('image/png');
    const pdf = new jsPDF('p', 'mm', 'a4');
    const imgWidth = 210; 
    const imgHeight = (canvas.height * imgWidth) / canvas.width;
    
    pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
    pdf.save(`Invoice_NC_${new Date().getTime()}.pdf`);
}

async function downloadPNG() {
    const element = document.querySelector('.invoice-card');
    
    // Ensure images are loaded
    const images = element.getElementsByTagName('img');
    await Promise.all(Array.from(images).map(img => {
        if (img.complete) return Promise.resolve();
        return new Promise(resolve => { img.onload = resolve; img.onerror = resolve; });
    }));

    const canvas = await html2canvas(element, {
        scale: 3,
        useCORS: true,
        allowTaint: true,
        logging: false
    });
    
    const link = document.createElement('a');
    link.download = `Receipt_NC_${new Date().getTime()}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
