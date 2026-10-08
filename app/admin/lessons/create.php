<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/app.php'; require_once __DIR__.'/../../includes/auth.php'; require_role('admin');
$pageTitle='Create Lesson';$showNavbar=true;$errors=[];
$skillId=(int)($_POST['skill_id']??$_GET['skill_id']??0);$title=trim((string)($_POST['title']??''));$description=trim((string)($_POST['description']??''));$order=(int)($_POST['lesson_order']??0);$published=isset($_POST['is_published'])?1:0;
$skills=$pdo->query("SELECT id,name FROM skills WHERE is_active=1 ORDER BY name")->fetchAll();
if($order<=0&&$skillId>0){$q=$pdo->prepare("SELECT COALESCE(MAX(lesson_order),0)+1 FROM lessons WHERE skill_id=:id");$q->execute(['id'=>$skillId]);$order=(int)$q->fetchColumn();} if($order<=0)$order=1;
if(is_post()){verify_csrf();if($skillId<=0)$errors[]='Please select a vocational skill.';if($title==='')$errors[]='Lesson title is required.';elseif(mb_strlen($title)>200)$errors[]='Lesson title must not exceed 200 characters.';if(mb_strlen($description)>10000)$errors[]='Lesson description must not exceed 10,000 characters.';if($order<1)$errors[]='Lesson order must be at least 1.';
if(!$errors){$q=$pdo->prepare("SELECT COUNT(*) FROM lessons WHERE skill_id=:s AND lesson_order=:o");$q->execute(['s'=>$skillId,'o'=>$order]);if($q->fetchColumn())$errors[]='That lesson order is already used for the selected skill.';}
if(!$errors){$q=$pdo->prepare("INSERT INTO lessons(skill_id,title,description,lesson_order,is_published,created_by) VALUES(:s,:t,:d,:o,:p,:u)");$q->execute(['s'=>$skillId,'t'=>$title,'d'=>$description!==''?$description:null,'o'=>$order,'p'=>$published,'u'=>(int)current_user()['id']]);flash('success','Lesson created successfully.');redirect('app/admin/lessons/index.php');}}
require_once __DIR__.'/../../includes/header.php';?>
<main class="admin-page lessons-management"><div class="container"><section class="module-heading"><div><span class="section-label">LESSON MANAGEMENT</span><h1>Create Lesson</h1><p>Create the lesson record. Text, images and live video content will be managed in the next stage.</p></div><a class="btn btn-secondary" href="<?=e(url('app/admin/lessons/index.php'))?>">← Back to Lessons</a></section>
<?php if($errors):?><div class="form-errors"><strong>Please correct the following:</strong><ul><?php foreach($errors as $e):?><li><?=e($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="form-card"><form method="post"><?=csrf_field()?>
<div class="form-group"><label>Vocational Skill <span class="required">*</span></label><select name="skill_id" required><option value="">Select a skill</option><?php foreach($skills as $s):?><option value="<?=$s['id']?>" <?=$skillId==(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Lesson Title <span class="required">*</span></label><input type="text" name="title" value="<?=e($title)?>" maxlength="200" required placeholder="e.g. Introduction to Kitchen Tools"></div>
<div class="form-group"><label>Lesson Description</label><textarea name="description" rows="7" maxlength="10000" placeholder="Describe what learners will learn..."><?=e($description)?></textarea></div>
<div class="form-group"><label>Lesson Order <span class="required">*</span></label><input type="number" name="lesson_order" value="<?=$order?>" min="1" required><small>Unique within the selected skill.</small></div>
<div class="checkbox-group"><label><input type="checkbox" name="is_published" value="1" <?=$published?'checked':''?>> Publish this lesson immediately</label><small>Leave unchecked while the lesson is being prepared.</small></div>
<div class="form-actions"><button class="btn btn-primary">Create Lesson</button><a class="btn btn-secondary" href="<?=e(url('app/admin/lessons/index.php'))?>">Cancel</a></div>
</form></section></div></main><?php require_once __DIR__.'/../../includes/footer.php';?>
