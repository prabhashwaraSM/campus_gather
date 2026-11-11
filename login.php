<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if (is_logged_in()) {
    header('Location: my-dashboard.php');
    exit;
}

$error = '';
$email = trim((string)($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both your university email and password.';
    } else {
        $stmt = $db->prepare('SELECT id, name, student_id, email, password_hash, department, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            set_flash('Welcome back, ' . $user['name'] . '!');
            $redirect = $_SESSION['redirect_after'] ?? 'index.php';
            unset($_SESSION['redirect_after']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid email or password. Please verify your credentials.';
        }
    }
}

header_view('Sign In · Faculty Event Portal', 'login');
?>

<div class="form-shell narrow" style="margin-top:20px;">
    <div class="form-header" style="text-align:center;">
        <span class="eyebrow">STUDENT & ORGANIZER ACCESS</span>
        <h1>Sign in to CampusGather</h1>
        <p>Faculty of Information Technology · University of Moratuwa</p>
    </div>

    <!-- Quick Demo Credentials Box for Lecturer / Evaluator -->
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px; margin-bottom:24px; font-size:13px; line-height:1.6; color:#1e40af;">
        <strong style="display:block; margin-bottom:6px; color:#1d4ed8;">🔑 Demo Evaluation Accounts:</strong>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
            <div style="background:#ffffff; padding:8px; border-radius:6px; border:1px solid #dbeafe;">
                <span style="font-weight:700; display:block; color:#0f172a;">Organizer Account:</span>
                <code>organizer@mora.ac.lk</code><br>
                Password: <code>Password123!</code>
            </div>
            <div style="background:#ffffff; padding:8px; border-radius:6px; border:1px solid #dbeafe;">
                <span style="font-weight:700; display:block; color:#0f172a;">Student Account:</span>
                <code>student@mora.ac.lk</code><br>
                Password: <code>Password123!</code>
            </div>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠</span>
            <span><?=h($error)?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="login.php" class="form-card">
        <input type="hidden" name="csrf" value="<?=token()?>">

        <div class="form-group">
            <label for="email">University Email Address</label>
            <input type="email" id="email" name="email" value="<?=h($email)?>" placeholder="e.g. student@mora.ac.lk" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••••••" required>
        </div>

        <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:14px; margin-top:10px;">
            Sign In to Account →
        </button>

        <div style="margin-top:20px; text-align:center; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0; padding-top:16px;">
            Don't have an account yet? <a href="register.php" style="font-weight:700;">Register as a student</a>
        </div>
    </form>
</div>

<?php footer_view(); ?>
