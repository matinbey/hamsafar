<?php
date_default_timezone_set('Asia/Tehran');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
header('Content-Type: application/json; charset=utf-8');
function out($d,$c=200){http_response_code($c);echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
set_exception_handler(function($e){error_log($e);out(['error'=>'خطای سرور'],500);});
$C=require __DIR__.'/config.php';
$db=new PDO("mysql:host={$C['host']};dbname={$C['name']};charset=utf8mb4",$C['user'],$C['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function q($s,$p=[]){global $db;$st=$db->prepare($s);$st->execute($p);return $st;}
function note($uid,$t){q('INSERT INTO notes(user_id,text) VALUES(?,?)',[$uid,$t]);}
$a=$_GET['a']??'';$now=date('Y-m-d H:i:s');
$W=['register','login','logout','create','join','cancel','done','rate','admin_block','admin_tokens'];
if(in_array($a,$W)){
  if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'method'],405);
  if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)out(['error'=>'bad request'],400); // دفاع در برابر CSRF
}
$in=json_decode(file_get_contents('php://input'),true)?:[];
$U=null;
if(!empty($_SESSION['uid'])){$U=q('SELECT * FROM users WHERE id=?',[$_SESSION['uid']])->fetch();if(!$U||$U['blocked']){session_destroy();$U=null;}}
function need(){global $U;if(!$U)out(['error'=>'ابتدا وارد شوید'],401);return $U;}
function admin(){if(need()['role']!=='admin')out(['error'=>'دسترسی ندارید'],403);}

switch($a){
case 'me': out($U?['id'=>$U['id'],'name'=>$U['name'],'role'=>$U['role'],'tokens'=>(int)$U['tokens'],'gender'=>$U['gender']]:null);
case 'register':
  $n=trim($in['name']??'');$sid=trim($in['sid']??'');$pw=(string)($in['pass']??'');$g=$in['gender']??'';$c=trim($in['contact']??'');
  if(!$n||mb_strlen($n)>60||!preg_match('/^\d{9}$/',$sid)||strlen($pw)<6||!in_array($g,['f','m'])||!$c||mb_strlen($c)>60)out(['error'=>'اطلاعات نامعتبر است (شماره دانشجویی ۹ رقم، رمز حداقل ۶ کاراکتر)'],400);
  if(q('SELECT 1 FROM users WHERE sid=?',[$sid])->fetch())out(['error'=>'این شماره دانشجویی قبلاً ثبت شده'],409);
  q('INSERT INTO users(name,sid,pass,gender,contact) VALUES(?,?,?,?,?)',[$n,$sid,password_hash($pw,PASSWORD_DEFAULT),$g,$c]);
  session_regenerate_id(true);$_SESSION['uid']=$db->lastInsertId();out(['ok'=>1]);
case 'login':
  $u=q('SELECT * FROM users WHERE sid=?',[trim($in['sid']??'')])->fetch();
  if(!$u||!password_verify((string)($in['pass']??''),$u['pass']))out(['error'=>'شماره دانشجویی یا رمز اشتباه است'],401);
  if($u['blocked'])out(['error'=>'حساب شما مسدود شده است'],403);
  session_regenerate_id(true);$_SESSION['uid']=$u['id'];out(['ok'=>1]);
case 'logout': session_destroy();out(['ok'=>1]);
case 'trips':
  need();
  out(q("SELECT t.id,t.from_p,t.to_p,t.depart_at,t.cap,t.gender,u.name owner,
   (SELECT COUNT(*) FROM members m WHERE m.trip_id=t.id) cnt,
   EXISTS(SELECT 1 FROM members m WHERE m.trip_id=t.id AND m.user_id=?) joined
   FROM trips t JOIN users u ON u.id=t.owner_id WHERE t.status='active' AND t.depart_at>? ORDER BY t.depart_at",[$U['id'],$now])->fetchAll());
case 'create':
  $u=need();
  $f=trim($in['from']??'');$t=trim($in['to']??'');$d=strtotime(str_replace('T',' ',(string)($in['when']??'')));$cap=(int)($in['cap']??0);$g=$in['g']??'all';
  if(!$f||!$t||$f===$t||mb_strlen($f)>60||mb_strlen($t)>60||!$d||$d<=time()||$cap<2||$cap>4||!in_array($g,['all','f','m']))out(['error'=>'اطلاعات سفر نامعتبر است'],400);
  if($g!=='all'&&$g!==$u['gender'])out(['error'=>'فیلتر جنسیتی با جنسیت شما سازگار نیست'],400);
  $db->beginTransaction();
  if(!q('UPDATE users SET tokens=tokens-1 WHERE id=? AND tokens>=1',[$u['id']])->rowCount()){$db->rollBack();out(['error'=>'توکن کافی ندارید'],402);}
  q('INSERT INTO trips(owner_id,from_p,to_p,depart_at,cap,gender) VALUES(?,?,?,?,?,?)',[$u['id'],$f,$t,date('Y-m-d H:i:s',$d),$cap,$g]);
  q('INSERT INTO members VALUES(?,?)',[$db->lastInsertId(),$u['id']]);
  $db->commit();out(['ok'=>1]);
case 'join':
  $u=need();$id=(int)($in['id']??0);$err=null;
  $db->beginTransaction();
  $t=q("SELECT * FROM trips WHERE id=? AND status='active' AND depart_at>? FOR UPDATE",[$id,$now])->fetch();
  if(!$t)$err='سفر در دسترس نیست';
  elseif(q('SELECT 1 FROM members WHERE trip_id=? AND user_id=?',[$id,$u['id']])->fetch())$err='قبلاً عضو شده‌اید';
  elseif(q('SELECT COUNT(*) c FROM members WHERE trip_id=?',[$id])->fetch()['c']>=$t['cap'])$err='ظرفیت سفر تکمیل است';
  elseif($t['gender']!=='all'&&$t['gender']!==$u['gender'])$err='این سفر برای جنسیت شما نیست';
  elseif(!q('UPDATE users SET tokens=tokens-1 WHERE id=? AND tokens>=1',[$u['id']])->rowCount())$err='توکن کافی ندارید';
  if($err){$db->rollBack();out(['error'=>$err],400);}
  q('INSERT INTO members VALUES(?,?)',[$id,$u['id']]);
  foreach(q('SELECT user_id FROM members WHERE trip_id=? AND user_id<>?',[$id,$u['id']])->fetchAll() as $m)note($m['user_id'],$u['name'].' به سفر '.$t['from_p'].' ← '.$t['to_p'].' پیوست.');
  $db->commit();out(['ok'=>1]);
case 'cancel':
  $u=need();$id=(int)($in['id']??0);
  $db->beginTransaction();
  $t=q("SELECT * FROM trips WHERE id=? AND status='active' FOR UPDATE",[$id])->fetch();
  if(!$t||!q('SELECT 1 FROM members WHERE trip_id=? AND user_id=?',[$id,$u['id']])->fetch()){$db->rollBack();out(['error'=>'سفر یافت نشد'],404);}
  $lbl=$t['from_p'].' ← '.$t['to_p'];
  if($t['owner_id']==$u['id']){
    foreach(q('SELECT user_id FROM members WHERE trip_id=?',[$id])->fetchAll() as $m){
      q('UPDATE users SET tokens=tokens+1 WHERE id=?',[$m['user_id']]);
      if($m['user_id']!=$u['id'])note($m['user_id'],"سفر $lbl توسط سازنده لغو شد و توکن شما بازگشت.");
    }
    q("UPDATE trips SET status='cancelled' WHERE id=?",[$id]);
  }else{
    q('DELETE FROM members WHERE trip_id=? AND user_id=?',[$id,$u['id']]);
    q('UPDATE users SET tokens=tokens+1 WHERE id=?',[$u['id']]);
    foreach(q('SELECT user_id FROM members WHERE trip_id=?',[$id])->fetchAll() as $m)note($m['user_id'],$u['name']." از سفر $lbl انصراف داد.");
  }
  $db->commit();out(['ok'=>1]);
case 'done':
  $u=need();
  if(!q("UPDATE trips SET status='done' WHERE id=? AND owner_id=? AND status='active' AND depart_at<=?",[(int)($in['id']??0),$u['id'],$now])->rowCount())out(['error'=>'فقط سازنده و بعد از زمان حرکت می‌تواند سفر را پایان دهد'],400);
  out(['ok'=>1]);
case 'mine':
  $u=need();
  $T=q("SELECT t.id,t.from_p,t.to_p,t.depart_at,t.cap,t.gender,t.status,t.owner_id FROM trips t JOIN members m ON m.trip_id=t.id WHERE m.user_id=? AND t.status<>'cancelled' ORDER BY t.depart_at DESC",[$u['id']])->fetchAll();
  foreach($T as $i=>$t)$T[$i]['members']=q('SELECT u.id,u.name,u.contact,EXISTS(SELECT 1 FROM ratings r WHERE r.trip_id=? AND r.rater_id=? AND r.ratee_id=u.id) rated FROM members m JOIN users u ON u.id=m.user_id WHERE m.trip_id=? AND u.id<>?',[$t['id'],$u['id'],$t['id'],$u['id']])->fetchAll();
  out(['trips'=>$T,'notes'=>q('SELECT text,created FROM notes WHERE user_id=? ORDER BY id DESC LIMIT 20',[$u['id']])->fetchAll()]);
case 'rate':
  $u=need();$id=(int)($in['trip']??0);$to=(int)($in['to']??0);$s=(int)($in['stars']??0);$tag=$in['tag']??null;
  if($s<1||$s>5||($tag&&!in_array($tag,['good','late','rude','noshow']))||$to===(int)$u['id'])out(['error'=>'ورودی نامعتبر'],400);
  $ok=q("SELECT COUNT(*) c FROM trips t JOIN members a ON a.trip_id=t.id AND a.user_id=? JOIN members b ON b.trip_id=t.id AND b.user_id=? WHERE t.id=? AND t.status='done'",[$u['id'],$to,$id])->fetch()['c'];
  if(!$ok)out(['error'=>'امکان امتیازدهی وجود ندارد'],400);
  q('INSERT INTO ratings(trip_id,rater_id,ratee_id,stars,tag) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE stars=VALUES(stars),tag=VALUES(tag)',[$id,$u['id'],$to,$s,$tag?:null]);
  out(['ok'=>1]);
// فقط ادمین: امتیازها هیچ‌جای دیگری به کاربران نمایش داده نمی‌شود
case 'admin_users':
  admin();
  out(q("SELECT u.id,u.name,u.sid,u.contact,u.tokens,u.blocked,u.role,ROUND(AVG(r.stars),1) avg,COUNT(r.id) n,COALESCE(SUM(r.tag IN ('late','rude','noshow')),0) bad FROM users u LEFT JOIN ratings r ON r.ratee_id=u.id GROUP BY u.id ORDER BY bad DESC,avg ASC")->fetchAll());
case 'admin_ratings':
  admin();
  out(q('SELECT r.stars,r.tag,r.created,ra.name rater,t.from_p,t.to_p FROM ratings r JOIN users ra ON ra.id=r.rater_id JOIN trips t ON t.id=r.trip_id WHERE r.ratee_id=? ORDER BY r.id DESC',[(int)($_GET['uid']??0)])->fetchAll());
case 'admin_block':
  admin();q("UPDATE users SET blocked=? WHERE id=? AND role<>'admin'",[empty($in['blocked'])?0:1,(int)($in['uid']??0)]);out(['ok'=>1]);
case 'admin_tokens':
  admin();q('UPDATE users SET tokens=GREATEST(0,tokens+?) WHERE id=?',[(int)($in['amount']??0),(int)($in['uid']??0)]);out(['ok'=>1]);
default: out(['error'=>'not found'],404);
}
