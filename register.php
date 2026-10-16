<?php
// REGISTER: public registration always creates a Volunteer account (role = volunteer).
require __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');

$errors = [];
$f = ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'address' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($f['first_name'] === '' || $f['last_name'] === '') $errors[] = 'First name and last name are required.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (empty($_POST['agree'])) $errors[] = 'You must agree to the Terms & Privacy Policy to create an account.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $st->execute([$f['email']]);
        if ($st->fetchColumn() > 0) {
            $errors[] = 'Email is already registered.';
        } else {
            try {
                $pdo->prepare("INSERT INTO users (last_name, first_name, middle_name, email, password, role, phone, address) VALUES (?, ?, ?, ?, ?, 'volunteer', ?, ?)")
                    ->execute([$f['last_name'], $f['first_name'], $f['middle_name'] ?: null, $f['email'], password_hash($password, PASSWORD_DEFAULT), $f['phone'] ?: null, $f['address'] ?: null]);
                flash('success', 'Account created successfully. Please log in.');
                redirect('login.php');
            } catch (PDOException $e) {
                $errors[] = 'Account could not be created. Please try again.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register · Community Volunteer Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=8" rel="stylesheet"></head>
<body class="auth-body login-dark">
<div class="auth-wrap">
  <section class="auth-hero login-hero">
    <h1>Become a volunteer</h1>
    <p>Create your account, then add your skills, interests and availability so organizers can find the right activity for you.</p>
  </section>
  <section class="auth-panel">
    <div class="auth-card login-card wide">
      <h2>Create Account</h2>
      <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">First name *</label><input class="form-control" name="first_name" value="<?= h($f['first_name']) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" value="<?= h($f['middle_name']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Last name *</label><input class="form-control" name="last_name" value="<?= h($f['last_name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Email (used to log in) *</label><input class="form-control" type="email" name="email" value="<?= h($f['email']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= h($f['phone']) ?>"></div>
          <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= h($f['address']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Password * <small class="text-muted">(min. 8 characters)</small></label><input class="form-control" type="password" name="password" required></div>
          <div class="col-md-6"><label class="form-label">Confirm password *</label><input class="form-control" type="password" name="confirm" required></div>
        </div>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="agree" id="agree" value="1" <?= !empty($_POST['agree']) ? 'checked' : '' ?> required>
          <label class="form-check-label small" for="agree">I agree to the <a href="terms.php" target="_blank">Terms &amp; Privacy Policy</a></label>
        </div>
        <button class="btn btn-primary w-100 mt-3 login-btn" type="submit">Create Account</button>
      </form>
      <p class="text-center small mt-3 mb-0 login-links pt-3">Already registered? <a href="login.php">Login</a></p>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body></html>
