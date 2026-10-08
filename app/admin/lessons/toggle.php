<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/app.php';require_once __DIR__.'/../../includes/auth.php';require_role('admin');
if(!is_post()){http_response_code(405);exit('Method Not Allowed');}verify_csrf();$id=(int)($_POST['id']??0);if($id<=0){flash('error','Invalid lesson selected.');redirect('app/admin/lessons/index.php');}
$q=$pdo->prepare("SELECT id,is_published FROM lessons WHERE id=:id");$q->execute(['id'=>$id]);$l=$q->fetch();if(!$l){flash('error','The selected lesson could not be found.');redirect('app/admin/lessons/index.php');}
$new=(int)$l['is_published']===1?0:1;$q=$pdo->prepare("UPDATE lessons SET is_published=:p WHERE id=:id");$q->execute(['p'=>$new,'id'=>$id]);flash('success',$new?'Lesson published successfully.':'Lesson moved back to draft.');redirect('app/admin/lessons/index.php');
