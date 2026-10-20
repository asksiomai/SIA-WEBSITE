<?php
// AI-Based Volunteer Matching: simple, transparent, rule-based compatibility score (0-100).
//   Skills + proficiency  = 60 points
//   Interests             = 15 points
//   Availability          = 10 points
//   Previous experience   = 15 points (completed assignments)
// This weighting is our own implementation decision; the proposal does not define a formula.

function level_rank($level) {
    $map = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
    return $map[strtolower(trim((string)$level))] ?? 1;
}

function availability_points($availability, $date) {
    $a = strtolower(trim((string)$availability));
    if ($a === '') return 0;
    if (preg_match('/any ?time|any day|every ?day|daily|flexible|all days|always/', $a)) return 10;
    if (!$date) return 5;                       // volunteer gave availability, activity has no date
    $day = strtolower(date('l', strtotime($date)));
    $weekend = in_array($day, ['saturday', 'sunday'], true);
    if (strpos($a, $day) !== false) return 10;
    if (preg_match('/\b' . substr($day, 0, 3) . '\b/', $a)) return 10;
    if ($weekend && strpos($a, 'weekend') !== false) return 10;
    if (!$weekend && strpos($a, 'weekday') !== false) return 10;
    return 0;
}

function interest_points($interests, $activityText) {
    $matched = 0;
    foreach (preg_split('/[,;\n]+/', strtolower((string)$interests)) as $i) {
        $i = trim($i);
        if ($i === '') continue;
        $hit = strpos($activityText, $i) !== false;
        if (!$hit) {
            foreach (preg_split('/\s+/', $i) as $w) {
                if (strlen($w) >= 4 && strpos($activityText, $w) !== false) { $hit = true; break; }
            }
        }
        if ($hit) $matched++;
    }
    return $matched >= 2 ? 15 : ($matched === 1 ? 10 : 0);
}

// Calculates and stores recommendations for one activity. Returns the score breakdown per volunteer.
function generate_recommendations(PDO $pdo, array $activity) {
    $aid = (int)$activity['activity_id'];

    $st = $pdo->prepare('SELECT r.skill_id, r.required_level, s.skill_name FROM activity_requirements r JOIN skills s ON s.skill_id = r.skill_id WHERE r.activity_id = ?');
    $st->execute([$aid]);
    $reqs = $st->fetchAll();

    $activityText = strtolower($activity['activity_name'] . ' ' . $activity['description'] . ' ' . implode(' ', array_column($reqs, 'skill_name')));

    $skillMap = [];
    foreach ($pdo->query('SELECT user_id, skill_id, proficiency_level FROM volunteer_skills') as $r) {
        $skillMap[$r['user_id']][$r['skill_id']] = $r['proficiency_level'];
    }
    $experience = [];
    foreach ($pdo->query("SELECT user_id, COUNT(*) AS c FROM assignments WHERE assignment_status = 'Completed' GROUP BY user_id") as $r) {
        $experience[$r['user_id']] = (int)$r['c'];
    }

    $result = [];
    $pdo->beginTransaction();
    foreach ($pdo->query("SELECT user_id, interests, availability FROM users WHERE role = 'volunteer'")->fetchAll() as $v) {
        $uid = (int)$v['user_id'];

        $skills = 0;
        if ($reqs) {
            $each = 60 / count($reqs);
            foreach ($reqs as $r) {
                if (isset($skillMap[$uid][$r['skill_id']])) {
                    $ratio = min(level_rank($skillMap[$uid][$r['skill_id']]) / level_rank($r['required_level']), 1);
                    $skills += $each * (0.5 + 0.5 * $ratio);   // has the skill = 50%, meets the level = 100%
                }
            }
        }
        $interests = interest_points($v['interests'], $activityText);
        $availability = availability_points($v['availability'], $activity['activity_date']);
        $exp = min($experience[$uid] ?? 0, 3) / 3 * 15;          // 3+ completed assignments = full 15
        $score = round($skills + $interests + $availability + $exp, 2);

        $result[$uid] = ['skills' => round($skills, 2), 'interests' => $interests, 'availability' => $availability, 'experience' => round($exp, 2)];

        $ex = $pdo->prepare('SELECT recommendation_id FROM recommendations WHERE activity_id = ? AND user_id = ?');
        $ex->execute([$aid, $uid]);
        $rid = $ex->fetchColumn();
        if ($score > 0 && $rid) {
            $pdo->prepare('UPDATE recommendations SET compatibility_score = ?, recommendation_date = CURDATE() WHERE recommendation_id = ?')->execute([$score, $rid]);
        } elseif ($score > 0) {
            $pdo->prepare('INSERT INTO recommendations (activity_id, user_id, compatibility_score, recommendation_date, is_selected) VALUES (?, ?, ?, CURDATE(), 0)')->execute([$aid, $uid, $score]);
        } elseif ($rid) {
            $pdo->prepare('DELETE FROM recommendations WHERE recommendation_id = ? AND is_selected = 0')->execute([$rid]);
        }
    }
    $pdo->commit();
    return $result;
}

// Stored recommendations for an activity (highest score first).
function get_recommendations(PDO $pdo, $activity_id, array $breakdown = []) {
    $st = $pdo->prepare(
        "SELECT r.recommendation_id, r.user_id, r.compatibility_score, r.recommendation_date, r.is_selected,
                u.first_name, u.middle_name, u.last_name, u.interests, u.availability,
                (SELECT GROUP_CONCAT(CONCAT(s.skill_name, ' - ', vs.proficiency_level) SEPARATOR ', ')
                   FROM volunteer_skills vs JOIN skills s ON s.skill_id = vs.skill_id WHERE vs.user_id = u.user_id) AS skills,
                (SELECT COUNT(*) FROM assignments a WHERE a.activity_id = r.activity_id AND a.user_id = r.user_id
                    AND a.assignment_status IN ('Assigned','Completed')) AS assigned
           FROM recommendations r JOIN users u ON u.user_id = r.user_id
          WHERE r.activity_id = ? AND u.role = 'volunteer'
          ORDER BY r.compatibility_score DESC, u.last_name"
    );
    $st->execute([$activity_id]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $score = (float)$r['compatibility_score'];
        $rows[] = [
            'user_id'    => (int)$r['user_id'],
            'name'       => full_name($r),
            'skills'     => $r['skills'] ?: 'No skills listed',
            'interests'  => $r['interests'],
            'availability' => $r['availability'],
            'score'      => $score,
            'status'     => $score >= 50 ? 'Recommended' : 'Low match',
            'is_selected'=> (int)$r['is_selected'],
            'assigned'   => (int)$r['assigned'] > 0,
            'date'       => $r['recommendation_date'],
            'breakdown'  => $breakdown[(int)$r['user_id']] ?? null,
        ];
    }
    return $rows;
}
