<?php
// Standalone checks with WordPress stubs; run directly with PHP, never load inside WordPress.
$root=dirname(__DIR__);
require "$root/src/Config.php"; require "$root/src/Admin.php"; require "$root/src/Queue.php";
use Arcwell\Core\Config; use Arcwell\Core\Admin; use Arcwell\Core\Queue;
$options=[]; $allowed=true; $nonce=true; $notice=''; $autoload=null; $response=null;
function get_option($key,$default=[]) {global $options; return $options[$key]??$default;}
function update_option($key,$value,$auto){global $options,$autoload;$options[$key]=$value;$autoload=$auto;}
function current_user_can($cap){global $allowed;return $allowed;}
function check_admin_referer($action){global $nonce;if(!$nonce)throw new RuntimeException('nonce');}
function check_ajax_referer($action,$field){check_admin_referer($action);}
function wp_die(...$args){throw new RuntimeException('denied');}
function wp_unslash($value){return $value;}
function wp_parse_url($value){return parse_url($value);}
function wp_get_environment_type(){return 'production';}
function get_current_user_id(){return 1;}
function set_transient($key,$value,$ttl){global $notice;$notice=$value;}
function admin_url($value){return $value;}
function wp_safe_redirect($url){throw new RuntimeException('redirect');}
function sanitize_text_field($value){return strip_tags($value);}
function nocache_headers(){}
function wp_send_json_success($data){global $response;$response=$data;throw new RuntimeException('json');}
function wp_send_json_error($data,$status){global $response;$response=['error'=>$status];throw new RuntimeException('json');}
function verify($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
$admin=new Admin(new Queue());
function save($values){global $admin;$_POST=['connection'=>$values];try{$admin->saveConnection();}catch(RuntimeException $e){if($e->getMessage()!=='redirect')throw $e;}}
foreach(Config::FIELDS as $key)putenv($key);
$valid=['ARCWELL_FRONTEND_URL'=>'https://example.com','ARCWELL_SOURCE_ID'=>'arcwell-test-123','ARCWELL_PREVIEW_SECRET'=>str_repeat('a',64),'ARCWELL_WEBHOOK_SECRET'=>str_repeat('b',64)];
save($valid);verify(Config::origin()==='https://example.com' && $autoload===false,'Save database fallback without autoload');
save(array_merge($valid,['ARCWELL_PREVIEW_SECRET'=>'']));verify(Config::value('ARCWELL_PREVIEW_SECRET')===str_repeat('a',64),'Blank secret preserves saved key');
$before=$options;save(array_merge($valid,['ARCWELL_FRONTEND_URL'=>'http://example.com','ARCWELL_SOURCE_ID'=>'new-source-id']));verify($before===$options,'Invalid production URL rejects entire update');
save(array_merge($valid,['ARCWELL_WEBHOOK_SECRET'=>str_repeat('a',64)]));verify($before===$options,'Identical secrets rejected');
putenv('ARCWELL_SOURCE_ID=host-source-123');save($valid);verify(Config::value('ARCWELL_SOURCE_ID')==='host-source-123','Host override wins over form');
define('ARCWELL_ENVIRONMENT','constant');putenv('ARCWELL_ENVIRONMENT=environment');verify(Config::value('ARCWELL_ENVIRONMENT')==='constant','Constant wins over environment');
$allowed=false;try{save($valid);throw new Exception('Permission not enforced');}catch(RuntimeException $e){verify($e->getMessage()==='denied','Save requires administrator');}$allowed=true;
$nonce=false;try{save($valid);throw new Exception('Nonce not enforced');}catch(RuntimeException $e){verify($e->getMessage()==='nonce','Save requires nonce');}$nonce=true;
$_SERVER['REQUEST_METHOD']='POST';$_POST=['key'=>'ARCWELL_PREVIEW_SECRET'];try{$admin->revealKey();}catch(RuntimeException $e){}verify($response['value']===str_repeat('a',64),'Explicit administrator reveal');
putenv('ARCWELL_PREVIEW_SECRET=host-secret');try{$admin->revealKey();}catch(RuntimeException $e){}verify($response===['error'=>400],'Hosting secret cannot be revealed');
$allowed=false;try{$admin->revealKey();}catch(RuntimeException $e){}verify($response===['error'=>403],'Reveal requires administrator');
