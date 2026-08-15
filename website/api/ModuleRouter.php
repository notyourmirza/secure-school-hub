<?php
declare(strict_types=1);

namespace SchoolSaaS\Http;

use PDO;
use SchoolSaaS\Auth\Auth;
use Throwable;

final class ModuleRouter
{
    private const MODULES = [
        'teachers' => ['table' => 'teachers', 'permission' => 'teachers', 'required' => ['employee_id', 'first_name'], 'fields' => ['uuid','employee_id','first_name','last_name','phone','status']],
        'employees' => ['table' => 'employees', 'permission' => 'employees', 'required' => ['employee_code', 'first_name', 'role_title'], 'fields' => ['uuid','employee_code','first_name','last_name','role_title','phone','email','joining_date','salary','status']],
        'classes' => ['table' => 'classes', 'permission' => 'classes', 'required' => ['name'], 'fields' => ['name']],
        'subjects' => ['table' => 'subjects', 'permission' => 'subjects', 'required' => ['name'], 'fields' => ['name','code']],
        'attendance' => ['table' => 'attendance', 'permission' => 'attendance', 'required' => ['student_id','attendance_date','status'], 'fields' => ['uuid','student_id','attendance_date','status','notes','client_uuid']],
        'timetable' => ['table' => 'timetable_entries', 'permission' => 'timetable', 'required' => ['class_id','subject_id','weekday','starts_at','ends_at'], 'fields' => ['uuid','class_id','subject_id','teacher_id','weekday','starts_at','ends_at','room']],
        'fee-structures' => ['table' => 'fee_structures', 'permission' => 'fees', 'required' => ['name','amount','frequency'], 'fields' => ['name','amount','frequency','active']],
        'fees' => ['table' => 'fee_invoices', 'permission' => 'fees', 'required' => ['student_id','invoice_no','due_date','amount'], 'fields' => ['uuid','student_id','fee_structure_id','invoice_no','due_date','amount','paid_amount','status']],
        'payments' => ['table' => 'fee_payments', 'permission' => 'fees', 'required' => ['invoice_id','amount','paid_on'], 'fields' => ['uuid','invoice_id','amount','paid_on','method','reference_no']],
        'expenses' => ['table' => 'expenses', 'permission' => 'expenses', 'required' => ['category','description','amount','spent_on'], 'fields' => ['uuid','category','description','amount','spent_on','status']],
        'exams' => ['table' => 'exams', 'permission' => 'exams', 'required' => ['name','starts_on','ends_on'], 'fields' => ['name','starts_on','ends_on','status']],
        'exam-subjects' => ['table' => 'exam_subjects', 'permission' => 'exams', 'required' => ['exam_id','subject_id','max_marks','passing_marks'], 'fields' => ['exam_id','subject_id','max_marks','passing_marks','exam_date']],
        'marks' => ['table' => 'marks', 'permission' => 'marks', 'required' => ['exam_subject_id','student_id','marks'], 'fields' => ['uuid','exam_subject_id','student_id','marks','remarks']],
        'results' => ['table' => 'student_results', 'permission' => 'results', 'required' => ['exam_id','student_id'], 'fields' => ['uuid','exam_id','student_id','total_marks','percentage','grade','status']],
        'payroll-runs' => ['table' => 'payroll_runs', 'permission' => 'payroll', 'required' => ['period_start','period_end'], 'fields' => ['period_start','period_end','status']],
        'payroll-items' => ['table' => 'payroll_items', 'permission' => 'payroll', 'required' => ['payroll_run_id','employee_id','gross_amount','net_amount'], 'fields' => ['payroll_run_id','employee_id','gross_amount','deductions','net_amount']],
        'library-books' => ['table' => 'library_books', 'permission' => 'library', 'required' => ['title'], 'fields' => ['isbn','title','author','total_copies','available_copies']],
        'library-loans' => ['table' => 'library_loans', 'permission' => 'library', 'required' => ['book_id','student_id','issued_on','due_on'], 'fields' => ['uuid','book_id','student_id','issued_on','due_on','returned_on','status']],
        'transport-routes' => ['table' => 'transport_routes', 'permission' => 'transport', 'required' => ['name'], 'fields' => ['name','vehicle_no','driver_employee_id']],
        'transport-assignments' => ['table' => 'transport_assignments', 'permission' => 'transport', 'required' => ['route_id','student_id'], 'fields' => ['route_id','student_id','pickup_point']],
        'notices' => ['table' => 'notices', 'permission' => 'notices', 'required' => ['title','body'], 'fields' => ['uuid','title','body','published_at','status']],
        'reports' => ['table' => 'report_runs', 'permission' => 'reports', 'required' => ['report_type'], 'fields' => ['report_type','filters']],
    ];

    public static function dispatch(PDO $db, string $method, string $path): bool
    {
        if (preg_match('#^/api/v1/([a-z-]+)(?:/([0-9]+))?$#', $path, $matches) !== 1) return false;
        $slug = $matches[1];
        if (!isset(self::MODULES[$slug])) return false;
        $config = self::MODULES[$slug];
        $user = Auth::requirePermission($db, $config['permission'] . '.' . self::actionFor($method), null);
        $id = $matches[2] ?? null;
        $table = $config['table'];
        $schoolId = Auth::tenantId($user);

        if ($method === 'GET') {
            if ($id !== null) {
                $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = :id AND school_id = :school_id LIMIT 1");
                $stmt->execute(['id' => $id, 'school_id' => $schoolId]);
                $row = $stmt->fetch();
                if (!$row) Response::error('Record not found', 'NOT_FOUND', 404);
                Response::json(true, 'Record loaded', $row);
            }
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE school_id = :school_id ORDER BY id DESC");
            $stmt->execute(['school_id' => $schoolId]);
            Response::json(true, 'Records loaded', $stmt->fetchAll());
        }

        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) return false;
        if ($method === 'DELETE') {
            if ($id === null) Response::error('Record id is required', 'VALIDATION_ERROR', 422);
            self::deleteDomain($db, $slug, (int)$id, $schoolId);
            $stmt = $db->prepare("DELETE FROM {$table} WHERE id = :id AND school_id = :school_id");
            $stmt->execute(['id' => $id, 'school_id' => $schoolId]);
            if ($stmt->rowCount() !== 1) Response::error('Record not found', 'NOT_FOUND', 404);
            Auth::audit($db, $user, 'delete', $slug, (string)$id);
            Response::json(true, 'Record deleted');
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) Response::error('JSON object body is required', 'VALIDATION_ERROR', 422);
        $values = [];
        foreach ($config['fields'] as $field) if (array_key_exists($field, $body)) $values[$field] = $body[$field];
        if ($method === 'POST') {
            foreach ($config['required'] as $required) {
                if (!array_key_exists($required, $values) || $values[$required] === '' || $values[$required] === null) Response::error($required . ' is required', 'VALIDATION_ERROR', 422);
            }
            if (in_array('uuid', $config['fields'], true) && empty($values['uuid'])) $values['uuid'] = self::uuid();
            $values['school_id'] = $schoolId;
            if ($table === 'expenses') $values['created_by'] = (int)$user['id'];
            if ($table === 'marks') $values['entered_by'] = (int)$user['id'];
            if ($table === 'payroll_runs') $values['created_by'] = (int)$user['id'];
            if ($table === 'report_runs') { $values['generated_by'] = (int)$user['id']; $values['filters'] = json_encode($values['filters'] ?? [], JSON_THROW_ON_ERROR); }
            self::validateAndEnrich($db, $slug, $values, $schoolId);
            self::createDomain($db, $slug, $values, $schoolId);
            $columns = array_keys($values);
            $placeholders = array_map(static fn(string $field): string => ':' . $field, $columns);
            try {
                $stmt = $db->prepare("INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")");
                $stmt->execute($values);
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('Record create failed for ' . $slug . ': ' . $e->getMessage());
                Response::error('Record could not be created', 'DATABASE_CONSTRAINT', 409);
            }
            if ($db->inTransaction()) $db->commit();
            $newId = (int)$db->lastInsertId();
            self::finishDomainCreate($db, $slug, $newId, $values, $schoolId);
            Auth::audit($db, $user, 'create', $slug, (string)$newId);
            Response::json(true, 'Record created', ['id' => $newId], 201);
        }

        if ($id === null || !$values) Response::error('Record id and update fields are required', 'VALIDATION_ERROR', 422);
        self::validateAndEnrich($db, $slug, $values, $schoolId, (int)$id);
        $sets = [];
        foreach (array_keys($values) as $field) $sets[] = "$field = :$field";
        $values['id'] = $id; $values['school_id'] = $schoolId;
        try {
            $stmt = $db->prepare("UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = :id AND school_id = :school_id");
            $stmt->execute($values);
        } catch (Throwable $e) { error_log('Record update failed for ' . $slug . ': ' . $e->getMessage()); Response::error('Record could not be updated', 'DATABASE_CONSTRAINT', 409); }
        if ($stmt->rowCount() === 0) Response::error('Record not found or unchanged', 'NOT_FOUND', 404);
        Auth::audit($db, $user, 'update', $slug, (string)$id);
        Response::json(true, 'Record updated');
        return true;
    }

    private static function validateAndEnrich(PDO $db, string $slug, array &$v, int $schoolId, ?int $id = null): void
    {
        if (isset($v['amount']) && (float)$v['amount'] < 0) Response::error('Amount cannot be negative', 'VALIDATION_ERROR', 422);
        if (isset($v['salary']) && (float)$v['salary'] < 0) Response::error('Salary cannot be negative', 'VALIDATION_ERROR', 422);
        if ($slug === 'timetable' && isset($v['weekday'], $v['starts_at'], $v['ends_at'])) {
            if ((int)$v['weekday'] < 1 || (int)$v['weekday'] > 7 || $v['starts_at'] >= $v['ends_at']) Response::error('Timetable day or time range is invalid', 'VALIDATION_ERROR', 422);
            if (isset($v['class_id'])) {
                $overlap = $db->prepare('SELECT id FROM timetable_entries WHERE school_id = :school_id AND weekday = :weekday AND starts_at < :ends_at AND ends_at > :starts_at AND (class_id = :class_id OR (teacher_id IS NOT NULL AND teacher_id = :teacher_id))' . ($id !== null ? ' AND id <> :id' : '') . ' LIMIT 1');
                $params = ['school_id' => $schoolId, 'weekday' => $v['weekday'], 'ends_at' => $v['ends_at'], 'starts_at' => $v['starts_at'], 'class_id' => $v['class_id'], 'teacher_id' => $v['teacher_id'] ?? 0];
                if ($id !== null) $params['id'] = $id;
                $overlap->execute($params);
                if ($overlap->fetchColumn()) Response::error('Timetable entry overlaps an existing class or teacher schedule', 'BUSINESS_RULE_ERROR', 422);
            }
        }
        if ($slug === 'library-loans' && isset($v['issued_on'], $v['due_on']) && $v['due_on'] < $v['issued_on']) Response::error('Loan due date must not precede issue date', 'VALIDATION_ERROR', 422);
        if ($slug === 'payroll-items' && isset($v['gross_amount'], $v['deductions'], $v['net_amount'])) {
            if ((float)$v['deductions'] < 0 || (float)$v['net_amount'] < 0) Response::error('Payroll amounts cannot be negative', 'VALIDATION_ERROR', 422);
            $expectedNet = round((float)$v['gross_amount'] - (float)$v['deductions'], 2);
            if (abs($expectedNet - (float)$v['net_amount']) > 0.009) Response::error('Net amount must equal gross amount minus deductions', 'BUSINESS_RULE_ERROR', 422);
        }
        if ($slug === 'exams' && isset($v['starts_on'], $v['ends_on']) && $v['starts_on'] > $v['ends_on']) Response::error('Exam end date must not precede start date', 'VALIDATION_ERROR', 422);
        if ($slug === 'exam-subjects' && isset($v['max_marks'], $v['passing_marks']) && ((float)$v['max_marks'] <= 0 || (float)$v['passing_marks'] < 0 || (float)$v['passing_marks'] > (float)$v['max_marks'])) Response::error('Marks range is invalid', 'VALIDATION_ERROR', 422);
        if ($slug === 'marks') {
            $q = $db->prepare('SELECT max_marks FROM exam_subjects WHERE id = :id AND school_id = :school_id'); $q->execute(['id' => $v['exam_subject_id'], 'school_id' => $schoolId]); $max = $q->fetchColumn();
            if ($max === false) Response::error('Exam subject is not in this school', 'TENANT_REFERENCE_ERROR', 422);
            if ((float)$v['marks'] < 0 || (float)$v['marks'] > (float)$max) Response::error('Marks exceed the permitted range', 'VALIDATION_ERROR', 422);
        }
        if ($slug === 'payroll-items' && (float)$v['net_amount'] > (float)$v['gross_amount']) Response::error('Net amount cannot exceed gross amount', 'VALIDATION_ERROR', 422);
        if ($slug === 'reports' && !in_array($v['report_type'], ['attendance','fees','expenses','results','payroll','enrollment'], true)) Response::error('Unsupported report type', 'VALIDATION_ERROR', 422);
        self::assertReferences($db, $slug, $v, $schoolId);
    }

    private static function assertReferences(PDO $db, string $slug, array $v, int $schoolId): void
    {
        $refs = match ($slug) {
            'timetable' => [['classes','class_id'],['subjects','subject_id']],
            'fees' => [['students','student_id']], 'payments' => [['fee_invoices','invoice_id']],
            'exam-subjects' => [['exams','exam_id'],['subjects','subject_id']], 'marks' => [['exam_subjects','exam_subject_id'],['students','student_id']],
            'results' => [['exams','exam_id'],['students','student_id']], 'payroll-items' => [['payroll_runs','payroll_run_id'],['employees','employee_id']],
            'library-loans' => [['library_books','book_id'],['students','student_id']], 'transport-assignments' => [['transport_routes','route_id'],['students','student_id']], default => []
        };
        foreach ($refs as [$table, $field]) {
            if (!array_key_exists($field, $v)) continue;
            $q = $db->prepare("SELECT id FROM {$table} WHERE id = :id AND school_id = :school_id LIMIT 1"); $q->execute(['id' => $v[$field], 'school_id' => $schoolId]);
            if (!$q->fetchColumn()) Response::error($field . ' does not belong to this school', 'TENANT_REFERENCE_ERROR', 422);
        }
    }

    private static function createDomain(PDO $db, string $slug, array &$v, int $schoolId): void
    {
        if ($slug === 'payments') {
            $q = $db->prepare('SELECT amount, paid_amount, status FROM fee_invoices WHERE id = :id AND school_id = :school_id FOR UPDATE'); $db->beginTransaction(); $q->execute(['id' => $v['invoice_id'], 'school_id' => $schoolId]); $invoice = $q->fetch();
            if (!$invoice || $invoice['status'] === 'void' || (float)$v['amount'] <= 0 || (float)$v['amount'] > ((float)$invoice['amount'] - (float)$invoice['paid_amount'])) { $db->rollBack(); Response::error('Payment exceeds the invoice balance', 'BUSINESS_RULE_ERROR', 422); }
            $v['uuid'] = $v['uuid'] ?? self::uuid();
        }
        if ($slug === 'library-loans') {
            $db->beginTransaction(); $q = $db->prepare('SELECT available_copies FROM library_books WHERE id = :id AND school_id = :school_id FOR UPDATE'); $q->execute(['id' => $v['book_id'], 'school_id' => $schoolId]);
            if ((int)$q->fetchColumn() < 1) { $db->rollBack(); Response::error('No available copy for this book', 'BUSINESS_RULE_ERROR', 422); }
            $v['uuid'] = $v['uuid'] ?? self::uuid();
        }
        if ($slug === 'results') {
            $v['uuid'] = $v['uuid'] ?? self::uuid();
            $q = $db->prepare('SELECT COALESCE(SUM(m.marks),0), COALESCE(SUM(es.max_marks),0) FROM marks m JOIN exam_subjects es ON es.id=m.exam_subject_id WHERE m.school_id=:school_id AND es.exam_id=:exam_id AND m.student_id=:student_id'); $q->execute(['school_id'=>$schoolId,'exam_id'=>$v['exam_id'],'student_id'=>$v['student_id']]); [$total,$max] = $q->fetch(PDO::FETCH_NUM); $v['total_marks'] = $total; $v['percentage'] = $max > 0 ? round($total * 100 / $max, 2) : 0; $v['grade'] = self::grade((float)$v['percentage']);
        }
    }

    private static function finishDomainCreate(PDO $db, string $slug, int $id, array $v, int $schoolId): void
    {
        if ($slug === 'payments') {
            $q = $db->prepare('UPDATE fee_invoices SET paid_amount = paid_amount + :amount, status = CASE WHEN paid_amount + :amount >= amount THEN "paid" ELSE "partial" END WHERE id = :id AND school_id = :school_id'); $q->execute(['amount'=>$v['amount'],'id'=>$v['invoice_id'],'school_id'=>$schoolId]);
        }
        if ($slug === 'library-loans') {
            $q = $db->prepare('UPDATE library_books SET available_copies = available_copies - 1 WHERE id = :id AND school_id = :school_id'); $q->execute(['id'=>$v['book_id'],'school_id'=>$schoolId]);
        }
    }

    private static function deleteDomain(PDO $db, string $slug, int $id, int $schoolId): void
    {
        if ($slug === 'library-loans') {
            $q = $db->prepare('SELECT book_id, status FROM library_loans WHERE id=:id AND school_id=:school_id'); $q->execute(['id'=>$id,'school_id'=>$schoolId]); $loan=$q->fetch();
            if ($loan && $loan['status'] === 'issued') { $u=$db->prepare('UPDATE library_books SET available_copies=available_copies+1 WHERE id=:id AND school_id=:school_id'); $u->execute(['id'=>$loan['book_id'],'school_id'=>$schoolId]); }
        }
    }

    private static function grade(float $percentage): string
    {
        return $percentage >= 80 ? 'A' : ($percentage >= 70 ? 'B' : ($percentage >= 60 ? 'C' : ($percentage >= 50 ? 'D' : 'F')));
    }

    private static function actionFor(string $method): string { return match ($method) { 'GET' => 'view', 'POST' => 'create', 'PUT', 'PATCH' => 'update', 'DELETE' => 'delete', default => 'view' }; }
    private static function uuid(): string { $data=random_bytes(16); $data[6]=chr((ord($data[6])&0x0f)|0x40); $data[8]=chr((ord($data[8])&0x3f)|0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data),4)); }
}
