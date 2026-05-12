<?php
$current_page  = basename($_SERVER['PHP_SELF']);
$btype         = $_SESSION['business_type'] ?? 'other';
$prefix        = APP_URL . '/';

$industry_colors = [
    'school'     => ['primary' => '#ff5532', 'name' => 'School ERP'],
    'college'    => ['primary' => '#ff5532', 'name' => 'College ERP'],
    'dispensary' => ['primary' => '#ff5532', 'name' => 'Clinic'],
    'hotel'      => ['primary' => '#ff5532', 'name' => 'Hotel'],
    'shop'       => ['primary' => '#ff5532', 'name' => 'Shop'],
    'restaurant' => ['primary' => '#ff5532', 'name' => 'Restaurant'],
    'inventory'  => ['primary' => '#ff5532', 'name' => 'Inventory'],
    'company'    => ['primary' => '#ff5532', 'name' => 'Company'],
    'it_institution' => ['primary' => '#ff5532', 'name' => 'IT Institution'],
    'other'      => ['primary' => '#ff5532', 'name' => 'FMS Pro'],
];

$ic     = $industry_colors[$btype] ?? $industry_colors['other'];
$accent = $ic['primary'];

function adjustBrightness($hex, $steps) {
    $steps = max(-255, min(255, $steps));
    $hex   = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1),2).str_repeat(substr($hex,1,1),2).str_repeat(substr($hex,2,1),2);
    }
    $r = max(0,min(255,hexdec(substr($hex,0,2))+$steps));
    $g = max(0,min(255,hexdec(substr($hex,2,2))+$steps));
    $b = max(0,min(255,hexdec(substr($hex,4,2))+$steps));
    return '#'.str_pad(dechex($r),2,'0',STR_PAD_LEFT).str_pad(dechex($g),2,'0',STR_PAD_LEFT).str_pad(dechex($b),2,'0',STR_PAD_LEFT);
}
$accent_hover = adjustBrightness($accent, -25);
$accent_light = $accent.'20';
// Convert hex to R,G,B for Bootstrap rgb vars
list($r,$g,$b) = sscanf($accent, "#%02x%02x%02x");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $ic['name']; ?> — NetCoder ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary: #ff5532;
            --primary-hover: #e64a2a;
            --primary-light: #fff2f0;
            --first-color: #ff5532;
            --first-color-light: #fff2f0;
            --bg-body: #f8f9fc;
            --bg-card: #ffffff;
            --text-main: #111;
            --text-muted: #575757;
            --border-color: #e2e8f0;
            --header-bg: rgba(255, 255, 255, 0.8);
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main); 
        }

        .top-navbar { 
            background: var(--header-bg); 
            backdrop-filter: blur(10px); 
            border-bottom: 1px solid var(--border-color); 
            padding: 0.75rem 1.5rem; 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            height: var(--header-height, 70px);
            display: flex;
            align-items: center;
        }

        .glass-card, .card, .metric-card { 
            background: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 8px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
        }

        .icon-box {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Color overrides */
        .text-primary { color: var(--primary) !important; }
        .bg-primary { background-color: var(--primary) !important; }
        .btn-primary { 
            background: var(--primary) !important; 
            border-color: var(--primary) !important; 
            color: #fff !important; 
            box-shadow: 0 4px 12px rgba(255, 123, 0, 0.2); 
        }
        .btn-primary:hover { 
            background: var(--primary-hover) !important; 
            transform: translateY(-1px); 
        }

        /* Forms */
        .form-control, .form-select { 
            border: 1px solid var(--border-color); 
            border-radius: 10px;
            padding: 0.6rem 1rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 123, 0, 0.1);
        }

        /* Tables */
        .table thead th {
            background-color: #f8f9fa;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .text-dark { color: var(--text-main) !important; }
        .text-muted { color: var(--text-muted) !important; }
        
        .modal-content { 
            background: #fff; 
            border-radius: 16px; 
            border: none; 
            color: var(--text-main); 
        }

        /* Accessibility/UI fixes */
        #menu-toggle {
            color: var(--text-main);
            background: #fff;
            border: 1px solid var(--border-color);
        }
        
        .badge.bg-primary-subtle {
            background-color: var(--primary-light) !important;
            color: var(--primary) !important;
        }
    </style>

</head>
<body>
<div id="preloader"><div class="loader-ring"></div></div>
<div class="d-flex wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100">
        <!-- Top Navbar -->
        <nav class="top-navbar d-flex align-items-center">
            <button class="btn btn-light btn-sm me-3 border" id="menu-toggle"><i class="fas fa-bars"></i></button>

            <div class="d-none d-md-flex position-relative me-3" style="width:240px;">
                <i class="fas fa-search position-absolute top-50 translate-middle-y ms-3 text-muted" style="font-size:0.8rem;"></i>
                <input class="form-control form-control-sm ps-5 bg-light border-0" type="search" placeholder="Search..." id="globalSearch">
            </div>

            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="badge d-none d-sm-inline-flex" style="background:var(--first-color-light);color:var(--first-color);font-size:0.7rem;padding:6px 12px;">
                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($_SESSION['branch_name'] ?? $ic['name']); ?>
                </span>

                <div class="dropdown">
                    <button class="btn btn-light border btn-sm d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle fw-bold" style="width:28px;height:28px;font-size:11px;">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <div class="text-start d-none d-sm-block">
                            <div class="fw-bold" style="font-size:0.8rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                            <div class="text-muted" style="font-size:0.65rem;"><?php echo strtoupper(str_replace('_',' ',$_SESSION['role'])); ?></div>
                        </div>
                        <i class="fas fa-chevron-down text-muted" style="font-size:0.65rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2 rounded-3" style="min-width:180px;">
                        <li class="px-3 pb-2 pt-1 border-bottom mb-1">
                            <div class="fw-bold small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?php echo htmlspecialchars($_SESSION['employee_id'] ?? ''); ?></div>
                        </li>
                        <li><a class="dropdown-item rounded-2 py-2 small" href="#"><i class="fas fa-user-circle me-2 text-muted"></i>My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-2 py-2 small text-danger" href="<?php echo $prefix; ?>pages/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="main-container">

<script>
window.addEventListener('load', () => { document.getElementById('preloader').style.display = 'none'; });
document.getElementById('menu-toggle').addEventListener('click', () => document.querySelector('.wrapper').classList.toggle('toggled'));
</script>

<?php if (isset($trial_expired) && $trial_expired): ?>
<div class="modal fade" id="trialExpiredModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg p-3 rounded-4">
            <div class="modal-body text-center p-4">
                <div style="width:64px;height:64px;background:rgba(239,68,68,0.1);color:#ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h4 class="fw-bold mb-3">Trial Expired</h4>
                <p class="text-muted mb-4 small">Your 30-day free trial has ended. To unlock all Pro features (Staff Management, Expense Tracking, Advanced Settings), please complete the one-time registration payment of <strong>$50</strong>.</p>
                
                <div class="bg-light rounded-3 p-3 text-start mb-4 border">
                    <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:10px;letter-spacing:1px;">Payment Instructions</div>
                    <div class="small text-dark mb-2"><i class="fas fa-university me-2 text-muted" style="width:16px;"></i>Bank: <strong>Global Bank Inc.</strong></div>
                    <div class="small text-dark mb-2"><i class="fas fa-hashtag me-2 text-muted" style="width:16px;"></i>Account: <strong>1234-5678-9012</strong></div>
                    <div class="small text-dark"><i class="fas fa-mobile-alt me-2 text-muted" style="width:16px;"></i>UPI ID: <strong>fms-pro@bank</strong></div>
                </div>
                <p class="small text-muted mb-4" style="font-size:0.75rem;">After payment, please share the <strong>UTR / Transaction ID</strong> with the Super Admin to instantly unlock your account.</p>
                <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Close & Browse Limited</button>
            </div>
        </div>
    </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
    var myModal = new bootstrap.Modal(document.getElementById('trialExpiredModal'));
    myModal.show();
});
</script>
<?php endif; ?>
