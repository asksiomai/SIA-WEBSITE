<?php
// LOGIN: enter email + password -> verify credentials -> read users.role -> correct dashboard.
require __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT user_id, password, role FROM users WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    if ($row && password_verify($password, $row['password']) && in_array($row['role'], ['volunteer', 'organizer', 'super_admin'], true)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['user_id'];
        redirect('dashboard.php');          // dashboard.php shows the dashboard for the user's role
    }
    $error = 'Invalid email or password.';
}
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · Community Volunteer Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=7" rel="stylesheet">
<style>
  .site-disclaimer-wrap { width: 100%; max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem 1rem; text-align: center; }
  .site-disclaimer { background: #fff7d6; color: #2b2b2b; border: 1px solid #ecd98a; border-radius: 12px; padding: 14px 18px; font-size: .9rem; line-height: 1.6; text-align: left; }
  .site-footer-links { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.12); display: flex; justify-content: center; gap: 1.25rem; flex-wrap: wrap; }
  .site-footer-links a { color: inherit; font-weight: 600; font-size: .9rem; text-decoration: none; }
  .site-footer-links a:hover { text-decoration: underline; }
  .site-copy { margin-top: .5rem; font-size: .85rem; opacity: .7; }
</style></head>
<body class="auth-body login-dark">
<div class="auth-wrap">
  <section class="auth-hero login-hero">
    <h1>VOLUNTEER MANAGEMENT</h1>
    <p>Log in to manage volunteers, activities, attendance and your own volunteer records.</p>
    <div class="dev-block">
      <div class="dev-col">
        <div class="dev-label">Developed by</div>
        <div class="dev-name">YOUR GROUP NAME</div>
      </div>
      <div class="dev-col">
        <div class="dev-label">Team</div>
        <div class="dev-team">
          <div>John Peter Villanueva</div>
          <div>Jhosua Tapia</div>
          <div>Jazlee Petalver</div>
          <div>Karlo Mejia</div>
          <div>Warren Beniez</div>
          <div>Merick Salinas</div>
        </div>
      </div>
    </div>
  </section>
  <section class="auth-panel">
    <div class="auth-card login-card">
      <h2>Sign in to your account</h2>
      <p class="login-sub">Enter your email and password to continue.</p>
      <?php if ($flash): ?><div class="alert alert-<?= h($flash['type']) ?>">✅ <?= h($flash['msg']) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= h($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="on" id="loginForm">
        <?= csrf_field() ?>
        <label class="form-label" for="email">Email / Username</label>
        <div class="input-group mb-3">
          <span class="input-group-text">✉</span>
          <input class="form-control" id="email" type="text" name="email" placeholder="you@example.com" value="<?= h($email) ?>" required autofocus>
        </div>
        <label class="form-label" for="password">Password</label>
        <div class="input-group mb-4">
          <span class="input-group-text">🔒</span>
          <input class="form-control" id="password" type="password" name="password" placeholder="Enter your password" required>
          <button class="btn btn-outline-secondary" type="button" id="togglePw" aria-label="Show password"><span class="eye">👁</span></button>
        </div>
        <button class="btn btn-primary w-100 btn-lg login-btn" type="submit" id="loginBtn"><span>Login</span> <span aria-hidden="true">→</span></button>
      </form>
      <div class="login-links">
        <p class="small mb-1">No account yet? <a href="register.php">Register as a volunteer</a></p>
        <p class="small mb-0"><a href="terms.php">Terms &amp; Privacy Policy</a></p>
      </div>
    </div>
  </section>
</div>

<footer class="site-disclaimer-wrap">
  <div class="site-disclaimer">
    <strong>Disclaimer:</strong> This website is created for demonstration and academic purposes only as part of an SIA subject project. It is not a real company, official service, registered business, or commercial website. No claims are made regarding actual business operations, legal standing, or real-world services.
  </div>
  <nav class="site-footer-links">
    <a href="terms.php">Privacy Policy</a>
    <a href="terms.php">Terms</a>
    <a href="terms.php#copyright">Copyright</a>
  </nav>
  <div class="site-copy">&copy; <?= date('Y') ?> Community Volunteer Management System</div>
</footer>

<script>
document.getElementById('togglePw').addEventListener('click', function () {
  var pw = document.getElementById('password'), show = pw.type === 'password';
  pw.type = show ? 'text' : 'password';
  this.querySelector('.eye').textContent = show ? '🙈' : '👁';
  this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});
document.getElementById('loginForm').addEventListener('submit', function () {
  var b = document.getElementById('loginBtn');
  b.disabled = true; b.querySelector('span').textContent = 'Signing in...';
});
</script>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body></html>