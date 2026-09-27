<?php
function admin_notification_items(): array {
    $items=[];
    try {
        $totalStudents=(int)db()->query('SELECT COUNT(*) FROM students WHERE is_active=1')->fetchColumn();
        $pendingExams=(int)db()->query('SELECT COUNT(*) FROM students s WHERE s.is_active=1 AND NOT EXISTS (SELECT 1 FROM exams e WHERE e.student_id=s.id)')->fetchColumn();
        $programs=(int)db()->query('SELECT COUNT(*) FROM programs')->fetchColumn();
        $items[]=[
            'icon'=>'👥',
            'title'=>$totalStudents.' active students',
            'message'=>'Open Student Management to review registered applicants.',
            'href'=>base_url('admin/index.php?page=students')
        ];
        $items[]=[
            'icon'=>$pendingExams>0?'⏳':'✅',
            'title'=>$pendingExams>0?$pendingExams.' students without exam records':'Exam records up to date',
            'message'=>$pendingExams>0?'Add entrance exam scores for pending students.':'All active students currently have an exam record.',
            'href'=>base_url('admin/index.php?page=scores')
        ];
        $items[]=[
            'icon'=>'🎓',
            'title'=>$programs.' programs configured',
            'message'=>'Program and subject-weight settings are available for review.',
            'href'=>base_url('admin/index.php?page=programs')
        ];
    } catch(Throwable $e) {
        $items[]=['icon'=>'🔔','title'=>'Admin notifications','message'=>'Your notification center is ready.','href'=>base_url('admin/index.php')];
    }
    return $items;
}
function admin_header(string $title,string $subtitle,string $active): void {
    $flash=pull_flash();
    $notifications=admin_notification_items();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> | ECRS Admin</title><link rel="stylesheet" href="<?=e(base_url('assets/css/styles.css'))?>"></head>
<body class="app-body admin-app">
<aside class="sidebar admin-sidebar">
  <a class="brand admin-brand" href="<?=e(base_url('admin/index.php'))?>"><span class="logo">▱</span><span><b>ECRS</b><small>Course Recommender</small></span></a>
  <div class="admin-portal-pill">ADMIN PORTAL</div>
  <div class="admin-nav-label">NAVIGATION</div>
  <nav>
  <?php $items=['dashboard'=>'▦ Dashboard','students'=>'👥 Students','scores'=>'📋 Exam Results','subjects'=>'🧩 Subjects','weights'=>'⚖ Weights','programs'=>'🎓 Programs']; foreach($items as $key=>$label): ?>
    <a class="<?= $active===$key?'active':'' ?>" href="<?=e(base_url('admin/index.php?page='.$key))?>"><?=$label?></a>
  <?php endforeach; ?>
  </nav>
  <a class="logout" href="<?=e(base_url('admin/logout.php'))?>">🚪 Logout</a>
</aside>
<main class="main admin-main">
  <header class="topbar admin-topbar">
    <div><h1><?=e($title)?></h1><p><?=e($subtitle)?></p></div>
    <div class="userbox">
      <div class="notification-wrap" data-notification-center data-notification-key="admin">
        <button type="button" class="bell notification-toggle" aria-label="Open notifications" aria-expanded="false">🔔<i class="notification-dot"></i></button>
        <div class="notification-dropdown" role="dialog" aria-label="Admin notifications">
          <div class="notification-head"><div><b>Notifications</b><small><?=count($notifications)?> system updates</small></div><button type="button" class="notification-close" aria-label="Close notifications">×</button></div>
          <div class="notification-list">
            <?php foreach($notifications as $n): ?>
            <a class="notification-item" href="<?=e($n['href'])?>"><span class="notification-icon"><?=e($n['icon'])?></span><span><b><?=e($n['title'])?></b><small><?=e($n['message'])?></small></span></a>
            <?php endforeach; ?>
          </div>
          <div class="notification-foot"><button type="button" class="notification-read">Mark all as read</button><a href="<?=e(base_url('admin/index.php'))?>">Open dashboard</a></div>
        </div>
      </div>
      <span class="avatar admin-avatar">AD</span><span><b>Administrator</b><small>System Admin</small></span>
    </div>
  </header>
  <section class="content admin-content"><?php if($flash):?><div class="flash <?=$flash['type']?>"><?=e($flash['message'])?></div><?php endif;?>
<?php }
function admin_footer(): void { ?></section></main><script src="<?=e(base_url('assets/js/app.js'))?>"></script></body></html><?php }
