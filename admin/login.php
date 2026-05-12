<?php
require_once '../includes/db.php';
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// If already logged in as super_admin, go to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'super_admin') {
    header("Location: " . APP_URL . "/admin/index.php");
    exit();
}

// If logged in as someone else, redirect them out
if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'super_admin') {
    header("Location: " . APP_URL . "/index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Only allow super_admin accounts to log in here
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = 'super_admin'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id']       = $row['id'];
            $_SESSION['username']      = $row['username'];
            $_SESSION['role']          = $row['role'];
            $_SESSION['branch_id']     = null; // Super admins don't have a branch
            $_SESSION['business_type'] = 'other'; // Generic fallback

            logActivity($conn, $row['id'], 'Admin Login', 'Super Admin Authenticated via secure portal.');

            header("Location: " . APP_URL . "/admin/index.php");
            exit();
        } else {
            $error = "Access Denied: Invalid Credentials.";
        }
    } else {
        $error = "Access Denied: Unauthorized Account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetCoder ERP — Super Admin Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
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
            color: #1e293b;
        }

        /* ─── LEFT SIDE (CLEAN SECURITY THEME) ─── */
        .split-left {
            flex: 1.2;
            background: #fff8f1;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
        }
        
        .brand-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            z-index: 10;
            color: #1e293b;
        }
        
        .brand-title span {
            color: #ff7b00;
        }

        .subtitle {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 450px;
            line-height: 1.6;
            z-index: 10;
        }

        /* Pattern */
        .pattern-bg {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(#ff7b0015 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 1;
        }

        .anim-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.2;
            z-index: 1;
        }
        .blob-1 { width: 500px; height: 500px; background: #ffe0b3; top: -100px; left: -100px; }

        /* ─── RIGHT SIDE (LOGIN CARD) ─── */
        .split-right {
            flex: 1;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3.5rem;
            position: relative;
            border-left: 1px solid #f1f5f9;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            background: #fff8f1;
            color: #ff7b00;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 2rem;
            border: 1px solid #ff7b0020;
        }

        .login-heading {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            color: #1e293b;
        }
        
        .login-subheading {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 3rem;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 10px;
            display: block;
        }

        .input-group {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s ease;
            overflow: hidden;
        }

        .input-group:focus-within {
            border-color: #ff7b00;
            box-shadow: 0 0 0 3px rgba(255, 123, 0, 0.1);
        }

        .input-group-text {
            background: #f8fafc;
            border: none;
            color: #94a3b8;
            padding: 0 1.25rem;
        }

        .form-control {
            background: transparent;
            border: none;
            padding: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .form-control::placeholder { color: #cbd5e1; }
        .form-control:focus { box-shadow: none; }

        .btn-admin {
            background: #ff7b00;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            margin-top: 2rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 20px rgba(255, 123, 0, 0.2);
            cursor: pointer;
        }

        .btn-admin:hover {
            background: #e66e00;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(255, 123, 0, 0.3);
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #ef4444;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 992px) {
            .split-left { display: none; }
            .split-right { border-left: none; padding: 2rem; width: 100%; }
            .login-card { max-width: 420px; }
        }
    </style>

</head>
<body>

<div class="split-left">
    <div class="pattern-bg"></div>
    <div class="anim-blob blob-1"></div>
    <div class="anim-blob blob-2"></div>

    <div style="z-index:10;">
        <div style="width:72px;height:72px;background:#ff5532;border-radius:22px;display:flex;align-items:center;justify-content:center;margin-bottom:2.5rem;box-shadow:0 20px 40px rgba(255,85,50,0.3);">
            <i class="fas fa-shield-alt fa-2x text-white"></i>
        </div>
        <h1 class="brand-title">Super<span>Admin</span><br>Command Center</h1>
        <p class="subtitle">Secure administrative interface for global network management and system auditing.</p>
    </div>
</div>

<div class="split-right">
    <div class="login-card">
        <div class="admin-badge"><i class="fas fa-fingerprint me-2"></i>Secure Identity</div>
        <h2 class="login-heading">Authenticate</h2>
        <p class="login-subheading">Enter super admin credentials to proceed.</p>

        <?php if($error): ?>
            <div class="alert-error">
                <i class="fas fa-shield-virus"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="admin_id" required autocomplete="off" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Access Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-admin">
                Open Command Center <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="text-center mt-5">
            <a href="../index.php" class="text-muted small text-decoration-none hover-white">
                <i class="fas fa-chevron-left me-1"></i> Exit to Portals
            </a>
        </div>
    </div>
</div>

<script>
if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
}
</script>

</body>
</html>
