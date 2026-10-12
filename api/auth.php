<?php
require_once __DIR__.'/config.php';
startSess();
$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $d = json_decode(file_get_contents('php://input'),true);
    $email = trim($d['email']??''); $pass = trim($d['password']??''); $role = trim($d['role']??'resident');
    if(!$email||!$pass) jsonError('Email and password required.');
    $db=getDB(); $s=$db->prepare("SELECT * FROM users WHERE email=? AND status='active' LIMIT 1"); $s->execute([$email]); $u=$s->fetch();
    if(!$u||!password_verify($pass,$u['password'])) jsonError('Invalid email or password.');
    if($role==='resident' && $u['role']!=='resident') jsonError('This account is not a resident.');
    if($role==='admin' && $u['role']==='resident') jsonError('This account is not an admin/staff.');
    $res=null;
    if($u['resident_id']){$rs=$db->prepare("SELECT * FROM residents WHERE resident_id=? LIMIT 1");$rs->execute([$u['resident_id']]);$res=$rs->fetch();}
    $notifCount=0;
    if($u['role']==='resident'){$ns=$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");$ns->execute([$u['id']]);$notifCount=(int)$ns->fetchColumn();}
    $sess=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role'],'resident_id'=>$u['resident_id'],'avatar'=>$u['avatar']];
    $_SESSION['user']=$sess;
    jsonSuccess(['user'=>$sess,'resident'=>$res,'notif_count'=>$notifCount],'Login successful');
}

if ($action === 'register') {
    $d = json_decode(file_get_contents('php://input'),true);
    $name=trim($d['full_name']??''); $email=trim($d['email']??''); $pass=trim($d['password']??'');
    $dob=$d['date_of_birth']??''; $gender=$d['gender']??''; $address=$d['address']??'';
    $contact=$d['contact_number']??''; $civil=$d['civil_status']??''; $occ=$d['occupation']??'';
    if(!$name||!$email||!$pass||!$dob||!$gender||!$address) jsonError('All required fields must be filled.');
    $db=getDB(); $chk=$db->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]);
    if($chk->fetch()) jsonError('Email already registered.');
    $newId='BRGY-'.date('Y').'-'.str_pad((int)$db->query("SELECT COUNT(*) FROM residents")->fetchColumn()+1,5,'0',STR_PAD_LEFT);
    $db->prepare("INSERT INTO residents(resident_id,full_name,date_of_birth,gender,address,zone,contact_number,civil_status,occupation,date_registered,status)VALUES(?,?,?,?,?,?,?,?,?,CURDATE(),'Active')")
       ->execute([$newId,$name,$dob,$gender,$address,$d['zone']??'Zone 1',$contact,$civil?:'Single',$occ]);
    $hash=password_hash($pass,PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users(name,email,password,role,resident_id)VALUES(?,?,?,'resident',?)")->execute([$name,$email,$hash,$newId]);
    jsonSuccess(['resident_id'=>$newId],'Registration successful',201);
}

if ($action === 'logout') { session_destroy(); jsonSuccess(null,'Logged out'); }
if ($action === 'me') {
    if(empty($_SESSION['user'])) jsonError('Not logged in',401);
    $u=$_SESSION['user']; $db=getDB(); $res=null;
    if($u['resident_id']){$s=$db->prepare("SELECT * FROM residents WHERE resident_id=? LIMIT 1");$s->execute([$u['resident_id']]);$res=$s->fetch();}
    jsonSuccess(['user'=>$u,'resident'=>$res]);
}
jsonError('Unknown action',404);
