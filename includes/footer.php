    </main>
    <?php require __DIR__ . '/site_footer.php'; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.CVMS = { csrf: <?= json_encode(csrf_token()) ?>, role: <?= json_encode($u['role']) ?>, userId: <?= (int)$u['user_id'] ?> };</script>
<script src="assets/js/app.js"></script>
<script>
document.getElementById('menuBtn')?.addEventListener('click', function () { document.getElementById('sidebar').classList.toggle('open'); });
</script>
<?= $extraScript ?? '' ?>
</body>
</html>
