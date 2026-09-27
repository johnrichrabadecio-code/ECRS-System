<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$config = require __DIR__ . '/../config/config.php';
function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo === null) {
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path=''): string {
    global $config;
    return rtrim($config['base_url'],'/') . '/' . ltrim($path,'/');
}
function redirect(string $url): never { header('Location: '.$url); exit; }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $token=$_POST['csrf'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) { http_response_code(419); exit('Invalid CSRF token.'); }
    }
}
function flash(string $type, string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function pull_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function require_student(): void {
    if (empty($_SESSION['student_id'])) redirect(base_url('login.php'));
}
function require_admin(): void {
    if (empty($_SESSION['admin_id'])) redirect(base_url('admin/login.php'));
}
function student_record(): array {
    require_student();
    $s=db()->prepare('SELECT * FROM students WHERE id=? AND is_active=1'); $s->execute([$_SESSION['student_id']]);
    $row=$s->fetch(); if(!$row){ session_destroy(); redirect(base_url('login.php')); } return $row;
}
function latest_exam(int $studentId): ?array {
    $s=db()->prepare('SELECT * FROM exams WHERE student_id=? ORDER BY exam_date DESC,id DESC LIMIT 1'); $s->execute([$studentId]);
    return $s->fetch() ?: null;
}
function exam_scores(int $examId): array {
    $s=db()->prepare('SELECT sub.id,sub.name,es.score FROM subjects sub LEFT JOIN exam_scores es ON es.subject_id=sub.id AND es.exam_id=? ORDER BY sub.sort_order, sub.id');
    $s->execute([$examId]); return $s->fetchAll();
}
function recommendation_rows(int $studentId): array {
    $exam=latest_exam($studentId); if(!$exam) return [];
    $sql='SELECT p.id,p.code,p.name,p.description,p.study_track_id,st.name AS study_track,
          SUM(es.score * psw.weight) / NULLIF(SUM(psw.weight),0) AS fit_score
          FROM programs p JOIN study_tracks st ON st.id=p.study_track_id
          JOIN program_subject_weights psw ON psw.program_id=p.id AND psw.weight>0
          JOIN exam_scores es ON es.subject_id=psw.subject_id AND es.exam_id=?
          GROUP BY p.id,p.code,p.name,p.description,p.study_track_id,st.name
          HAVING SUM(psw.weight)>0 ORDER BY fit_score DESC,p.name ASC';
    $s=db()->prepare($sql); $s->execute([$exam['id']]); return $s->fetchAll();
}
function program_breakdown(int $studentId,int $programId): array {
    $exam=latest_exam($studentId); if(!$exam) return [];
    $s=db()->prepare('SELECT sub.name,es.score,psw.weight,(es.score*psw.weight) AS contribution
        FROM program_subject_weights psw JOIN subjects sub ON sub.id=psw.subject_id
        LEFT JOIN exam_scores es ON es.subject_id=sub.id AND es.exam_id=?
        WHERE psw.program_id=? AND psw.weight>0 ORDER BY psw.weight DESC, sub.name');
    $s->execute([$exam['id'],$programId]); return $s->fetchAll();
}
function score_label(float $v): array {
    if ($v>=90) return ['Excellent','green'];
    if ($v>=80) return ['Strong','blue'];
    if ($v>=70) return ['Good','amber'];
    return ['Needs Review','red'];
}
