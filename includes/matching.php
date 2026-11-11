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

// Requirements of one activity: a list of [skill_id, required_level, skill_name].
function activity_requirements(PDO $pdo, $activity_id) {
    $st = $pdo->prepare('SELECT r.skill_id, r.required_level, s.skill_name FROM activity_requirements r JOIN skills s ON s.skill_id = r.skill_id WHERE r.activity_id = ?');
    $st->execute([(int)$activity_id]);
    return $st->fetchAll();
}

// Text of an activity (name + description + required skills) that interests are compared with.
function activity_match_text(array $activity, array $reqs) {
    return strtolower($activity['activity_name'] . ' ' . $activity['description'] . ' ' . implode(' ', array_column($reqs, 'skill_name')));
}

// THE scoring formula (one place, used for organizers and for volunteers).
//   $volunteer needs: user_id, interests, availability
//   $skillMap    = [user_id => [skill_id => proficiency_level]]
//   $experience  = [user_id => number of completed assignments]
function score_volunteer(array $volunteer, array $activity, array $reqs, array $skillMap, array $experience, $activityText) {
    $uid = (int)$volunteer['user_id'];

    $skills = 0;
    $matched = [];      // required skills the volunteer has (with both levels)
    $missing = [];      // required skills the volunteer does not have
    if ($reqs) {
        $each = 60 / count($reqs);
        foreach ($reqs as $r) {
            if (isset($skillMap[$uid][$r['skill_id']])) {
                $mine = $skillMap[$uid][$r['skill_id']];
                $ratio = min(level_rank($mine) / level_rank($r['required_level']), 1);
                $skills += $each * (0.5 + 0.5 * $ratio);   // has the skill = 50%, meets the level = 100%
                $matched[] = ['skill_name' => $r['skill_name'], 'yours' => $mine, 'required' => $r['required_level'], 'meets' => $ratio >= 1];
            } else {
                $missing[] = $r['skill_name'];
            }
        }
    }
    $interests = interest_points($volunteer['interests'], $activityText);
    $availability = availability_points($volunteer['availability'], $activity['activity_date']);
    $done = $experience[$uid] ?? 0;
    $exp = min($done, 3) / 3 * 15;                           // 3+ completed assignments = full 15

    return [
        'score'        => round($skills + $interests + $availability + $exp, 2),
        'skills'       => round($skills, 2),
        'interests'    => $interests,
        'availability' => $availability,
        'experience'   => round($exp, 2),
        'completed'    => $done,
        'matched'      => $matched,
        'missing'      => $missing,
    ];
}

// Calculates and stores recommendations for one activity (organizer side). Returns the score breakdown per volunteer.
function generate_recommendations(PDO $pdo, array $activity) {
    $aid = (int)$activity['activity_id'];

    $reqs = activity_requirements($pdo, $aid);
    $activityText = activity_match_text($activity, $reqs);

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

        $s = score_volunteer($v, $activity, $reqs, $skillMap, $experience, $activityText);
        $score = $s['score'];

        $result[$uid] = ['skills' => $s['skills'], 'interests' => $s['interests'], 'availability' => $s['availability'], 'experience' => $s['experience']];

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

// Plain-language reasons why an activity fits a volunteer (shown on the volunteer's AI Recommendations page).
function recommendation_reasons(array $s) {
    $why = [];
    foreach ($s['matched'] as $m) {
        $why[] = $m['meets']
            ? 'You have ' . $m['skill_name'] . ' (' . $m['yours'] . '), which meets the required ' . $m['required'] . ' level.'
            : 'You have ' . $m['skill_name'] . ' (' . $m['yours'] . '); the activity asks for ' . $m['required'] . ', so this is a partial match.';
    }
    if ($s['missing'])
        $why[] = 'Not in your skills yet: ' . implode(', ', $s['missing']) . '.';
    if ($s['interests'] > 0)
        $why[] = 'It fits your listed interests.';
    if ($s['availability'] >= 10)
        $why[] = 'Your availability fits the activity date.';
    if ($s['completed'] > 0)
        $why[] = 'You have completed ' . $s['completed'] . ($s['completed'] === 1 ? ' activity' : ' activities') . ' before.';
    return $why;
}

// Volunteer side: suggests upcoming activities for ONE volunteer, using the same score_volunteer() formula.
// READ ONLY: nothing is written to the database and nobody is assigned. Left out: finished activities (end date passed), full activities,
// activities the volunteer is already assigned to / has completed, and activities that need none of the volunteer's skills.
function recommend_activities_for_volunteer(PDO $pdo, array $volunteer) {
    $uid = (int)$volunteer['user_id'];

    // Last day of an activity: end_date when the column exists (migration 001), otherwise the single activity_date.
    $lastDay = db_has_column($pdo, 'activities', 'end_date') ? 'COALESCE(a.end_date, a.activity_date)' : 'a.activity_date';
    $st = $pdo->prepare("SELECT a.*,
            (SELECT COUNT(*) FROM assignments s WHERE s.activity_id = a.activity_id AND s.assignment_status IN ('Assigned','Completed')) AS taken
          FROM activities a
         WHERE $lastDay >= ?
           AND NOT EXISTS (SELECT 1 FROM assignments m WHERE m.activity_id = a.activity_id AND m.user_id = ? AND m.assignment_status IN ('Assigned','Completed'))
         ORDER BY a.activity_date, a.activity_id");
    $st->execute([date('Y-m-d'), $uid]);
    $activities = array_values(array_filter($st->fetchAll(), fn($a) => (int)$a['taken'] < (int)$a['max_volunteers']));

    $skillMap = [$uid => []];
    $st = $pdo->prepare('SELECT skill_id, proficiency_level FROM volunteer_skills WHERE user_id = ?');
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $r) {
        $skillMap[$uid][$r['skill_id']] = $r['proficiency_level'];
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = ? AND assignment_status = 'Completed'");
    $st->execute([$uid]);
    $experience = [$uid => (int)$st->fetchColumn()];

    $reqsBy = [];
    if ($activities) {
        $ids = implode(',', array_map(fn($a) => (int)$a['activity_id'], $activities));   // integers from our own query
        foreach ($pdo->query("SELECT r.activity_id, r.skill_id, r.required_level, s.skill_name
                                FROM activity_requirements r JOIN skills s ON s.skill_id = r.skill_id
                               WHERE r.activity_id IN ($ids) ORDER BY s.skill_name") as $r) {
            $reqsBy[$r['activity_id']][] = $r;
        }
    }

    $out = [];
    foreach ($activities as $a) {
        $reqs = $reqsBy[$a['activity_id']] ?? [];
        $s = score_volunteer($volunteer, $a, $reqs, $skillMap, $experience, activity_match_text($a, $reqs));
        if ($s['skills'] <= 0) continue;                     // none of the required skills is in the volunteer's profile
        $out[] = [
            'activity_id'    => (int)$a['activity_id'],
            'activity_name'  => $a['activity_name'],
            'description'    => $a['description'],
            'activity_date'  => $a['activity_date'],
            'end_date'       => $a['end_date'] ?? null,
            'start_time'     => !empty($a['start_time']) ? substr($a['start_time'], 0, 5) : null,
            'end_time'       => !empty($a['end_time']) ? substr($a['end_time'], 0, 5) : null,
            'location'       => $a['location'],
            'max_volunteers' => (int)$a['max_volunteers'],
            'taken'          => (int)$a['taken'],
            'requirements'   => array_map(fn($r) => ['skill_name' => $r['skill_name'], 'required_level' => $r['required_level']], $reqs),
            'score'          => $s['score'],
            'status'         => $s['score'] >= 50 ? 'Recommended' : 'Low match',
            'breakdown'      => ['skills' => $s['skills'], 'interests' => $s['interests'], 'availability' => $s['availability'], 'experience' => $s['experience']],
            'reasons'        => recommendation_reasons($s),
        ];
    }
    usort($out, fn($x, $y) => $y['score'] <=> $x['score'] ?: strcmp((string)$x['activity_date'], (string)$y['activity_date']));
    return ['recommendations' => $out, 'skills_count' => count($skillMap[$uid])];
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
