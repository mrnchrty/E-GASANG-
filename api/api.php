<?php
// E-GASANG — Main API Router
require_once __DIR__.'/config.php';
$res = $_GET['resource']??''; $action = $_GET['action']??'list'; $method = $_SERVER['REQUEST_METHOD'];

switch($res){
case 'residents':   doResidents($action);   break;
case 'announcements': doAnnouncements($action); break;
case 'requests':    doRequests($action);    break;
case 'doctypes':    doDocTypes($action);    break;
case 'settings':    doSettings($action);    break;
case 'dashboard':   doDashboard();          break;
case 'users':       doUsers($action);       break;
case 'notifications': doNotifications($action); break;
case 'logs':        doLogs();               break;
default: jsonError("Unknown resource: $res",404);
}

// ── RESIDENTS ──────────────────────────────────────────────
function doResidents(string $action): void {
    $u = requireAuth();
    $db = getDB();
    switch($action) {
        case 'list':
            $zone=$_GET['zone']??''; $status=$_GET['status']??''; $q=$_GET['q']??'';
            $page=max(1,(int)($_GET['page']??1)); $limit=min(50,(int)($_GET['limit']??20));
            $off=($page-1)*$limit;
            $w=['1=1']; $p=[];
            if($zone){$w[]='zone=?';$p[]=$zone;}
            if($status){$w[]='status=?';$p[]=$status;}
            if($q){$w[]='(full_name LIKE ? OR resident_id LIKE ? OR contact_number LIKE ?)';$like="%$q%";$p[]=$like;$p[]=$like;$p[]=$like;}
            $cond=implode(' AND ',$w);
            $cs=$db->prepare("SELECT COUNT(*) FROM residents WHERE $cond"); $cs->execute($p); $tot=(int)$cs->fetchColumn();
            $s=$db->prepare("SELECT * FROM residents WHERE $cond ORDER BY full_name LIMIT $limit OFFSET $off"); $s->execute($p);
            jsonSuccess(['residents'=>$s->fetchAll(),'total'=>$tot,'page'=>$page,'limit'=>$limit,'total_pages'=>ceil($tot/$limit)]);
        case 'get':
            $id=$_GET['id']??''; if(!$id) jsonError('ID required');
            $s=$db->prepare("SELECT * FROM residents WHERE id=? OR resident_id=? LIMIT 1"); $s->execute([$id,$id]); $r=$s->fetch();
            if(!$r) jsonError('Not found',404);
            $rq=$db->prepare("SELECT dr.*,dt.name as doc_name FROM document_requests dr JOIN document_types dt ON dr.document_type_id=dt.id WHERE dr.resident_id=? ORDER BY dr.created_at DESC");
            $rq->execute([$r['resident_id']]); $r['requests']=$rq->fetchAll();
            jsonSuccess($r);
        case 'add':
            requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true);
            $req=['full_name','date_of_birth','gender','address','zone','civil_status'];
            foreach($req as $f) if(empty($d[$f])) jsonError("Field '$f' required.");
            $newId='BRGY-'.date('Y').'-'.str_pad((int)$db->query("SELECT COUNT(*) FROM residents")->fetchColumn()+1,5,'0',STR_PAD_LEFT);
            $db->prepare("INSERT INTO residents(resident_id,full_name,date_of_birth,gender,address,zone,purok,contact_number,email,civil_status,occupation,vaccination_status,is_pregnant,is_pwd,is_senior_citizen,is_voter,is_4ps_beneficiary,date_registered,status)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),'Active')")
               ->execute([$newId,$d['full_name'],$d['date_of_birth'],$d['gender'],$d['address'],$d['zone'],$d['purok']??null,$d['contact_number']??null,$d['email']??null,$d['civil_status'],$d['occupation']??null,$d['vaccination_status']??'Not vaccinated',$d['is_pregnant']??0,$d['is_pwd']??0,$d['is_senior_citizen']??0,$d['is_voter']??0,$d['is_4ps_beneficiary']??0]);
            $id=(int)$db->lastInsertId(); $admin=$GLOBALS['_SESSION']['user']??['id'=>0,'name'=>'Admin'];
            logAct($admin['id']??0,$admin['name']??'Admin',"Added resident: {$d['full_name']}",'resident',$id,"Zone: {$d['zone']}");
            jsonSuccess(['resident_id'=>$newId,'id'=>$id],'Resident added',201);
        case 'update':
            requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true); $id=$d['id']??''; if(!$id) jsonError('ID required');
            $fields=['full_name','date_of_birth','gender','address','zone','purok','contact_number','email','civil_status','occupation','vaccination_status','is_pregnant','is_pwd','is_senior_citizen','is_voter','is_4ps_beneficiary','status'];
            $sets=[]; $params=[];
            foreach($fields as $f) if(array_key_exists($f,$d)){$sets[]="$f=?";$params[]=$d[$f];}
            if(empty($sets)) jsonError('Nothing to update');
            $params[]=$id; $db->prepare("UPDATE residents SET ".implode(',',$sets)." WHERE id=?")->execute($params);
            jsonSuccess(null,'Updated');
        case 'delete':
            requireAdmin();
            $id=$_GET['id']??(json_decode(file_get_contents('php://input'),true)['id']??'');
            if(!$id) jsonError('ID required');
            $db->prepare("UPDATE residents SET status='Inactive' WHERE id=?")->execute([$id]);
            jsonSuccess(null,'Deactivated');
        case 'stats':
            $stats=[
                'total'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE status='Active'")->fetchColumn(),
                'male'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE gender='Male' AND status='Active'")->fetchColumn(),
                'female'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE gender='Female' AND status='Active'")->fetchColumn(),
                'seniors'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE is_senior_citizen=1 AND status='Active'")->fetchColumn(),
                'pwd'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE is_pwd=1 AND status='Active'")->fetchColumn(),
                'pregnant'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE is_pregnant=1 AND status='Active'")->fetchColumn(),
                'new_month'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE date_registered>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn(),
            ];
            $zones=$db->query("SELECT zone,COUNT(*) as count FROM residents WHERE status='Active' GROUP BY zone ORDER BY zone")->fetchAll();
            $stats['by_zone']=$zones;
            jsonSuccess($stats);
        default: jsonError('Unknown action',404);
    }
}

// ── ANNOUNCEMENTS ──────────────────────────────────────────
function doAnnouncements(string $action): void {
    $db=getDB();
    switch($action){
        case 'list':
            requireAuth();
            $type=$_GET['type']??''; $active=$_GET['active']??'';
            $w=['1=1']; $p=[];
            if($type){$w[]='type=?';$p[]=$type;}
            if($active!==''){$w[]='is_active=?';$p[]=(int)$active;}
            $cond=implode(' AND ',$w);
            $s=$db->prepare("SELECT a.*,u.name as posted_by_name FROM announcements a LEFT JOIN users u ON a.posted_by=u.id WHERE $cond ORDER BY is_pinned DESC,created_at DESC"); $s->execute($p);
            jsonSuccess($s->fetchAll());
            break;
        case 'add':
            $admin=requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true);
            if(empty($d['title'])||empty($d['content'])) jsonError('Title and content required.');
            $db->prepare("INSERT INTO announcements(title,type,content,date_posted,posted_by,is_pinned,is_active)VALUES(?,?,?,NOW(),?,?,1)")
               ->execute([$d['title'],$d['type']??'general',$d['content'],$admin['id'],$d['is_pinned']??0]);
            $id=(int)$db->lastInsertId();
            logAct($admin['id'],$admin['name'],"Posted: {$d['title']}",'announcement',$id,"Type: {$d['type']??'general'}");
            jsonSuccess(['id'=>$id],'Posted',201);
            break;
        case 'update':
            $admin=requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true); $id=$d['id']??''; if(!$id) jsonError('ID required');
            $db->prepare("UPDATE announcements SET title=?,type=?,content=?,is_pinned=?,is_active=? WHERE id=?")
               ->execute([$d['title'],$d['type'],$d['content'],$d['is_pinned']??0,$d['is_active']??1,$id]);
            jsonSuccess(null,'Updated');
            break;
        case 'toggle':
            requireAdmin();
            $id=(json_decode(file_get_contents('php://input'),true)['id']??''); if(!$id) jsonError('ID required');
            $db->prepare("UPDATE announcements SET is_active=NOT is_active WHERE id=?")->execute([$id]);
            jsonSuccess(null,'Toggled');
            break;
        case 'delete':
            requireAdmin();
            $id=$_GET['id']??(json_decode(file_get_contents('php://input'),true)['id']??''); if(!$id) jsonError('ID required');
            $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
            jsonSuccess(null,'Deleted');
            break;
        default: jsonError('Unknown action',404);
    }
}

// ── REQUESTS ───────────────────────────────────────────────
function doRequests(string $action): void {
    $db=getDB();
    switch($action){
        case 'list':
            $u=requireAuth();
            $status=$_GET['status']??''; $rid=$_GET['resident_id']??''; $q=$_GET['q']??'';
            $w=['1=1']; $p=[];
            if($u['role']==='resident'&&!$rid){$rid=$u['resident_id'];}
            if($status){$w[]='dr.status=?';$p[]=$status;}
            if($rid){$w[]='dr.resident_id=?';$p[]=$rid;}
            if($q){$w[]='(r.full_name LIKE ? OR dr.request_number LIKE ? OR dt.name LIKE ?)';$like="%$q%";$p[]=$like;$p[]=$like;$p[]=$like;}
            $cond=implode(' AND ',$w);
            $s=$db->prepare("SELECT dr.*,r.full_name as resident_name,dt.name as doc_type_name,dt.fee as doc_fee FROM document_requests dr JOIN residents r ON dr.resident_id=r.resident_id JOIN document_types dt ON dr.document_type_id=dt.id WHERE $cond ORDER BY dr.created_at DESC"); $s->execute($p);
            jsonSuccess($s->fetchAll());
            break;
        case 'add':
            $u=requireAuth();
            $d=json_decode(file_get_contents('php://input'),true);
            if(empty($d['resident_id'])||empty($d['document_type_id'])) jsonError('resident_id and document_type_id required.');
            $dt=$db->prepare("SELECT * FROM document_types WHERE id=?"); $dt->execute([$d['document_type_id']]); $docType=$dt->fetch();
            if(!$docType) jsonError('Invalid document type');
            $cnt=(int)$db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
            $reqNum='#'.str_pad($cnt+1001,4,'0',STR_PAD_LEFT);
            $pm=($d['payment_method']??'cash')==='gcash'?'gcash':'cash';
            $db->prepare("INSERT INTO document_requests(request_number,resident_id,document_type_id,status,purpose,fee,payment_method,payment_status,requested_on,update_on)VALUES(?,?,?,'pending',?,?,?,?,CURDATE(),CURDATE())")
               ->execute([$reqNum,$d['resident_id'],$d['document_type_id'],$d['purpose']??null,$docType['fee'],$pm,$pm==='gcash'?'paid':'unpaid']);
            $newId=(int)$db->lastInsertId();
            // Add notification
            $userRow=$db->prepare("SELECT id FROM users WHERE resident_id=? LIMIT 1"); $userRow->execute([$d['resident_id']]); $usr=$userRow->fetch();
            if($usr) $db->prepare("INSERT INTO notifications(user_id,title,message,type,related_id)VALUES(?,'Request submitted','Your request $reqNum for {$docType['name']} has been submitted.','request',?)")->execute([$usr['id'],$newId]);
            jsonSuccess(['id'=>$newId,'request_number'=>$reqNum],'Request submitted',201);
            break;
        case 'update_status':
            $admin=requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true); $id=$d['id']??''; $status=$d['status']??''; if(!$id||!$status) jsonError('ID and status required.');
            $allowed=['pending','processing','ready','completed','rejected'];
            if(!in_array($status,$allowed)) jsonError('Invalid status');
            $db->prepare("UPDATE document_requests SET status=?,admin_notes=?,update_on=CURDATE(),processed_by=?,updated_at=NOW() WHERE id=?")
               ->execute([$status,$d['notes']??null,$admin['id'],$id]);
            $rq=$db->prepare("SELECT dr.*,r.full_name FROM document_requests dr JOIN residents r ON dr.resident_id=r.resident_id WHERE dr.id=?"); $rq->execute([$id]); $r=$rq->fetch();
            logAct($admin['id'],$admin['name'],"Updated {$r['request_number']} to $status",'request',$id);
            // Notify resident
            $userRow=$db->prepare("SELECT id FROM users WHERE resident_id=? LIMIT 1"); $userRow->execute([$r['resident_id']]); $usr=$userRow->fetch();
            if($usr){
                $msgs=['processing'=>'Your request is now being processed.','ready'=>'Your document is ready for pickup!','completed'=>'Your request has been completed.','rejected'=>'Your request was not approved.'];
                $msg=$msgs[$status]??'Your request status has been updated.';
                $db->prepare("INSERT INTO notifications(user_id,title,message,type,related_id)VALUES(?,?,?,'request',?)")->execute([$usr['id'],'Request '.$status,$msg,$id]);
            }
            jsonSuccess(null,'Updated');
            break;
        case 'stats':
            requireAdmin();
            $stats=['total'=>(int)$db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn()];
            foreach(['pending','processing','ready','completed','rejected'] as $s) $stats[$s]=(int)$db->query("SELECT COUNT(*) FROM document_requests WHERE status='$s'")->fetchColumn();
            $byType=$db->query("SELECT dt.name,COUNT(*) as count FROM document_requests dr JOIN document_types dt ON dr.document_type_id=dt.id GROUP BY dt.name ORDER BY count DESC")->fetchAll();
            $stats['by_type']=$byType;
            jsonSuccess($stats);
            break;
        default: jsonError('Unknown action',404);
    }
}

// ── DOCUMENT TYPES ─────────────────────────────────────────
function doDocTypes(string $action): void {
    $db=getDB();
    switch($action){
        case 'list':
            requireAuth();
            $enabled=$_GET['enabled']??'';
            $w='1=1'; $p=[];
            if($enabled!==''){$w='is_enabled=?';$p[]=(int)$enabled;}
            $s=$db->prepare("SELECT * FROM document_types WHERE $w ORDER BY sort_order,id"); $s->execute($p);
            $rows=$s->fetchAll();
            // array_values() keeps this a JSON array (not an object) so the
            // frontend can safely call .map()/.join() on it.
            foreach($rows as &$r) $r['requirements_arr']=array_values(array_filter(array_map('trim',explode("\n",$r['requirements']??''))));
            jsonSuccess($rows);
            break;
        case 'add':
            requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true);
            if(empty($d['name'])) jsonError('Name required');
            $reqs=is_array($d['requirements'])?implode("\n",$d['requirements']):($d['requirements']??'');
            $db->prepare("INSERT INTO document_types(name,description,requirements,processing_time,fee,is_enabled)VALUES(?,?,?,?,?,1)")
               ->execute([$d['name'],$d['description']??'',$reqs,$d['processing_time']??'3-5 business days',$d['fee']??'Free']);
            jsonSuccess(['id'=>(int)$db->lastInsertId()],'Added',201);
            break;
        case 'update':
            requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true); $id=$d['id']??''; if(!$id) jsonError('ID required');
            $reqs=is_array($d['requirements']??null)?implode("\n",$d['requirements']):($d['requirements']??'');
            $db->prepare("UPDATE document_types SET name=?,description=?,requirements=?,processing_time=?,fee=?,is_enabled=? WHERE id=?")
               ->execute([$d['name'],$d['description'],$reqs,$d['processing_time'],$d['fee'],$d['is_enabled']??1,$id]);
            jsonSuccess(null,'Updated');
            break;
        case 'toggle':
            requireAdmin();
            $id=(json_decode(file_get_contents('php://input'),true)['id']??''); if(!$id) jsonError('ID required');
            $db->prepare("UPDATE document_types SET is_enabled=NOT is_enabled WHERE id=?")->execute([$id]);
            jsonSuccess(null,'Toggled');
            break;
        default: jsonError('Unknown action',404);
    }
}

// ── SETTINGS ───────────────────────────────────────────────
function doSettings(string $action): void {
    $db=getDB();
    if($action==='get'){
        requireAuth();
        $rows=$db->query("SELECT setting_key,setting_value FROM settings")->fetchAll();
        $out=[]; foreach($rows as $r) $out[$r['setting_key']]=$r['setting_value'];
        jsonSuccess($out);
    }
    if($action==='update'){
        $admin=requireAdmin();
        $d=json_decode(file_get_contents('php://input'),true);
        foreach($d as $k=>$v) $db->prepare("INSERT INTO settings(setting_key,setting_value)VALUES(?,?)ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
        logAct($admin['id'],$admin['name'],'Updated system settings','settings');
        jsonSuccess(null,'Saved');
    }
    jsonError('Unknown action',404);
}

// ── DASHBOARD ──────────────────────────────────────────────
function doDashboard(): void {
    requireAuth(); $db=getDB();
    $data=[
        'residents_total'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE status='Active'")->fetchColumn(),
        'new_this_month'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE date_registered>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn(),
        'vaccinated'=>(int)$db->query("SELECT setting_value FROM settings WHERE setting_key='vaccinated_count'")->fetchColumn(),
        'senior_citizens'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE is_senior_citizen=1 AND status='Active'")->fetchColumn(),
        'pregnant_women'=>(int)$db->query("SELECT COUNT(*) FROM residents WHERE is_pregnant=1 AND status='Active'")->fetchColumn(),
        'requests_total'=>(int)$db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn(),
        'requests_pending'=>(int)$db->query("SELECT COUNT(*) FROM document_requests WHERE status='pending'")->fetchColumn(),
        'announcements_active'=>(int)$db->query("SELECT COUNT(*) FROM announcements WHERE is_active=1")->fetchColumn(),
        'households'=>(int)$db->query("SELECT setting_value FROM settings WHERE setting_key='households_total'")->fetchColumn(),
    ];
    $data['population_by_zone']=$db->query("SELECT zone,COUNT(*) as count FROM residents WHERE status='Active' GROUP BY zone ORDER BY zone")->fetchAll();
    $data['status_dist']=$db->query("SELECT status,COUNT(*) as count FROM document_requests GROUP BY status")->fetchAll();
    $data['recent_requests']=$db->query("SELECT dr.*,r.full_name as resident_name,dt.name as doc_type_name FROM document_requests dr JOIN residents r ON dr.resident_id=r.resident_id JOIN document_types dt ON dr.document_type_id=dt.id ORDER BY dr.created_at DESC LIMIT 7")->fetchAll();
    $data['recent_announcements']=$db->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();
    jsonSuccess($data);
}

// ── USERS ──────────────────────────────────────────────────
function doUsers(string $action): void {
    $db=getDB();
    switch($action){
        case 'list':
            requireAdmin();
            $rows=$db->query("SELECT id,name,email,role,resident_id,status,last_active,created_at FROM users ORDER BY role,name")->fetchAll();
            jsonSuccess($rows);
            break;
        case 'add':
            $admin=requireAdmin();
            $d=json_decode(file_get_contents('php://input'),true);
            if(empty($d['name'])||empty($d['email'])||empty($d['password'])) jsonError('Name, email, password required.');
            $chk=$db->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$d['email']]);
            if($chk->fetch()) jsonError('Email already exists.');
            $hash=password_hash($d['password'],PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users(name,email,password,role,resident_id)VALUES(?,?,?,?,?)")
               ->execute([$d['name'],$d['email'],$hash,$d['role']??'resident',$d['resident_id']??null]);
            logAct($admin['id'],$admin['name'],"Added user: {$d['name']}",'user');
            jsonSuccess(null,'User added',201);
            break;
        case 'toggle':
            requireAdmin();
            $id=(json_decode(file_get_contents('php://input'),true)['id']??''); if(!$id) jsonError('ID required');
            $db->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            jsonSuccess(null,'Toggled');
            break;
        default: jsonError('Unknown action',404);
    }
}

// ── NOTIFICATIONS ─────────────────────────────────────────
function doNotifications(string $action): void {
    $u=requireAuth(); $db=getDB();
    if($action==='list'){
        $s=$db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20"); $s->execute([$u['id']]);
        $notifs=$s->fetchAll();
        $unread=(int)$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$u['id']])?$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$u['id']]):0;
        $uc=$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0"); $uc->execute([$u['id']]); $unread=(int)$uc->fetchColumn();
        jsonSuccess(['notifications'=>$notifs,'unread'=>$unread]);
    }
    if($action==='mark_read'){
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$u['id']]);
        jsonSuccess(null,'Marked read');
    }
    jsonError('Unknown action',404);
}

// ── LOGS ───────────────────────────────────────────────────
function doLogs(): void {
    requireAdmin(); $db=getDB();
    $limit=(int)($_GET['limit']??50);
    $rows=$db->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT $limit");
    $rows->execute(); jsonSuccess($rows->fetchAll());
}
