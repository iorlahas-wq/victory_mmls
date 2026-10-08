<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle='Lessons Management'; $showNavbar=true;
$search=trim((string)($_GET['search']??'')); $status=(string)($_GET['status']??'all'); $skillId=(int)($_GET['skill_id']??0);
if(!in_array($status,['all','published','draft'],true))$status='all';
$skills=$pdo->query("SELECT id,name FROM skills WHERE is_active=1 ORDER BY name")->fetchAll();
$where=[];$params=[];
if($search!==''){ $where[]="(l.title LIKE :search OR l.description LIKE :search)";$params['search']="%$search%"; }
if($status==='published')$where[]='l.is_published=1'; elseif($status==='draft')$where[]='l.is_published=0';
if($skillId>0){$where[]='l.skill_id=:skill_id';$params['skill_id']=$skillId;}
$sql="SELECT l.*,s.name skill_name,(SELECT COUNT(*) FROM lesson_contents lc WHERE lc.lesson_id=l.id) content_count FROM lessons l JOIN skills s ON s.id=l.skill_id";
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=" ORDER BY s.name,l.lesson_order,l.title";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$lessons=$stmt->fetchAll();
require_once __DIR__.'/../../includes/header.php';
?>
<main class="admin-page lessons-management"><div class="container">
<section class="module-heading"><div><span class="section-label">LESSON MANAGEMENT</span><h1>Lessons</h1><p>Create and organise practical lessons under each vocational skill.</p></div><div class="module-heading-actions"><a href="<?=e(url('app/admin/lessons/create.php'))?>" class="btn btn-primary">+ Add Lesson</a></div></section>
<?php foreach(consume_flash() as $m): ?><div class="flash-message flash-<?=e($m['type'])?>"><?=e($m['message'])?></div><?php endforeach; ?>
<section class="filter-card"><form method="get"><div class="filter-grid">
<div class="form-group"><label>Search</label><input type="text" name="search" value="<?=e($search)?>" placeholder="Search lessons..."></div>
<div class="form-group"><label>Skill</label><select name="skill_id"><option value="0">All skills</option><?php foreach($skills as $s): ?><option value="<?=$s['id']?>" <?=$skillId==(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Status</label><select name="status"><option value="all" <?=$status==='all'?'selected':''?>>All</option><option value="published" <?=$status==='published'?'selected':''?>>Published</option><option value="draft" <?=$status==='draft'?'selected':''?>>Draft</option></select></div>
<div class="filter-actions"><button class="btn btn-primary">Filter</button><a class="btn btn-secondary" href="<?=e(url('app/admin/lessons/index.php'))?>">Reset</a></div>
</div></form></section>
<section class="module-card"><div class="module-card-heading"><div><span class="section-label">LESSON CATALOGUE</span><h2><?=count($lessons)?> <?=count($lessons)===1?'lesson':'lessons'?></h2></div></div>
<?php if(!$lessons): ?><div class="empty-state"><h3>No lessons found</h3><p>Create your first lesson for one of the vocational skills.</p><a class="btn btn-primary" href="<?=e(url('app/admin/lessons/create.php'))?>">Create First Lesson</a></div>
<?php else: ?><div class="table-wrap"><table class="data-table lessons-table"><thead><tr><th>ORDER</th><th>LESSON</th><th>SKILL</th><th>CONTENT</th><th>STATUS</th><th>CREATED</th><th>ACTIONS</th></tr></thead><tbody>
<?php foreach($lessons as $l): ?><tr><td><span class="lesson-order"><?=$l['lesson_order']?></span></td><td><strong><?=e($l['title'])?></strong><?php if($l['description']): ?><div class="table-description"><?=e(mb_strimwidth((string)$l['description'],0,110,'...'))?></div><?php endif;?></td><td><?=e($l['skill_name'])?></td><td><span class="content-count"><?=$l['content_count']?></span></td><td><span class="status-badge <?=$l['is_published']?'status-published':'status-draft'?>"><?=$l['is_published']?'Published':'Draft'?></span></td><td><?=e(date('d M Y',strtotime($l['created_at'])))?></td><td><div class="table-actions"><a class="action-link action-view" href="<?=e(url('app/admin/lessons/view.php?id='.$l['id']))?>">View</a><a class="action-link action-edit" href="<?=e(url('app/admin/lessons/edit.php?id='.$l['id']))?>">Edit</a><form method="post" action="<?=e(url('app/admin/lessons/toggle.php'))?>" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$l['id']?>"><button class="action-button <?=$l['is_published']?'action-unpublish':'action-publish'?>"><?=$l['is_published']?'Unpublish':'Publish'?></button></form></div></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?></section>
</div></main>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
