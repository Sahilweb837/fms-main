<?php
require_once 'includes/db.php';
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

function panelPathForIndustry(string $business_type): string {
    $business_type = trim($business_type, '/');
    if ($business_type !== '' && is_file(__DIR__ . "/{$business_type}/dashboard.php")) {
        return "{$business_type}/dashboard.php";
    }
    return 'pages/dashboard.php';
}

// If already logged in, redirect properly based on role
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['role'] ?? '';
    $t = $_SESSION['business_type'] ?? 'other';
    if ($r == 'super_admin') {
        header("Location: " . APP_URL . "/admin/index.php"); exit();
    } else {
        header("Location: " . APP_URL . "/" . panelPathForIndustry($t)); exit();
    }
}

try {
    // db.php already required above
} catch (Throwable $e) {
    die("<div style='padding:2rem;text-align:center;font-family:sans-serif;'>
        <h2 style='color:#ff5532;'>Database Connection Error</h2>
        <p>Please check your database credentials in <code>includes/db.php</code>.</p>
        <div style='color:#64748b;font-size:0.8rem;margin-top:1rem;'>" . htmlspecialchars($e->getMessage()) . "</div>
    </div>");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $selected_industry = $_POST['industry_type'] ?? '';
    $login_role = $_POST['login_role'] ?? 'employee'; // admin or employee

    if (empty($selected_industry)) {
        $error = "Please select your industry portal type.";
    } else {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.password, u.role, u.employee_id, u.full_name, u.branch_id, b.business_type, b.branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $actual_industry = $row['business_type'] ?? 'other';

                if ($row['role'] === 'super_admin') {
                    $error = "Super Admins must use the dedicated Admin Portal. <a href='admin/login.php' class='text-white fw-bold underline'>Click here</a>";
                } elseif ($row['role'] === 'admin' && $login_role === 'employee') {
                    $error = "You are a Branch Admin. Please select the 'Branch Admin' toggle.";
                } elseif ($row['role'] === 'employee' && $login_role === 'admin') {
                    $error = "You are a Staff Member. Please select the 'Staff Login' toggle.";
                } elseif ($actual_industry !== $selected_industry) {
                    $error = "Access Denied: Your account belongs to the <strong>" . ucfirst($actual_industry) . "</strong> portal, not <strong>" . ucfirst($selected_industry) . "</strong>.";
                } else {
                    $_SESSION['user_id']       = $row['id'];
                    $_SESSION['username']      = $row['username'];
                    $_SESSION['full_name']     = $row['full_name'] ?? $row['username'];
                    $_SESSION['employee_id']   = $row['employee_id'] ?? '';
                    $_SESSION['role']          = $row['role'];
                    $_SESSION['branch_id']     = $row['branch_id'];
                    $_SESSION['branch_name']   = $row['branch_name'] ?? 'Head Office';
                    $_SESSION['business_type'] = $actual_industry;

                    logActivity($conn, $row['id'], "Login", "Logged in via " . ucfirst($selected_industry) . " portal.");

                    header("Location: " . panelPathForIndustry($actual_industry));
                    exit();
                }
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No active account found for that username.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetCoder ERP — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #ffffff;
            display: flex;
            align-items: stretch;
            margin: 0;
            overflow-x: hidden;
        }

        /* ─── LEFT SIDE (CLEAN BRANDING) ─── */
        .split-left {
            flex: 1.2;
            background: #fff8f1;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            color: #1e293b;
        }
        
        .ems-brand-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            z-index: 10;
        }
        
        .ems-brand-title span {
            color: #ff7b00;
        }

        .ems-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 440px;
            line-height: 1.6;
            z-index: 10;
        }

        /* Subtle Shapes */
        .anim-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            z-index: 1;
        }
        .shape-1 { width: 400px; height: 400px; background: #ffe0b3; top: -100px; left: -100px; }
        .shape-2 { width: 500px; height: 500px; background: #fff2e6; bottom: -150px; right: -100px; }

        /* Mockup */
        .mockup-glass {
            position: absolute;
            right: -80px;
            top: 50%;
            transform: translateY(-50%);
            width: 440px;
            height: 580px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            z-index: 10;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .mock-header { height: 40px; background: #f8fafc; border-radius: 12px; width: 100%; }
        .mock-row { display: flex; gap: 16px; }
        .mock-box { height: 100px; background: #f8fafc; border-radius: 12px; flex: 1; }
        .mock-main { flex: 1; background: #f8fafc; border-radius: 12px; }

        /* ─── RIGHT SIDE (LOGIN FORM) ─── */
        .split-right {
            flex: 1;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-heading {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .login-subheading {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2.5rem;
        }

        /* Role Toggle Switch */
        .role-toggle-box {
            display: flex;
            background: #f1f5f9;
            border-radius: 14px;
            padding: 6px;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .role-option {
            flex: 1;
            text-align: center;
            padding: 12px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            position: relative;
            z-index: 2;
            transition: color 0.3s ease;
        }
        .role-option.active { color: #fff; }
        .role-slider {
            position: absolute;
            top: 6px; left: 6px;
            height: calc(100% - 12px);
            width: calc(50% - 6px);
            background: #ff7b00;
            border-radius: 10px;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
            box-shadow: 0 4px 12px rgba(255, 123, 0, 0.3);
        }

        /* Industry Tiles */
        .industry-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .industry-tile {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 10px;
            text-align: center;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        .industry-tile i { font-size: 1.5rem; margin-bottom: 8px; display: block; color: #94a3b8; }
        .industry-tile span { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .industry-tile:hover {
            border-color: #ff7b00;
            background: #fff8f1;
            transform: translateY(-2px);
        }
        .industry-tile.selected {
            background: #fff8f1;
            border-color: #ff7b00;
            color: #ff7b00;
            box-shadow: 0 4px 12px rgba(255, 123, 0, 0.1);
        }
        .industry-tile.selected i { color: #ff7b00; }

        /* Form Inputs */
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            margin-bottom: 10px;
            display: block;
        }
        .input-group { border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; transition: border-color 0.2s; }
        .input-group-text { background: #f8fafc; border: none; color: #94a3b8; padding: 0 1.25rem; }
        .form-control { border: none; padding: 14px 1rem; font-size: 1rem; color: #1e293b; background: #fff; }
        .form-control:focus { box-shadow: none; }
        .input-group:focus-within { border-color: #ff7b00; box-shadow: 0 0 0 3px rgba(255, 123, 0, 0.1); }

        .btn-login {
            background: #ff7b00;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            width: 100%;
            transition: all 0.2s;
            box-shadow: 0 8px 20px rgba(255, 123, 0, 0.2);
            margin-top: 1rem;
            cursor: pointer;
        }
        .btn-login:hover {
            background: #e66e00;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(255, 123, 0, 0.3);
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #ef4444;
            border-radius: 12px;
            font-size: 0.9rem;
            padding: 14px;
            margin-bottom: 24px;
        }

        @media (max-width: 992px) {
            .split-left { display: none; }
            .split-right { padding: 2rem; width: 100%; }
            .login-container { max-width: 440px; }
        }
    </style>

</head>
<body>

<!-- Left Split: Animated EMS Brand -->
<div class="split-left">
    <div class="anim-shape shape-1"></div>
    <div class="anim-shape shape-2"></div>
    <div class="anim-shape shape-3"></div>

    <div class="mockup-glass">
        <div class="mock-header"></div>
        <div class="mock-row">
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
        </div>
        <div class="mock-main"></div>
    </div>

    <h1 class="ems-brand-title">Net<span>Coder</span><br>Management<br>System</h1>
    <p class="ems-subtitle">The ultimate multi-tenant platform to manage students, staff, and payments seamlessly across your institution.</p>
    <div style="z-index:10;margin-top:2rem;padding:1rem 1.25rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:14px;max-width:400px;">
        <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.1em;color:#64748b;font-weight:700;margin-bottom:6px;"><i class="fas fa-map-marker-alt me-1" style="color:#ff5532;"></i> Our Office</div>
        <div style="font-size:0.82rem;color:#94a3b8;line-height:1.6;">1st Floor, above Gramin Bank, near Govt. ITI, Dari, Dharamshala, Gabli Dar, Himachal Pradesh 176215</div>
    </div>
</div>

<!-- Right Split: Login Form -->
<div class="split-right">
    <div class="login-container">
        <h2 class="login-heading">Welcome Back</h2>
        <p class="login-subheading">Secure access to your specific branch dashboard.</p>

        <?php if($error): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <!-- Hidden inputs -->
            <input type="hidden" name="industry_type" id="industry_type" value="<?php echo isset($_POST['industry_type']) ? htmlspecialchars($_POST['industry_type']) : ''; ?>">
            <input type="hidden" name="login_role" id="loginRoleInput" value="<?php echo isset($_POST['login_role']) ? htmlspecialchars($_POST['login_role']) : 'employee'; ?>">
            
            <!-- Role Toggle -->
            <label class="form-label">1. Select Your Role</label>
            <div class="role-toggle-box">
                <div class="role-slider" id="roleSlider"></div>
                <div class="role-option" id="roleEmployee" onclick="setRole('employee')">Staff Login</div>
                <div class="role-option" id="roleAdmin" onclick="setRole('admin')">Branch Admin</div>
            </div>

            <!-- Industry Selector -->
            <label class="form-label mt-2">2. Select Your Sector</label>
            <div class="industry-grid" style="grid-template-columns: repeat(2, 1fr);">
                <div class="industry-tile" onclick="selectIndustry('it_institution', this)"><i class="fas fa-laptop-code"></i><span>IT Institute</span></div>
                <div class="industry-tile" onclick="selectIndustry('company', this)"><i class="fas fa-building"></i><span>Company</span></div>
            </div>

            <!-- Credentials -->
            <label class="form-label mt-2">3. Account Credentials</label>
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="form-control" required
                           placeholder="Login ID"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>
            <div class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" required
                           placeholder="Password">
                    <button type="button" class="btn btn-link px-3"
                            onclick="togglePwd()" style="border:1px solid #e2e8f0; border-left:none; border-radius:0 12px 12px 0; background:#fff; color:#94a3b8; text-decoration:none;">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="submitBtn">
                <i class="fas fa-sign-in-alt me-2"></i>Authenticate
            </button>
            
            <div class="text-center mt-4">
                <a href="admin/login.php" class="text-muted small text-decoration-none"><i class="fas fa-shield-halved me-1"></i> Super Admin Portal</a>
            </div>
        </form>
    </div>
</div>

<script>
// Role Toggle Logic
function setRole(role) {
    document.getElementById('loginRoleInput').value = role;
    const slider = document.getElementById('roleSlider');
    const optEmp = document.getElementById('roleEmployee');
    const optAdmin = document.getElementById('roleAdmin');
    
    if (role === 'admin') {
        slider.style.transform = 'translateX(100%)';
        slider.style.background = '#ff7b00';
        slider.style.boxShadow = '0 4px 12px rgba(255, 123, 0, 0.3)';
        optAdmin.classList.add('active');
        optEmp.classList.remove('active');
        document.querySelector('.btn-login').style.background = '#ff7b00';
        document.querySelector('.btn-login').style.boxShadow = '0 8px 20px rgba(255, 123, 0, 0.25)';
    } else {
        slider.style.transform = 'translateX(0)';
        slider.style.background = '#ff7b00';
        slider.style.boxShadow = '0 4px 12px rgba(255, 123, 0, 0.3)';
        optEmp.classList.add('active');
        optAdmin.classList.remove('active');
        document.querySelector('.btn-login').style.background = '#ff7b00';
        document.querySelector('.btn-login').style.boxShadow = '0 8px 20px rgba(255, 123, 0, 0.25)';
    }

}

// Initialize Role based on previous selection
const initialRole = document.getElementById('loginRoleInput').value;
setRole(initialRole);

// Industry Selection Logic
function selectIndustry(type, element) {
    document.getElementById('industry_type').value = type;
    document.querySelectorAll('.industry-tile').forEach(el => el.classList.remove('selected'));
    if (element) {
        element.classList.add('selected');
    }
}

// Reselect Industry if validation failed
const preSelected = document.getElementById('industry_type').value;
if (preSelected) {
    const tiles = document.querySelectorAll('.industry-tile');
    for (let tile of tiles) {
        if (tile.getAttribute('onclick').includes("'" + preSelected + "'")) {
            tile.classList.add('selected');
            break;
        }
    }
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (!document.getElementById('industry_type').value) {
        e.preventDefault();
        alert('Please select your sector (icon tile) before authenticating.');
    }
});

function togglePwd() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// Prevent form resubmission prompt on refresh
if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
}
</script>
</body>
</html>
