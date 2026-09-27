<?php
require __DIR__.'/../includes/bootstrap.php';
require __DIR__.'/../includes/admin_layout.php';
require_admin();

$page=$_GET['page']??'dashboard';
$allowed=['dashboard','students','scores','subjects','weights','programs'];
if(!in_array($page,$allowed,true))$page='dashboard';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if($page==='students'&&isset($_POST['add_student'])){
        $no=trim($_POST['student_no']);$name=trim($_POST['full_name']);$email=trim($_POST['email']);
        $status=trim($_POST['applicant_status']);$ay=trim($_POST['academic_year']);$pw=$_POST['password'];
        try{
            $s=db()->prepare('INSERT INTO students(student_no,full_name,email,contact_number,applicant_status,academic_year,password_hash) VALUES(?,?,?,?,?,?,?)');
            $s->execute([$no,$name,$email,trim($_POST['contact_number']),$status,$ay,password_hash($pw,PASSWORD_DEFAULT)]);
            flash('success','Student account created.');
        }catch(Throwable $e){flash('error','Could not create student. Student ID or email may already exist.');}
        redirect(base_url('admin/index.php?page=students'));
    }
    if($page==='subjects'&&isset($_POST['add_subject'])){
        try{$s=db()->prepare('INSERT INTO subjects(name,description,sort_order) VALUES(?,?,99)');$s->execute([trim($_POST['name']),trim($_POST['description'])]);flash('success','Subject added.');}
        catch(Throwable $e){flash('error','Subject name must be unique.');}
        redirect(base_url('admin/index.php?page=subjects'));
    }
    if($page==='programs'&&isset($_POST['add_program'])){
        try{
            $s=db()->prepare('INSERT INTO programs(code,name,description,study_track_id) VALUES(?,?,?,?)');
            $s->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),(int)$_POST['study_track_id']]);
            $pid=(int)db()->lastInsertId();$subs=db()->query('SELECT id FROM subjects')->fetchAll();
            $ins=db()->prepare('INSERT INTO program_subject_weights(program_id,subject_id,weight) VALUES(?,?,0)');
            foreach($subs as $sub)$ins->execute([$pid,$sub['id']]);
            flash('success','Program added. Configure its weights next.');
        }catch(Throwable $e){flash('error','Program code or name may already exist.');}
        redirect(base_url('admin/index.php?page=programs'));
    }
    if($page==='scores'&&isset($_POST['save_scores'])){
        $sid=(int)$_POST['student_id'];$pdo=db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT id FROM exams WHERE student_id=? ORDER BY id DESC LIMIT 1');$s->execute([$sid]);$eid=$s->fetchColumn();
            if(!$eid){$s=$pdo->prepare("INSERT INTO exams(student_id,exam_name,exam_date,status,percentile) VALUES(?,'University Entrance Exam',CURDATE(),'Completed',NULL)");$s->execute([$sid]);$eid=$pdo->lastInsertId();}
            $del=$pdo->prepare('DELETE FROM exam_scores WHERE exam_id=?');$del->execute([$eid]);
            $ins=$pdo->prepare('INSERT INTO exam_scores(exam_id,subject_id,score) VALUES(?,?,?)');
            foreach($_POST['score']??[] as $subId=>$score){if($score==='')continue;$v=(float)$score;if($v<0||$v>100)throw new Exception('Score out of range');$ins->execute([$eid,(int)$subId,$v]);}
            $pdo->commit();flash('success','Exam scores saved in one transaction.');
        }catch(Throwable $e){$pdo->rollBack();flash('error','Scores must be numeric values from 0 to 100.');}
        redirect(base_url('admin/index.php?page=scores&student_id='.$sid));
    }
    if($page==='weights'&&isset($_POST['save_weights'])){
        $pid=(int)$_POST['program_id'];$pdo=db();$pdo->beginTransaction();
        try{
            $u=$pdo->prepare('UPDATE program_subject_weights SET weight=? WHERE program_id=? AND subject_id=?');
            foreach($_POST['weight']??[] as $sub=>$val){$v=max(0,(float)$val);$u->execute([$v,$pid,(int)$sub]);}
            $pdo->commit();flash('success','Program weights updated. Recommendations will recalculate on the next request.');
        }catch(Throwable $e){$pdo->rollBack();flash('error','Could not update weights.');}
        redirect(base_url('admin/index.php?page=weights&program_id='.$pid));
    }
}

function admin_student_snapshot(): array {
    $students=db()->query('SELECT * FROM students ORDER BY created_at DESC,id DESC')->fetchAll();
    $out=[];
    foreach($students as $st){
        $exam=latest_exam((int)$st['id']);$avg=null;$top=null;
        if($exam){
            $q=db()->prepare('SELECT AVG(score) FROM exam_scores WHERE exam_id=?');$q->execute([$exam['id']]);$v=$q->fetchColumn();
            if($v!==false&&$v!==null)$avg=(float)$v;
            $recs=recommendation_rows((int)$st['id']); if($recs)$top=$recs[0];
        }
        $out[]=['student'=>$st,'exam'=>$exam,'avg'=>$avg,'top'=>$top];
    }
    return $out;
}
function pct(float $v): string { return number_format($v,1).'%'; }

$title=['dashboard'=>'Admin Dashboard','students'=>'Student Management','scores'=>'Exam Results Management','subjects'=>'Subject Management','weights'=>'Weight Management','programs'=>'Program Management'][$page];
$subtitle=['dashboard'=>'System overview and analytics.','students'=>'Manage registered students and exam records.','scores'=>'View and manage student entrance examination scores.','subjects'=>'Manage the final 13 entrance-exam subject areas.','weights'=>'Configure transparent per-program subject weights.','programs'=>'Configure academic programs and study-track groupings.'][$page];
admin_header($title,$subtitle,$page);

if($page==='dashboard'){
    $snap=admin_student_snapshot();
    $totalStudents=count($snap);$programCount=(int)db()->query('SELECT COUNT(*) FROM programs')->fetchColumn();$subjectCount=(int)db()->query('SELECT COUNT(*) FROM subjects')->fetchColumn();$examCount=(int)db()->query('SELECT COUNT(*) FROM exams')->fetchColumn();
    $withExam=0;$avgScores=[];$topCounts=[];$topFits=[];$allFit=[];
    foreach($snap as $r){
        if($r['exam'])$withExam++;
        if($r['avg']!==null)$avgScores[]=$r['avg'];
        if($r['top']){$label=$r['top']['code']?:$r['top']['name'];$topCounts[$label]=($topCounts[$label]??0)+1;$topFits[]=(float)$r['top']['fit_score'];}
        if($r['exam'])foreach(recommendation_rows((int)$r['student']['id']) as $rec)$allFit[]=(float)$rec['fit_score'];
    }
    arsort($topCounts);$topCounts=array_slice($topCounts,0,5,true);$maxCount=max(1,...array_values($topCounts?:[1]));
    $avgOverall=$avgScores?array_sum($avgScores)/count($avgScores):0;
    $completion=$totalStudents?($withExam/$totalStudents*100):0;
    $recommendRate=$totalStudents?(count($topFits)/$totalStudents*100):0;
    $highFit=$topFits?(count(array_filter($topFits,fn($v)=>$v>=85))/count($topFits)*100):0;
    $avgFit=$allFit?array_sum($allFit)/count($allFit):0;
?>
<div class="admin-metric-grid">
  <div class="admin-metric-card"><div class="metric-icon purple">👥</div><span class="metric-live">CURRENT</span><strong><?=$totalStudents?></strong><p>Total Students</p><small>Registered applicant accounts</small></div>
  <div class="admin-metric-card"><div class="metric-icon pink">📋</div><span class="metric-live">CURRENT</span><strong><?=$examCount?></strong><p>Exams Recorded</p><small>Entrance exam records</small></div>
  <div class="admin-metric-card"><div class="metric-icon greenish">🎓</div><span class="metric-live">CURRENT</span><strong><?=$programCount?></strong><p>Active Programs</p><small><?=$subjectCount?> subject areas configured</small></div>
  <div class="admin-metric-card"><div class="metric-icon gold">⭐</div><span class="metric-live">CURRENT</span><strong><?=count($allFit)?></strong><p>Recommendations Generated</p><small><?=count($topFits)?> students with ranked matches</small></div>
</div>
<div class="admin-analytics-grid">
  <div class="admin-panel">
    <div class="panel-head"><div><h2>Top Recommended Programs</h2><p>Number of students per top recommendation</p></div><span class="visual-badge">LIVE DATA</span></div>
    <?php if($topCounts): ?>
      <div class="vertical-chart">
        <?php $ci=0; foreach($topCounts as $name=>$count): $h=max(16,($count/$maxCount)*100); ?>
          <div class="vbar-item"><div class="vbar-count"><?=$count?></div><div class="vbar-track"><span class="tone-<?=$ci%5?>" style="height:<?=$h?>%"></span></div><div class="vbar-label"><?=e(strlen($name)>18?substr($name,0,16).'…':$name)?></div></div>
        <?php $ci++; endforeach; ?>
      </div>
      <div class="chart-legend-note">Bars update automatically from each student's current #1 recommendation.</div>
    <?php else: ?><div class="empty compact">No recommendations yet. Enter exam scores to generate analytics.</div><?php endif; ?>
  </div>
  <div class="admin-panel">
    <div class="panel-head"><div><h2>System Overview</h2><p>Key performance indicators from current database records</p></div></div>
    <div class="kpi-row"><div><b>Average Overall Score</b><span><?=pct($avgOverall)?></span></div><div class="admin-progress bluebar"><span style="width:<?=min(100,$avgOverall)?>%"></span></div></div>
    <div class="kpi-row"><div><b>Recommendation Rate</b><span class="good"><?=pct($recommendRate)?></span></div><div class="admin-progress greenbar"><span style="width:<?=min(100,$recommendRate)?>%"></span></div></div>
    <div class="kpi-row"><div><b>Exam Completion Rate</b><span class="violet"><?=pct($completion)?></span></div><div class="admin-progress violetbar"><span style="width:<?=min(100,$completion)?>%"></span></div></div>
    <div class="kpi-row"><div><b>High Fit Top Matches (≥85%)</b><span class="orange"><?=pct($highFit)?></span></div><div class="admin-progress orangebar"><span style="width:<?=min(100,$highFit)?>%"></span></div></div>
    <div class="analytics-foot">Average Fit Score across evaluated programs: <b><?=pct($avgFit)?></b></div>
  </div>
</div>
<div class="admin-panel admin-recent">
  <div class="panel-head"><div><h2>Recent Student Activity</h2><p>Latest applicants and their current exam/recommendation status</p></div><a class="btn" href="<?=e(base_url('admin/index.php?page=students'))?>">View All Students</a></div>
  <div class="table-wrap"><table class="admin-table"><thead><tr><th>Student ID</th><th>Student Name</th><th>Exam Status</th><th>Overall Score</th><th>Top Match</th><th>Fit</th></tr></thead><tbody>
  <?php foreach(array_slice($snap,0,6) as $r): ?><tr><td><b><?=e($r['student']['student_no'])?></b></td><td><?=e($r['student']['full_name'])?></td><td><?php if($r['exam']):?><span class="status-pill completed">Completed</span><?php else:?><span class="status-pill pending">Pending</span><?php endif;?></td><td><?= $r['avg']!==null?'<b>'.pct($r['avg']).'</b>':'—' ?></td><td><?= $r['top']?'<a class="table-link" href="'.e(base_url('admin/index.php?page=weights&program_id='.$r['top']['id'])).'">'.e($r['top']['name']).'</a>':'—' ?></td><td><?= $r['top']?'<b class="good">'.pct((float)$r['top']['fit_score']).'</b>':'—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php
}elseif($page==='students'){
    $snap=admin_student_snapshot();
?>
<div class="admin-toolbar screenshot-toolbar">
  <input class="admin-search" data-search=".student-row" placeholder="🔍  Search students by name or ID...">
  <a href="#add-student" class="btn primary">+ Add Student</a>
</div>
<div class="admin-panel flush management-table-card">
  <div class="table-wrap"><table class="admin-table admin-reference-table"><thead><tr><th>Student ID</th><th>Name</th><th>Exam Status</th><th>Overall Score</th><th>Top Recommendation</th><th>Fit</th><th>Action</th></tr></thead><tbody>
  <?php foreach($snap as $r):?><tr class="student-row"><td><b><?=e($r['student']['student_no'])?></b></td><td><?=e($r['student']['full_name'])?></td><td><?= $r['exam']?'<span class="status-pill completed">Completed</span>':'<span class="status-pill pending">Pending</span>' ?></td><td><?= $r['avg']!==null?'<b>'.pct($r['avg']).'</b>':'—' ?></td><td><?= $r['top']?'<span class="table-link">'.e($r['top']['name']).'</span>':'—' ?></td><td><?= $r['top']?'<b class="good">'.pct((float)$r['top']['fit_score']).'</b>':'—' ?></td><td><a class="btn small" href="<?=e(base_url('admin/index.php?page=scores&student_id='.$r['student']['id']))?>">View / Edit</a></td></tr><?php endforeach;?>
  </tbody></table></div>
</div>
<form id="add-student" class="admin-panel form-panel aligned-form-panel" method="post">
  <div class="panel-head"><div><h2>Add Student</h2><p>Create a new incoming applicant account.</p></div></div>
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
  <div class="form-grid"><div class="field"><label>Student ID</label><input name="student_no" required></div><div class="field"><label>Full Name</label><input name="full_name" required></div><div class="field"><label>Email</label><input type="email" name="email" required></div><div class="field"><label>Contact Number</label><input name="contact_number"></div><div class="field"><label>Applicant Status</label><input name="applicant_status" value="Incoming Freshman"></div><div class="field"><label>Academic Year</label><input name="academic_year" value="AY 2026–2027"></div><div class="field"><label>Temporary Password</label><input name="password" value="Student123!" required></div></div>
  <button class="btn primary" name="add_student" style="margin-top:18px">+ Add Student</button>
</form>
<?php
}elseif($page==='scores'){
    $students=db()->query('SELECT id,student_no,full_name FROM students WHERE is_active=1 ORDER BY student_no')->fetchAll();
    $subjects=db()->query('SELECT * FROM subjects ORDER BY sort_order,id')->fetchAll();
    $scoreRows=[];
    foreach($students as $st){
        $exam=latest_exam((int)$st['id']); $scores=[]; $avg=null;
        if($exam){ foreach(exam_scores((int)$exam['id']) as $r)$scores[(int)$r['id']]=(float)$r['score']; if($scores)$avg=array_sum($scores)/count($scores); }
        $scoreRows[]=['student'=>$st,'exam'=>$exam,'scores'=>$scores,'avg'=>$avg];
    }
    $sid=(int)($_GET['student_id']??0); $current=[]; $selected=null; $exam=null; $avg=null;
    if($sid){ foreach($students as $st)if((int)$st['id']===$sid)$selected=$st; if($selected){$exam=latest_exam($sid); if($exam){foreach(exam_scores((int)$exam['id']) as $r)$current[(int)$r['id']]=$r['score']; if($current)$avg=array_sum(array_map('floatval',$current))/count($current);}} }
?>
<div class="admin-toolbar screenshot-toolbar">
  <input class="admin-search" data-search=".score-row-admin" placeholder="🔍  Search students...">
  <form method="get" class="inline-select-form"><input type="hidden" name="page" value="scores"><select class="admin-select" name="student_id" onchange="if(this.value)this.form.submit()"><option value="">+ Add / Edit Record</option><?php foreach($students as $st):?><option value="<?=$st['id']?>" <?=$sid===(int)$st['id']?'selected':''?>><?=e($st['student_no'].' — '.$st['full_name'])?></option><?php endforeach;?></select></form>
</div>
<div class="admin-panel flush management-table-card exam-management-card">
 <div class="table-wrap score-table-wrap"><table class="admin-table admin-reference-table score-management-table"><thead><tr><th>Student ID</th><th>Name</th><?php foreach($subjects as $sub):?><th><?=e($sub['name'])?></th><?php endforeach;?><th>Overall</th><th>Action</th></tr></thead><tbody>
 <?php foreach($scoreRows as $row):?><tr class="score-row-admin"><td><b><?=e($row['student']['student_no'])?></b></td><td class="student-name-cell"><?=e($row['student']['full_name'])?></td><?php foreach($subjects as $sub): $v=$row['scores'][(int)$sub['id']]??null; ?><td><?= $v===null?'—':'<span class="score-text '.($v>=85?'excellent':($v>=70?'strong':'needs')).'">'.pct($v).'</span>' ?></td><?php endforeach;?><td><?= $row['avg']===null?'—':'<span class="overall-pill">'.round($row['avg']).'%</span>' ?></td><td><a class="btn small" href="<?=e(base_url('admin/index.php?page=scores&student_id='.$row['student']['id']))?>">Edit</a></td></tr><?php endforeach; ?>
 </tbody></table></div>
</div>
<?php if($sid && $selected): ?>
<div class="admin-panel score-editor-panel">
  <div class="panel-head"><div><h2><?=e($selected['full_name'])?> — Exam Score Record</h2><p>Enter or replace scores for the final 13 subject areas.</p></div><span class="status-pill <?=$exam?'completed':'pending'?>"><?=$exam?'Completed':'Pending'?></span></div>
  <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="student_id" value="<?=$sid?>">
    <div class="score-admin-grid aligned-score-grid"><?php foreach($subjects as $i=>$sub):?><div class="score-input-card"><span class="score-order"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><label><?=e($sub['name'])?></label><div class="score-input-wrap"><input type="number" step="0.01" min="0" max="100" name="score[<?=$sub['id']?>]" value="<?=e((string)($current[$sub['id']]??''))?>" placeholder="0"><span>%</span></div></div><?php endforeach;?></div>
    <div class="form-actions"><button class="btn primary" name="save_scores">Save / Replace Scores</button><a class="btn" href="<?=e(base_url('admin/index.php?page=scores'))?>">Close</a></div>
  </form>
</div>
<?php endif; ?>
<?php
}elseif($page==='subjects'){
    $rows=db()->query('SELECT * FROM subjects ORDER BY sort_order,id')->fetchAll();
?>
<div class="admin-toolbar screenshot-toolbar"><div class="toolbar-title-note"><b><?=count($rows)?> subject areas configured</b><span>Final entrance-exam structure</span></div><a class="btn primary" href="#add-subject">+ Add Subject</a></div>
<div class="admin-panel flush management-table-card"><div class="table-wrap"><table class="admin-table admin-reference-table"><thead><tr><th>#</th><th>Subject Area</th><th>Description</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $i=>$r):?><tr><td><span class="subject-no"><?=$i+1?></span></td><td><b><?=e($r['name'])?></b></td><td class="muted"><?=e($r['description'])?></td><td><span class="status-pill completed">Active</span></td></tr><?php endforeach;?></tbody></table></div></div>
<form id="add-subject" class="admin-panel form-panel aligned-form-panel" method="post"><div class="panel-head"><div><h2>Add Subject Area</h2><p>Add an additional subject only when required by the approved exam structure.</p></div></div><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="form-grid"><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Description</label><textarea name="description"></textarea></div></div><button class="btn primary" name="add_subject">+ Add Subject</button></form>
<?php
}elseif($page==='weights'){
    $progs=db()->query('SELECT id,code,name FROM programs ORDER BY name')->fetchAll();$pid=(int)($_GET['program_id']??($progs[0]['id']??0));
    $s=db()->prepare('SELECT psw.subject_id,psw.weight,sub.name FROM program_subject_weights psw JOIN subjects sub ON sub.id=psw.subject_id WHERE psw.program_id=? ORDER BY sub.sort_order,sub.id');$s->execute([$pid]);$weights=$s->fetchAll();
    $selected=null;foreach($progs as $p)if((int)$p['id']===$pid)$selected=$p;
    $activeWeights=array_values(array_filter($weights,fn($w)=>(float)$w['weight']>0)); $weightSum=array_sum(array_map(fn($w)=>(float)$w['weight'],$activeWeights));
    $maxWeight=0;$maxWeightName='—'; foreach($activeWeights as $w){if((float)$w['weight']>$maxWeight){$maxWeight=(float)$w['weight'];$maxWeightName=$w['name'];}}
?>
<form class="admin-toolbar" method="get"><input type="hidden" name="page" value="weights"><select class="admin-select wide" name="program_id" onchange="this.form.submit()"><?php foreach($progs as $p):?><option value="<?=$p['id']?>" <?=$pid===(int)$p['id']?'selected':''?>><?=e($p['code'].' — '.$p['name'])?></option><?php endforeach;?></select><span class="toolbar-note">Weights of 0 are excluded from the Fit Score.</span></form>
<div class="weight-visual-row">
  <div class="weight-summary-card"><span>🎯</span><div><small>Active Subjects</small><strong><?=count($activeWeights)?> / <?=count($weights)?></strong></div></div>
  <div class="weight-summary-card"><span>⚖</span><div><small>Total Weight</small><strong><?=number_format($weightSum,2)?></strong></div></div>
  <div class="weight-summary-card wide"><span>🏆</span><div><small>Highest Weight</small><strong><?=e($maxWeightName)?></strong><p><?=$maxWeight>0?number_format($maxWeight,2):'No active weight'?></p></div></div>
</div>
<div class="admin-panel"><div class="panel-head"><div><h2><?=e($selected['name']??'Program')?> — Subject Weights</h2><p>Configure the transparent weighting used by the recommendation formula.</p></div><span class="prototype-pill">Prototype weights</span></div>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="program_id" value="<?=$pid?>"><div class="weight-grid"><?php foreach($weights as $w):?><div class="weight-card"><div><b><?=e($w['name'])?></b><small><?=((float)$w['weight']>0)?'Included in calculation':'Currently excluded'?></small></div><input type="number" step="0.01" min="0" name="weight[<?=$w['subject_id']?>]" value="<?=e((string)$w['weight'])?>"></div><?php endforeach;?></div><div class="form-actions"><button class="btn primary" name="save_weights">Save Weights</button><span class="mini muted">Student recommendations update on the next request.</span></div></form></div>
<?php
}elseif($page==='programs'){
    $tracks=db()->query('SELECT * FROM study_tracks ORDER BY id')->fetchAll();
    $rows=db()->query('SELECT p.*,st.name AS study_track,(SELECT COUNT(*) FROM program_subject_weights psw WHERE psw.program_id=p.id AND psw.weight>0) AS weighted_subjects FROM programs p JOIN study_tracks st ON st.id=p.study_track_id ORDER BY p.name')->fetchAll();
?>
<div class="admin-toolbar screenshot-toolbar"><div class="toolbar-title-note"><b><?=count($rows)?> programs configured</b><span>Across <?=count($tracks)?> study tracks</span></div><a class="btn primary" href="#add-program">+ Add Program</a></div>
<div class="admin-panel flush management-table-card"><div class="table-wrap"><table class="admin-table admin-reference-table program-management-table"><thead><tr><th>Program Name</th><th>Program Code</th><th>Study Track</th><th>Active Subject Weights</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):?><tr class="prog-row"><td><b><?=e($r['name'])?></b><div class="mini muted"><?=e($r['description'])?></div></td><td><span class="code-pill"><?=e($r['code'])?></span></td><td><span class="track-pill"><?=e($r['study_track'])?></span></td><td><span class="weight-count"><?=(int)$r['weighted_subjects']?> / 13</span></td><td><a class="btn small" href="<?=e(base_url('admin/index.php?page=weights&program_id='.$r['id']))?>">Edit Weights</a></td></tr><?php endforeach;?></tbody></table></div></div>
<form id="add-program" class="admin-panel form-panel aligned-form-panel" method="post"><div class="panel-head"><div><h2>Add Program</h2><p>Add a catalog entry, then configure its subject weights.</p></div></div><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="form-grid"><div class="field"><label>Program Code</label><input name="code" required></div><div class="field"><label>Program Name</label><input name="name" required></div><div class="field"><label>Study Track</label><select name="study_track_id"><?php foreach($tracks as $t):?><option value="<?=$t['id']?>"><?=e($t['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Description</label><textarea name="description"></textarea></div></div><button class="btn primary" name="add_program">+ Add Program</button></form>
<?php }
admin_footer();
