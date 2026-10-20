<?php
// Shared layout (top): sidebar + top bar. Set $pageTitle and $pageSub before including.
$u = current_user();
$menu = menu_for($u['role']);
$current = basename($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? 'Dashboard') ?> · Community Volunteer Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="app-dark role-<?= h($u['role']) ?>">
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php"><span class="brand-mark"><i class="bi bi-person-fill"></i></span><span>Community<br><small>Volunteer Management</small></span></a>
    <nav class="nav flex-column">
      <a class="nav-link <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <?php foreach ($menu as $m): ?>
        <a class="nav-link <?= $current === $m[1] ? 'active' : '' ?>" href="<?= h($m[1]) ?>"><i class="bi <?= h($m[2]) ?>"></i> <?= h($m[0]) ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="btn btn-light d-lg-none" id="menuBtn" type="button" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="text-end lh-sm"><div class="fw-semibold"><?= h(full_name($u)) ?></div><span class="role-pill"><?= h(role_label($u['role'])) ?></span></div>
        <a class="btn btn-outline-secondary btn-sm" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </header>
    <main class="content">
      <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= h($f['type']) ?> alert-dismissible fade show" role="alert"><?= h($f['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
      <?php endif; ?>
      <div class="page-head">
        <h1><?= h($pageTitle ?? '') ?></h1>
        <?php if (!empty($pageSub)): ?><p><?= h($pageSub) ?></p><?php endif; ?>
      </div>
