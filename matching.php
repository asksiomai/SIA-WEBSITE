<?php
// GENERATE AI MATCHING: recommends volunteers only. The organizer decides on the "Select & Assign" page.
require __DIR__ . '/includes/auth.php';
$u = require_role(['super_admin', 'organizer']);
$a = find_activity($pdo, $u, $_GET['activity_id'] ?? 0);

$pageTitle = 'AI Volunteer Matching';
$pageSub = $a ? $a['activity_name'] . ' · ' . $a['activity_date'] : 'Choose an activity to see recommended volunteers.';
require __DIR__ . '/includes/header.php';
if (!$a) { activity_picker(visible_activities($pdo, $u), 'matching.php', 'Matching uses the activity requirements, so add requirements first.'); require __DIR__ . '/includes/footer.php'; exit; }

$st = $pdo->prepare('SELECT r.required_level, s.skill_name FROM activity_requirements r JOIN skills s ON s.skill_id = r.skill_id WHERE r.activity_id = ?');
$st->execute([$a['activity_id']]);
$reqs = $st->fetchAll();
?>
<div class="notice mb-3"><i class="bi bi-info-circle"></i> <strong>RECOMMENDED ≠ AUTOMATICALLY ASSIGNED.</strong>
  The system only recommends volunteers from their profile information. It does not guarantee availability or participation. You make the final decision on the Select &amp; Assign page.</div>

<div class="card section-card"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div>
    <div class="text-muted small text-uppercase">Activity requirements</div>
    <?php foreach ($reqs as $r): ?><span class="badge text-bg-light border me-1"><?= h($r['skill_name']) ?> · <?= h($r['required_level']) ?></span><?php endforeach; ?>
    <?php if (!$reqs): ?><span class="text-muted">None yet — <a href="requirements.php?activity_id=<?= (int)$a['activity_id'] ?>">add requirements</a> for better matching.</span><?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <button id="genBtn" class="btn btn-primary"><i class="bi bi-stars"></i> Generate AI Matching</button>
    <a class="btn btn-outline-primary" href="assign.php?activity_id=<?= (int)$a['activity_id'] ?>">Select &amp; Assign <i class="bi bi-arrow-right"></i></a>
  </div>
</div></div>

<div class="small text-muted mb-3">Compatibility score (0–100): skills &amp; proficiency 60 · interests 15 · availability 10 · previous completed assignments 15.</div>
<div id="results"><div class="empty">Loading…</div></div>

<?php
$extraScript = '<script>
const activityId = ' . (int)$a['activity_id'] . ', csrf = ' . json_encode(csrf_token()) . ';
const box = document.getElementById("results");
const esc = s => String(s ?? "").replace(/[&<>"\']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;"}[c]));
function render(list, generated) {
  if (!list.length) { box.innerHTML = "<div class=\"empty\">" + (generated ? "No volunteer matched this activity yet." : "No recommendations yet. Click Generate AI Matching.") + "</div>"; return; }
  box.innerHTML = list.map(v => {
    const b = v.breakdown ? `<div class="breakdown"><span>Skills ${v.breakdown.skills}/60</span><span>Interests ${v.breakdown.interests}/15</span><span>Availability ${v.breakdown.availability}/10</span><span>Experience ${v.breakdown.experience}/15</span></div>` : "";
    const action = v.assigned ? `<span class="badge text-bg-primary">Already assigned</span>` : `<a class="btn btn-sm btn-primary" href="assign.php?activity_id=${activityId}&user_id=${v.user_id}">Select</a>`;
    return `<div class="rec-card"><div class="row align-items-center g-3">
      <div class="col-md-6"><div class="fw-semibold">${esc(v.name)}</div><div class="small text-muted">Skills: ${esc(v.skills)}</div>${b}</div>
      <div class="col-md-3"><div class="score-big">${v.score.toFixed(0)}%</div><div class="score-bar"><span style="width:${Math.min(v.score,100)}%"></span></div></div>
      <div class="col-md-3 text-md-end"><div class="mb-2"><span class="badge ${v.status === "Recommended" ? "text-bg-success" : "text-bg-secondary"}">${esc(v.status)}</span></div>${action}</div>
    </div></div>`; }).join("");
}
async function load() { const r = await fetch("api/recommendations.php?activity_id=" + activityId); const d = await r.json(); render(d.ok ? d.recommendations : [], false); if (!d.ok) box.innerHTML = "<div class=\"alert alert-danger\">" + esc(d.error) + "</div>"; }
document.getElementById("genBtn").addEventListener("click", async function () {
  this.disabled = true;
  const fd = new FormData(); fd.append("activity_id", activityId); fd.append("csrf_token", csrf);
  const r = await fetch("api/recommendations.php", { method: "POST", body: fd }); const d = await r.json();
  this.disabled = false;
  if (d.ok) render(d.recommendations, true); else box.innerHTML = "<div class=\"alert alert-danger\">" + esc(d.error) + "</div>";
});
load();
</script>';
require __DIR__ . '/includes/footer.php'; ?>
