<?php
$config = require __DIR__.'/config/config.php';
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
try{
$dsn="mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4";$pdo=new PDO($dsn,$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db=preg_replace('/[^a-zA-Z0-9_]/','',$config['db_name']);$pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$db`");
$schema=[
"CREATE TABLE IF NOT EXISTS admins(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) UNIQUE NOT NULL,password_hash VARCHAR(255) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS students(id INT AUTO_INCREMENT PRIMARY KEY,student_no VARCHAR(50) UNIQUE NOT NULL,full_name VARCHAR(190) NOT NULL,email VARCHAR(190) UNIQUE NOT NULL,contact_number VARCHAR(60) DEFAULT '',applicant_status VARCHAR(100) DEFAULT 'Incoming Freshman',academic_year VARCHAR(50) DEFAULT '',password_hash VARCHAR(255) NOT NULL,is_active TINYINT(1) DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS study_tracks(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(190) UNIQUE NOT NULL,description TEXT) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS subjects(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(190) UNIQUE NOT NULL,description TEXT,sort_order INT DEFAULT 0) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS programs(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(50) UNIQUE NOT NULL,name VARCHAR(190) UNIQUE NOT NULL,description TEXT,study_track_id INT NOT NULL,FOREIGN KEY(study_track_id) REFERENCES study_tracks(id)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS program_subject_weights(program_id INT NOT NULL,subject_id INT NOT NULL,weight DECIMAL(8,2) NOT NULL DEFAULT 0,PRIMARY KEY(program_id,subject_id),FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE,FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS exams(id INT AUTO_INCREMENT PRIMARY KEY,student_id INT NOT NULL,exam_name VARCHAR(190) NOT NULL,exam_date DATE NOT NULL,status VARCHAR(50) DEFAULT 'Completed',percentile VARCHAR(50) NULL,FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS exam_scores(exam_id INT NOT NULL,subject_id INT NOT NULL,score DECIMAL(5,2) NOT NULL,PRIMARY KEY(exam_id,subject_id),FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE,CHECK(score>=0 AND score<=100)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS recommendations(id INT AUTO_INCREMENT PRIMARY KEY,student_id INT NOT NULL,program_id INT NOT NULL,fit_score DECIMAL(5,2) NOT NULL,rank_no INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS settings(student_id INT PRIMARY KEY,email_notifications TINYINT(1) DEFAULT 1,recommendation_updates TINYINT(1) DEFAULT 1,FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE) ENGINE=InnoDB"
];foreach($schema as $sql)$pdo->exec($sql);
$pdo->prepare('INSERT IGNORE INTO admins(email,password_hash) VALUES(?,?)')->execute(['admin@ecrs.local',password_hash('Admin123!',PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT IGNORE INTO students(student_no,full_name,email,contact_number,applicant_status,academic_year,password_hash) VALUES(?,?,?,?,?,?,?)')->execute(['2026-00451','Maria Santos','maria.santos@email.com','+63 912 345 6789','Incoming Freshman','AY 2026–2027',password_hash('Student123!',PASSWORD_DEFAULT)]);
$tracks=['Health and Biomedical Sciences','Exact Sciences and Engineering','Social Sciences and Humanities','Actuarial and Business Sciences'];$ins=$pdo->prepare('INSERT IGNORE INTO study_tracks(id,name,description) VALUES(?,?,?)');foreach($tracks as $i=>$t)$ins->execute([$i+1,$t,'ECRS institutional study-track grouping.']);
$subjects=['Verbal Reasoning','Mathematical Reasoning','Arithmetic and Algebra','Language','Geometry and Trigonometry','Physics','Chemistry','Biology','Logic','History','Literature','Economy','Geography'];$ins=$pdo->prepare('INSERT IGNORE INTO subjects(name,description,sort_order) VALUES(?,?,?)');foreach($subjects as $i=>$s)$ins->execute([$s,'Final ECRS entrance-exam subject area.',$i+1]);
$programs=[
    ['BSCS','BS Computer Science','Computing, algorithms, software development, and problem solving.',2],
    ['BSIS','BS Information Systems','Business-technology integration and information management.',4],
    ['BSIT','BS Information Technology','Applied computing, systems administration, networking, and web technologies.',2],
    ['BSMATH','BS Mathematics','Advanced mathematical theory and applied mathematics.',2],
    ['BSSTAT','BS Statistics','Statistical analysis, probability, data interpretation, and quantitative modeling.',4],
    ['BSCHEM','BS Chemistry','Chemical sciences, laboratory analysis, and scientific research.',1],
    ['BSBIO','BS Biology','Biological sciences, ecology, genetics, and laboratory research.',1],
    ['BSPHY','BS Physics','Physical sciences, mathematical modeling, and experimental analysis.',2],
    ['BSN','BS Nursing','Health sciences, patient care, and clinical practice.',1],
    ['BSMT','BS Medical Technology','Laboratory diagnostics, biomedical testing, and health sciences.',1],
    ['BSPHARM','BS Pharmacy','Pharmaceutical sciences, chemistry, and health care.',1],
    ['BSPH','BS Public Health','Community health, prevention, and health science analysis.',1],
    ['BSPsych','BS Psychology','Human behavior, research, and applied social science.',3],
    ['BAComm','BA Communication','Media, communication theory, writing, and public communication.',3],
    ['BAEng','BA English Language Studies','Language, literature, writing, and communication.',3],
    ['BAHist','BA History','Historical inquiry, culture, evidence, and interpretation.',3],
    ['BAPolSci','BA Political Science','Government, public institutions, policy, and political analysis.',3],
    ['BASoc','BA Sociology','Society, institutions, communities, and social research.',3],
    ['BSEcon','BS Economics','Economic analysis, markets, policy, and quantitative reasoning.',4],
    ['BSA','BS Accountancy','Accounting, auditing, finance, and analytical decision-making.',4],
    ['BSMA','BS Management Accounting','Management accounting, analytics, planning, and control.',4],
    ['BSBA-FM','BSBA Financial Management','Finance, investment, banking, and business analysis.',4],
    ['BSBA-MM','BSBA Marketing Management','Marketing strategy, consumer behavior, and business communication.',4],
    ['BSBA-HRM','BSBA Human Resource Management','Human resource planning, organizational behavior, and management.',4],
    ['BSEntrep','BS Entrepreneurship','Business creation, innovation, operations, and enterprise management.',4],
    ['BSHM','BS Hospitality Management','Hospitality operations, service management, and business fundamentals.',4],
    ['BSTM','BS Tourism Management','Tourism operations, destination management, and service business.',4],
    ['BSCE','BS Civil Engineering','Structures, infrastructure, mathematics, and physical sciences.',2],
    ['BSEE','BS Electrical Engineering','Electrical systems, physics, mathematics, and engineering design.',2],
    ['BSME','BS Mechanical Engineering','Mechanics, energy systems, mathematics, and engineering design.',2],
    ['BSECE','BS Electronics Engineering','Electronics, communications, physics, logic, and mathematics.',2],
    ['BSCpE','BS Computer Engineering','Computer hardware, software, electronics, logic, and mathematics.',2],
    ['BSABE','BS Agricultural and Biosystems Engineering','Engineering systems applied to agriculture, biology, and environment.',2],
];$ins=$pdo->prepare('INSERT IGNORE INTO programs(code,name,description,study_track_id) VALUES(?,?,?,?)');foreach($programs as $p)$ins->execute($p);
// Ensure every program has a row for every subject.
$pdo->exec('INSERT IGNORE INTO program_subject_weights(program_id,subject_id,weight) SELECT p.id,s.id,0 FROM programs p CROSS JOIN subjects s');
// DEMO-ONLY starting weights so the prototype works immediately. Replace in Admin > Weights with the final source-approved values.
$maps=[
1=>['Biology'=>5,'Chemistry'=>5,'Physics'=>3,'Mathematical Reasoning'=>2,'Arithmetic and Algebra'=>2,'Language'=>1,'Logic'=>2,'Verbal Reasoning'=>1],
2=>['Mathematical Reasoning'=>5,'Arithmetic and Algebra'=>5,'Geometry and Trigonometry'=>5,'Physics'=>4,'Chemistry'=>2,'Logic'=>4,'Verbal Reasoning'=>1,'Language'=>1],
3=>['Verbal Reasoning'=>5,'Language'=>5,'History'=>4,'Literature'=>4,'Geography'=>3,'Economy'=>2,'Logic'=>2,'Mathematical Reasoning'=>1],
4=>['Mathematical Reasoning'=>5,'Arithmetic and Algebra'=>5,'Economy'=>4,'Logic'=>4,'Verbal Reasoning'=>2,'Language'=>2,'Geography'=>2,'History'=>1]
];$sid=[];foreach($pdo->query('SELECT id,name FROM subjects') as $r)$sid[$r['name']]=$r['id'];$u=$pdo->prepare('UPDATE program_subject_weights psw JOIN programs p ON p.id=psw.program_id SET psw.weight=? WHERE p.study_track_id=? AND psw.subject_id=?');foreach($maps as $track=>$m)foreach($m as $name=>$val)$u->execute([$val,$track,$sid[$name]]);
// Demo exam for Maria.
$st=$pdo->query("SELECT id FROM students WHERE student_no='2026-00451'")->fetchColumn();$q=$pdo->prepare('SELECT id FROM exams WHERE student_id=? LIMIT 1');$q->execute([$st]);$eid=$q->fetchColumn();if(!$eid){$q=$pdo->prepare("INSERT INTO exams(student_id,exam_name,exam_date,status,percentile) VALUES(?, 'University Entrance Exam','2026-03-15','Completed','Top 8%')");$q->execute([$st]);$eid=$pdo->lastInsertId();}
$demo=['Verbal Reasoning'=>89,'Mathematical Reasoning'=>92,'Arithmetic and Algebra'=>90,'Language'=>88,'Geometry and Trigonometry'=>91,'Physics'=>92,'Chemistry'=>91,'Biology'=>93,'Logic'=>94,'History'=>86,'Literature'=>87,'Economy'=>88,'Geography'=>89];$ins=$pdo->prepare('INSERT INTO exam_scores(exam_id,subject_id,score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score)');foreach($demo as $name=>$score)$ins->execute([$eid,$sid[$name],$score]);
$pdo->prepare('INSERT IGNORE INTO settings(student_id,email_notifications,recommendation_updates) VALUES(?,1,1)')->execute([$st]);
$message='Installation complete. The database, 13 subjects, 4 study tracks, 33 demo programs, demo weights, and sample accounts are ready.';
}catch(Throwable $e){$error=$e->getMessage();}}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install ECRS</title><link rel="stylesheet" href="assets/css/styles.css"></head><body><div class="login-shell"><div class="login-card" style="width:min(680px,100%)"><h1>ECRS Installer</h1><p class="muted">Run once after copying the project into XAMPP htdocs. Default database account is root with no password; change config/config.php if your setup differs.</p><?php if($message):?><div class="flash success"><?=htmlspecialchars($message)?></div><p><a class="btn primary" href="login.php">Student Login</a> <a class="btn" href="admin/login.php">Admin Login</a></p><?php elseif($error):?><div class="flash error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><button class="btn primary">Install / Repair Database</button></form><div class="notice" style="margin-top:22px"><b>Demo credentials</b><p>Student: 2026-00451 / Student123!<br>Admin: admin@ecrs.local / Admin123!</p></div><p class="mini muted">Security note: delete or rename install.php after successful setup.</p></div></div></body></html>
