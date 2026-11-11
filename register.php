<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if (is_logged_in()) {
    header('Location: my-dashboard.php');
    exit;
}

$errors = [];
$name = trim((string)($_POST['name'] ?? ''));
$studentId = strtoupper(trim((string)($_POST['student_id'] ?? '')));
$email = trim((string)($_POST['email'] ?? ''));
$department = trim((string)($_POST['department'] ?? 'Faculty of Information Technology'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($studentId === '') {
        $errors[] = 'Student ID / Registration number is required (e.g. 220045B).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid university email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check unique email and student_id
    if (empty($errors)) {
        $checkStmt = $db->prepare('SELECT id, email, student_id FROM users WHERE email = ? OR student_id = ?');
        $checkStmt->execute([$email, $studentId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['email'] === $email) {
                $errors[] = 'An account with this email address already exists.';
            }
            if ($existing['student_id'] === $studentId) {
                $errors[] = 'An account with this Student ID already exists.';
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $db->prepare('
            INSERT INTO users (name, student_id, email, password_hash, department, role)
            VALUES (?, ?, ?, ?, ?, "student")
        ');
        $ins->execute([$name, $studentId, $email, $hash, $department]);
        $userId = (int)$db->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'student';
        $_SESSION['user_name'] = $name;

        set_flash('Welcome to CampusGather, ' . $name . '! Your student account is now active.');
        header('Location: my-dashboard.php');
        exit;
    }
}

header_view('Create Student Account · Faculty Event Portal', 'register');
?>

<div class="form-shell narrow" style="margin-top:20px;">
    <div class="form-header" style="text-align:center;">
        <span class="eyebrow">NEW STUDENT REGISTRATION</span>
        <h1>Join the Community</h1>
        <p>Faculty of Information Technology · University of Moratuwa</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠</span>
            <div>
                <?php foreach ($errors as $e): ?>
                    <div><?=h($e)?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="register.php" class="form-card">
        <input type="hidden" name="csrf" value="<?=token()?>">

        <div class="form-group">
            <label for="name">Full Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="<?=h($name)?>" placeholder="e.g. Kasun Bandara" required maxlength="120">
        </div>

        <div class="form-group">
            <label for="student_id">Student ID / Index No. <span class="required">*</span></label>
            <input type="text" id="student_id" name="student_id" value="<?=h($studentId)?>" placeholder="e.g. 220045B" required maxlength="30" style="text-transform:uppercase;">
            <span class="help-text">University of Moratuwa format</span>
        </div>

        <div class="form-group">
            <label for="department">Department / Faculty Section</label>
            <select id="department" name="department">
                <option value="Information Technology" <?=$department === 'Information Technology' ? 'selected' : ''?>>Department of Information Technology</option>
                <option value="Computational Mathematics" <?=$department === 'Computational Mathematics' ? 'selected' : ''?>>Department of Computational Mathematics</option>
                <option value="Interdisciplinary Studies" <?=$department === 'Interdisciplinary Studies' ? 'selected' : ''?>>Department of Interdisciplinary Studies</option>
                <option value="Faculty of IT (General)" <?=$department === 'Faculty of IT (General)' ? 'selected' : ''?>>Faculty of IT (General)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="email">University Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" value="<?=h($email)?>" placeholder="e.g. kasun.22@mora.ac.lk" required maxlength="190">
        </div>

        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" placeholder="At least 8 characters" required minlength="8">
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirm Password <span class="required">*</span></label>
            <input type="password" id="password_confirm" name="password_confirm" placeholder="Re-enter password" required minlength="8">
        </div>

        <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:14px; margin-top:10px;">
            Create Student Account →
        </button>

        <div style="margin-top:20px; text-align:center; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0; padding-top:16px;">
            Already have an account? <a href="login.php" style="font-weight:700;">Sign in here</a>
        </div>
    </form>
</div>

<?php footer_view(); ?>
