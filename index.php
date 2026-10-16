<?php
// START: choose Login or Register (flowchart).
require __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Community Volunteer Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet"></head>
<body class="auth-body">
<div class="auth-wrap">
  <section class="auth-hero">
    <span class="brand-mark lg"><i class="bi bi-hand-thumbs-up-fill"></i></span>
    <h1>Community Volunteer Management System</h1>
    <p>One place to register volunteers, organize community activities, find suitable volunteers, and keep attendance and volunteer hours.</p>
    <ul class="hero-list">
      <li><i class="bi bi-check2-circle"></i> Volunteer profiles, skills, interests and availability</li>
      <li><i class="bi bi-check2-circle"></i> AI-based volunteer recommendations with compatibility scores</li>
      <li><i class="bi bi-check2-circle"></i> Assignments, attendance and participation history</li>
    </ul>
  </section>
  <section class="auth-panel">
    <div class="auth-card text-center">
      <h2>Welcome</h2>
      <p class="text-muted">Choose how you want to continue.</p>
      <a class="btn btn-primary btn-lg w-100 mb-2" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
      <a class="btn btn-outline-primary btn-lg w-100" href="register.php"><i class="bi bi-person-plus"></i> Register as a Volunteer</a>
    </div>
  </section>
</div>
</body></html>
