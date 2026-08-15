<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';

use SchoolSaaS\Database\Database;

/**
 * Generic tenant-scoped CRUD screen.
 * Table names, column names and writable fields all come from this fixed
 * server-side allowlist - never from the request.
 */
$configs = [
 'students' => ['title' => 'Students', 'table' => 'students', 'permission' => 'students', 'columns' => ['admission_no','first_name','last_name','gender','phone','status'], 'fields' => ['admission_no','first_name','last_name','gender','phone','address']],
 'teachers' => ['title' => 'Teachers', 'table' => 'teachers', 'permission' => 'teachers', 'columns' => ['employee_id','first_name','last_name','email','phone','status'], 'fields' => ['employee_id','first_name','last_name','email','phone','qualification','joining_date']],
 'classes' => ['title' => 'Classes', 'table' => 'classes', 'permission' => 'classes', 'columns' => ['name','academic_year_id','status'], 'fields' => ['name','academic_year_id','status']],
 'subjects' => ['title' => 'Subjects', 'table' => 'subjects', 'permission' => 'subjects', 'columns' => ['name','code','status'], 'fields' => ['name','code','status']],
 'attendance' => ['title' => 'Attendance', 'table' => 'attendance', 'permission' => 'attendance', 'columns' => ['student_id','attendance_date','status','notes'], 'fields' => ['student_id','attendance_date','status','notes']],
 'exams' => ['title' => 'Exams', 'table' => 'exams', 'permission' => 'exams', 'columns' => ['name','starts_on','ends_on','status'], 'fields' => ['name','starts_on','ends_on','status']],
 'fees' => ['title' => 'Fees & Payments', 'table' => 'fee_invoices', 'permission' => 'fees', 'columns' => ['invoice_no','student_id','due_date','amount','paid_amount','status'], 'fields' => ['invoice_no','student_id','due_date','amount','paid_amount','status']],
 'admissions' => ['title' => 'Admissions', 'table' => 'admissions', 'permission' => 'admissions', 'columns' => ['application_no','applicant_name','status','submitted_at'], 'fields' => ['application_no','applicant_name','phone','email','status','notes']],
 'notices' => ['title' => 'Notices', 'table' => 'notices', 'permission' => 'notices', 'columns' => ['title','published_at','status'], 'fields' => ['title','body','published_at','status']],
 'reports' => ['title' => 'Reports', 'table' => 'report_runs', 'permission' => 'reports', 'columns' => ['report_type','status','created_at'], 'fields' => ['report_type','filters']],
];

/** Tables carrying a deleted_at column (see migrations 001 and 007). */
$softDeleteTables = ['students','teachers','classes','subjects','notices','admissions'];

$slug = $module_slug ?? 'students';
if (!isset($configs[$slug])) {
    http_response_code(404);
    exit('Unknown module');
}
$cfg = $configs[$slug];

// Authorization: the caller must actually hold the module permission.
$user = require_permission($cfg['permission'] . '.view');
$schoolId = tenant_id();
$supportsSoftDelete = in_array($cfg['table'], $softDeleteTables, true);

$search = clamp_text($_GET['q'] ?? '', MAX_SEARCH_LENGTH);
$page = min(1000, max(1, (int)($_GET['page'] ?? 1)));
$perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['_action'] ?? '');

    if ($action === 'delete') {
        require_permission($cfg['permission'] . '.delete');
        $id = (int)($_POST['id'] ?? 0);
        if (!$supportsSoftDelete) {
            flash('error', 'This record type does not support archiving.');
            redirect($slug . '.php');
        }
        try {
            $stmt = db()->prepare('UPDATE ' . $cfg['table'] . ' SET deleted_at = NOW() WHERE id = :id AND school_id = :school_id AND deleted_at IS NULL');
            $stmt->execute(['id' => $id, 'school_id' => $schoolId]);
            if ($stmt->rowCount() === 1) {
                audit('archive', $slug, (string)$id);
                flash('success', 'Record archived.');
            } else {
                flash('error', 'Record not found in this workspace.');
            }
        } catch (Throwable $e) {
            error_log('Archive failed: ' . $e->getMessage());
            flash('error', 'This record could not be archived.');
        }
        redirect($slug . '.php');
    }

    if ($action === 'create') {
        require_permission($cfg['permission'] . '.create');
        if ($slug === 'students') {
            require_plan_limit('students', 'students');
        }
        // Explicit field mapping: no mass assignment from $_POST.
        $fields = [];
        $values = [];
        foreach ($cfg['fields'] as $field) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $limit = match (true) {
                in_array($field, ['body','notes','address','filters'], true) => MAX_TEXT_LENGTH,
                $field === 'email' => MAX_EMAIL_LENGTH,
                $field === 'phone' => MAX_PHONE_LENGTH,
                default => MAX_NAME_LENGTH,
            };
            $value = clamp_text($_POST[$field], $limit);
            if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
                redirect($slug . '.php');
            }
            if ((str_contains($field, 'date') || str_ends_with($field, '_on')) && $value !== '' && valid_date($value) === null) {
                flash('error', 'Enter dates in YYYY-MM-DD format.');
                redirect($slug . '.php');
            }
            $fields[] = $field;
            $values[$field] = $value === '' ? null : $value;
        }
        $fields[] = 'school_id';
        $values['school_id'] = $schoolId;
        try {
            $placeholders = array_map(static fn (string $f): string => ':' . $f, $fields);
            $stmt = db()->prepare('INSERT INTO ' . $cfg['table'] . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')');
            $stmt->execute($values);
            audit('create', $slug, (string)db()->lastInsertId());
            flash('success', $cfg['title'] . ' record created.');
        } catch (Throwable $e) {
            error_log('Create failed for ' . $slug . ': ' . $e->getMessage());
            flash('error', 'Record could not be created. Check required fields and duplicate values.');
        }
    }
    redirect($slug . '.php');
}

$where = 'school_id = :school_id';
$params = ['school_id' => $schoolId];
if ($supportsSoftDelete) {
    $where .= ' AND deleted_at IS NULL';
}
if ($search !== '') {
    $like = [];
    foreach ($cfg['columns'] as $col) {
        $like[] = $col . " LIKE :search ESCAPE '\\\\'";
    }
    $where .= ' AND (' . implode(' OR ', $like) . ')';
    $params['search'] = Database::likeParam($search);
}
$stmt = db()->prepare('SELECT id,' . implode(',', $cfg['columns']) . ' FROM ' . $cfg['table'] . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$stmt->execute($params);
$rows = $stmt->fetchAll();

admin_header($cfg['title']);
?><div class="content"><div class="panel"><div class="panel-head"><h2>Manage <?= e(strtolower($cfg['title'])) ?></h2><a class="btn" href="#create">Add record</a></div><form method="get" class="actions"><input name="q" maxlength="<?= MAX_SEARCH_LENGTH ?>" value="<?= e($search) ?>" placeholder="Search records"><button class="btn secondary">Search</button></form><div class="table-wrap"><table><thead><tr><?php foreach ($cfg['columns'] as $col): ?><th><?= e(ucwords(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($cfg['columns'] as $col): ?><td><?= e($row[$col] ?? '') ?></td><?php endforeach; ?><td><?php if ($supportsSoftDelete): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= e($row['id'] ?? '') ?>"><button class="btn secondary" type="submit">Archive</button></form><?php endif; ?></td></tr><?php endforeach; if (!$rows): ?><tr><td class="empty" colspan="<?= count($cfg['columns']) + 1 ?>">No records yet. Add the first one below.</td></tr><?php endif; ?></tbody></table></div></div><div class="panel" id="create"><div class="panel-head"><h2>Create record</h2></div><form class="grid" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_action" value="create"><?php foreach ($cfg['fields'] as $field): ?><div class="field <?= in_array($field, ['body','notes','address'], true) ? 'full' : '' ?>"><label><?= e(ucwords(str_replace('_', ' ', $field))) ?></label><?php if (in_array($field, ['body','notes','address','filters'], true)): ?><textarea name="<?= e($field) ?>" rows="3" maxlength="<?= MAX_TEXT_LENGTH ?>"></textarea><?php else: ?><input name="<?= e($field) ?>" maxlength="<?= MAX_NAME_LENGTH ?>" type="<?= str_contains($field, 'date') || str_ends_with($field, '_on') ? 'date' : 'text' ?>" <?= in_array($field, ['admission_no','first_name','name','title','application_no','applicant_name'], true) ? 'required' : '' ?>><?php endif; ?></div><?php endforeach; ?><div class="actions"><button class="btn" type="submit">Save record</button></div></form></div></div><?php admin_footer();
