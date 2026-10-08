<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/app.php';require_once __DIR__.'/../../includes/auth.php';require_role('admin');
$pageTitle='Edit Lesson';$showNavbar=true;$id=(int)($_GET['id']??$_POST['id']??0);if($id<=0){flash('error','Invalid lesson selected.');redirect('app/admin/lessons/index.php');}
$q=$pdo->prepare("SELECT * FROM lessons WHERE id=:id");$q->execute(['id'=>$id]);$lesson=$q->fetch();if(!$lesson){flash('error','The selected lesson could not be found.');redirect('app/admin/lessons/index.php');}
$skills=$pdo->query("SELECT id,name,is_active FROM skills WHERE is_active=1 OR id=".(int)$lesson['skill_id']." ORDER BY name")->fetchAll();$errors=[];
$skillId=(int)($_POST['skill_id']??$lesson['skill_id']);$title=trim((string)($_POST['title']??$lesson['title']));$description=trim((string)($_POST['description']??($lesson['description']??'')));$order=(int)($_POST['lesson_order']??$lesson['lesson_order']);$published=isset($_POST['is_published'])?1:(int)$lesson['is_published'];
if(is_post()){verify_csrf();if($skillId<=0)$errors[]='Please select a vocational skill.';if($title==='')$errors[]='Lesson title is required.';elseif(mb_strlen($title)>200)$errors[]='Lesson title must not exceed 200 characters.';if(mb_strlen($description)>10000)$errors[]='Lesson description must not exceed 10,000 characters.';if($order<1)$errors[]='Lesson order must be at least 1.';
if(!$errors){$q=$pdo->prepare("SELECT COUNT(*) FROM lessons WHERE skill_id=:s AND lesson_order=:o AND id<>:id");$q->execute(['s'=>$skillId,'o'=>$order,'id'=>$id]);if($q->fetchColumn())$errors[]='That lesson order is already used for the selected skill.';}
if(!$errors){$q=$pdo->prepare("UPDATE lessons SET skill_id=:s,title=:t,description=:d,lesson_order=:o,is_published=:p WHERE id=:id");$q->execute(['s'=>$skillId,'t'=>$title,'d'=>$description!==''?$description:null,'o'=>$order,'p'=>$published,'id'=>$id]);flash('success','Lesson updated successfully.');redirect('app/admin/lessons/index.php');}}
require_once __DIR__.'/../../includes/header.php';?>
<main class="admin-page lessons-management"><div class="container"><section class="module-heading"><div><span class="section-label">LESSON MANAGEMENT</span><h1>Edit Lesson</h1><p>Update the lesson information and publication status.</p></div><div class="module-heading-actions"><a class="btn btn-secondary" href="<?=e(url('app/admin/lessons/view.php?id='.$id))?>">View Lesson</a><a class="btn btn-secondary" href="<?=e(url('app/admin/lessons/index.php'))?>">← Back</a></div></section>
<?php if($errors):?><div class="form-errors"><strong>Please correct the following:</strong><ul><?php foreach($errors as $e):?><li><?=e($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="form-card"><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>">
<div class="form-group"><label>Vocational Skill <span class="required">*</span></label><select name="skill_id" required><?php foreach($skills as $s):?><option value="<?=$s['id']?>" <?=$skillId==(int)$s['id']?'selected':''?>><?=e($s['name'])?><?=((int)$s['is_active']===0?' (Inactive)':'')?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Lesson Title <span class="required">*</span></label><input type="text" name="title" value="<?=e($title)?>" maxlength="200" required></div>
<div class="form-group"><label>Lesson Description</label><textarea name="description" rows="7" maxlength="10000"><?=e($description)?></textarea></div>
<div class="form-group"><label>Lesson Order <span class="required">*</span></label><input type="number" name="lesson_order" value="<?=$order?>" min="1" required><small>Unique within the selected skill.</small></div>
<div class="checkbox-group"><label><input type="checkbox" name="is_published" value="1" <?=$published?'checked':''?>> Published</label><small>A draft lesson is not yet available as published learning content.</small></div>
<div class="form-actions"><button class="btn btn-primary">Save Changes</button><a class="btn btn-secondary" href="<?=e(url('app/admin/lessons/index.php'))?>">Cancel</a></div>
</form></section></div></main><?php require_once __DIR__.'/../../includes/footer.php';?>
