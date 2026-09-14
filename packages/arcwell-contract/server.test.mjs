import test from 'node:test';
import assert from 'node:assert/strict';
import {createHmac} from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {execFileSync} from 'node:child_process';
import {verifyPreview,createPreviewSession,readPreviewSession,previewHeaders,verifyWebhook,receiveWebhook} from './server.mjs';
const config={source:'arcwell-test-instance',frontendOrigin:'https://arcwell.example',previewSecret:'test-preview-secret-at-least-32-bytes',webhookSecret:'test-webhook-secret-at-least-32-bytes'};
const now=1800000000;
const claims={v:1,source:config.source,audience:config.frontendOrigin,entityType:'post',databaseId:123,mode:'revision',revisionDatabaseId:456,featuredImageDatabaseId:0,issuedAt:now,expiresAt:now+300,frontendUrl:config.frontendOrigin+'/articles/example/'};
function signed(data=claims){const payload=Buffer.from(JSON.stringify(data)).toString('base64url');return [payload,createHmac('sha256',config.previewSecret).update('arcwell-preview-v1.'+payload).digest('hex')];}
function delivery(event){const body=JSON.stringify(event);return [body,{'x-arcwell-timestamp':String(now),'x-arcwell-event':event.event,'x-arcwell-signature':'sha256='+createHmac('sha256',config.webhookSecret).update(now+'.'+body).digest('hex')}];}
const event={version:'1',eventId:'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',source:config.source,event:'content.unpublished',occurredAt:new Date(now*1000).toISOString(),entity:{type:'post',id:123},before:{public:true,frontendUrl:claims.frontendUrl},after:{public:false},affected:{posts:[123],collections:['journal','homepage']}};
test('PHP launch signatures verify in JavaScript',()=>{
 const file=fileURLToPath(new URL('../../wordpress/wp-content/plugins/arcwell-core/src/Signer.php',import.meta.url));
 const php='require $argv[1]; echo json_encode(Arcwell\\Core\\Signer::preview(json_decode($argv[2], true), $argv[3]));';
 const value=JSON.parse(execFileSync('php',['-r',php,file,JSON.stringify(claims),config.previewSecret],{encoding:'utf8'}));
 assert.deepEqual(verifyPreview(value.payload,value.signature,config,now),claims);
});
test('preview rejects tampering, expiry, wrong source, and external redirects',()=>{
 const [p,s]=signed(); assert.throws(()=>verifyPreview(p+'A',s,config,now));
 assert.throws(()=>verifyPreview(p,s,config,now+300));
 assert.throws(()=>verifyPreview(p,s,{...config,source:'other'},now));
 for(const frontendUrl of ['https://evil.example/',config.frontendOrigin+'/\\evil.example/'])assert.throws(()=>verifyPreview(...signed({...claims,frontendUrl}),config,now));
});
test('session is purpose-separated, expiring, and scoped to content',()=>{
 const [p,s]=signed();const cookie=createPreviewSession(p,s,config,now);
 assert.equal(readPreviewSession(cookie,123,config,now+301).databaseId,123);
 assert.throws(()=>readPreviewSession(cookie,999,config,now));assert.throws(()=>readPreviewSession(cookie,123,config,now+1800));
 assert.throws(()=>readPreviewSession(p+'.'+s,123,config,now));
 assert.deepEqual(previewHeaders(claims),{'X-GraphQL-Preview':'database_id=123, featured_image_database_id=0','X-Arcwell-Revision':'456'});
});
test('webhook verifies exact bytes and rejects private details and unknown scopes',()=>{
 const [body,headers]=delivery(event);assert.deepEqual(verifyWebhook(body,headers,config,now),event);
 assert.throws(()=>verifyWebhook(body+' ',headers,config,now));assert.throws(()=>verifyWebhook(body,headers,config,now+301));
 assert.throws(()=>verifyWebhook(...delivery({...event,after:{public:false,frontendUrl:claims.frontendUrl}}),config,now));
 assert.throws(()=>verifyWebhook(...delivery({...event,affected:{collections:['everything']}}),config,now));
});
test('receiver delegates idempotency and propagates invalidation failures',async()=>{
 const completed=new Set();let calls=0;
 const adapters={runOnce:async(key,work)=>{if(!completed.has(key)){await work();completed.add(key);}},invalidate:async()=>{calls++;}};
 const args=delivery(event);await receiveWebhook(...args,config,adapters,now);await receiveWebhook(...args,config,adapters,now);assert.equal(calls,1);
 await assert.rejects(receiveWebhook(...args,config,{runOnce:async(key,work)=>work(),invalidate:async()=>{throw Error('cache unavailable');}},now));
});
