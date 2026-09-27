<?php
function student_notification_items(array $student): array {
    $items=[];
    try {
        $exam=latest_exam((int)$student['id']);
        if($exam){
            $items[]=[
                'icon'=>'📋',
                'title'=>'Entrance exam results available',
                'message'=>'Your latest entrance exam scores are ready to review.',
                'href'=>base_url('student/index.php?page=results')
            ];
            $recs=recommendation_rows((int)$student['id']);
            if($recs){
                $top=$recs[0];
                $items[]=[
                    'icon'=>'⭐',
                    'title'=>'Recommendations updated',
                    'message'=>'Your current top match is '.$top['name'].' ('.round((float)$top['fit_score']).'% fit).',
                    'href'=>base_url('student/index.php?page=recommendations')
                ];
            }
        } else {
            $items[]=[
                'icon'=>'⏳',
                'title'=>'Exam results pending',
                'message'=>'Your recommendations will appear after your entrance exam scores are recorded.',
                'href'=>base_url('student/index.php?page=results')
            ];
        }
        $items[]=[
            'icon'=>'👤',
            'title'=>'Keep your profile current',
            'message'=>'Review your contact details and account preferences anytime.',
            'href'=>base_url('student/index.php?page=profile')
        ];
    } catch(Throwable $e) {
        $items[]=[
            'icon'=>'🔔',
            'title'=>'Notifications',
            'message'=>'Your notification center is ready.',
            'href'=>base_url('student/index.php')
        ];
    }
    return $items;
}
function student_header(string $title,string $subtitle,string $active,array $student): void {
    $flash=pull_flash();
    $notifications=student_notification_items($student);
    $notificationKey='student-'.$student['id'];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> | ECRS</title><link rel="stylesheet" href="<?=e(base_url('assets/css/styles.css'))?>"></head>
<body class="app-body"><aside class="sidebar"><a class="brand" href="<?=e(base_url('student/index.php'))?>"><span class="logo">▱</span><span><b>ECRS</b><small>Course Recommender</small></span></a><nav>
<?php $items=['dashboard'=>'▦ Dashboard','results'=>'📋 Exam Results','recommendations'=>'⭐ Recommendations','programs'=>'🎓 Programs','profile'=>'👤 My Profile','settings'=>'⚙ Settings']; foreach($items as $key=>$label): ?>
<a class="<?= $active===$key?'active':'' ?>" href="<?=e(base_url('student/index.php?page='.$key))?>"><?=$label?></a><?php endforeach; ?></nav>
<a class="logout" href="<?=e(base_url('logout.php'))?>">🚪 Logout</a></aside>
<main class="main"><header class="topbar"><div><h1><?=e($title)?></h1><p><?=e($subtitle)?></p></div><div class="userbox">
<div class="notification-wrap" data-notification-center data-notification-key="<?=e($notificationKey)?>">
  <button type="button" class="bell notification-toggle" aria-label="Open notifications" aria-expanded="false">🔔<i class="notification-dot"></i></button>
  <div class="notification-dropdown" role="dialog" aria-label="Notifications">
    <div class="notification-head"><div><b>Notifications</b><small><?=count($notifications)?> updates</small></div><button type="button" class="notification-close" aria-label="Close notifications">×</button></div>
    <div class="notification-list">
      <?php foreach($notifications as $n): ?>
      <a class="notification-item" href="<?=e($n['href'])?>"><span class="notification-icon"><?=e($n['icon'])?></span><span><b><?=e($n['title'])?></b><small><?=e($n['message'])?></small></span></a>
      <?php endforeach; ?>
    </div>
    <div class="notification-foot"><button type="button" class="notification-read">Mark all as read</button><a href="<?=e(base_url('student/index.php?page=settings'))?>">Notification settings</a></div>
  </div>
</div>
<span class="avatar"><?=e(strtoupper(substr($student['full_name'],0,1).(strpos($student['full_name'],' ')!==false?substr(strrchr($student['full_name'],' '),1,1):'')))?></span><span><b><?=e($student['full_name'])?></b><small><?=e($student['student_no'])?></small></span></div></header>
<section class="content"><?php if($flash):?><div class="flash <?=$flash['type']?>"><?=e($flash['message'])?></div><?php endif;?>
<?php }
function student_footer(): void { ?></section></main><script src="<?=e(base_url('assets/js/app.js'))?>"></script></body></html><?php }
