/** Server-only Arcwell contract helpers. No WordPress credentials or secrets belong in browser bundles. */
import { createHmac, timingSafeEqual } from 'node:crypto';
const events = new Set(['content.published','content.updated','content.unpublished','content.deleted','taxonomy.updated','taxonomy.deleted','author.updated','author.deleted','media.updated','media.deleted','homepage.updated','site.updated','navigation.updated','arcwell.test']);
const types = new Set(['post','page','arcwell_series','attachment','category','post_tag','arcwell_topic','user','site','navigation']);
const scopes = new Set(['journal','homepage','navigation','site','media-dependent-content','taxonomy-dependent-content','author-dependent-content','page-routes','series']);
const ids = value => Array.isArray(value) && value.length <= 1000 && value.every(id => Number.isSafeInteger(id) && id > 0);
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
function assert(condition, message) { if (!condition) throw new Error(message); }
function hmac(message, secret) { assert(typeof secret === 'string' && Buffer.byteLength(secret) >= 32, 'Secret must contain at least 32 bytes.'); return createHmac('sha256',secret).update(message).digest('hex'); }
function same(actual, expected) { return typeof actual === 'string' && /^[a-f0-9]{64}$/.test(actual) && timingSafeEqual(Buffer.from(actual),Buffer.from(expected)); }
function approvedUrl(value, origin) { if(typeof value!=='string'||!value.startsWith(origin+'/'))return false;try {return new URL(value).origin === origin && !/[\r\n\\]/.test(value);}catch{return false;} }
function decodeSigned(payload, signature, secret, purpose) {
    assert(typeof payload === 'string' && payload.length <= 4096 && /^[A-Za-z0-9_-]+$/.test(payload), 'Invalid payload.');
    assert(same(signature,hmac(purpose+'.'+payload,secret)), 'Invalid signature.');
    const data=JSON.parse(Buffer.from(payload,'base64url').toString('utf8'));
    assert(object(data),'Invalid claims.');return data;
}
function validateClaims(data, config, now, ttl) {
    const required=['v','source','audience','entityType','databaseId','mode','revisionDatabaseId','featuredImageDatabaseId','issuedAt','expiresAt','frontendUrl'];
    assert(Object.keys(data).length===required.length && required.every(key=>Object.hasOwn(data,key)), 'Unexpected claims.');
    assert(data.v===1&&data.source===config.source&&data.audience===config.frontendOrigin,'Wrong version, source or audience.');
    assert(['post','page','arcwell_series'].includes(data.entityType)&&Number.isSafeInteger(data.databaseId)&&data.databaseId>0,'Invalid content identity.');
    assert(['saved','autosave','revision'].includes(data.mode),'Invalid preview mode.');
    assert(Number.isSafeInteger(data.issuedAt)&&Number.isSafeInteger(data.expiresAt)&&data.issuedAt<=now+30&&data.expiresAt>now&&data.expiresAt>data.issuedAt&&data.expiresAt-data.issuedAt<=ttl,'Expired or invalid preview lifetime.');
    assert(data.revisionDatabaseId===null||(Number.isSafeInteger(data.revisionDatabaseId)&&data.revisionDatabaseId>0),'Invalid revision.');
    assert(data.featuredImageDatabaseId===null||(Number.isSafeInteger(data.featuredImageDatabaseId)&&data.featuredImageDatabaseId>=0),'Invalid image.');
    assert(data.mode!=='revision'||data.revisionDatabaseId!==null,'Revision required.');
    assert(approvedUrl(data.frontendUrl,config.frontendOrigin),'Invalid destination.');
    return data;
}
export function verifyPreview(payload, signature, config, now=Math.floor(Date.now()/1000)) {
    return validateClaims(decodeSigned(payload,signature,config.previewSecret,'arcwell-preview-v1'),config,now,300);
}
/** Call only after successfully retrieving the authorized target from WordPress. */
export function createPreviewSession(payload, signature, config, now=Math.floor(Date.now()/1000)) {
    const claims={...verifyPreview(payload,signature,config,now),issuedAt:now,expiresAt:now+1800};
    const encoded=Buffer.from(JSON.stringify(claims)).toString('base64url');
    return encoded+'.'+hmac('arcwell-session-v1.'+encoded,config.previewSecret);
}
export function readPreviewSession(cookie, expectedId, config, now=Math.floor(Date.now()/1000)) {
    assert(typeof cookie==='string'&&cookie.split('.').length===2,'Invalid preview session.');
    const [payload,signature]=cookie.split('.');
    const claims=validateClaims(decodeSigned(payload,signature,config.previewSecret,'arcwell-session-v1'),config,now,1800);
    assert(claims.databaseId===expectedId,'Preview session does not authorize this content.');
    return claims;
}
export function previewHeaders(claims) {
    return {'X-GraphQL-Preview':'database_id='+claims.databaseId+(claims.featuredImageDatabaseId!==null?', featured_image_database_id='+claims.featuredImageDatabaseId:''),
        'X-Arcwell-Revision':String(claims.revisionDatabaseId||0)};
}
export function verifyWebhook(rawBody, headers, config, now=Math.floor(Date.now()/1000)) {
    assert(typeof rawBody==='string'&&Buffer.byteLength(rawBody)<=262144,'Invalid body size.');
    const read=name=>typeof headers.get==='function'?headers.get(name):headers[name.toLowerCase()];
    const stamp=read('x-arcwell-timestamp');
    assert(typeof stamp==='string'&&/^\d{1,12}$/.test(stamp),'Invalid timestamp.');
    const timestamp=Number(stamp);
    assert(timestamp<=now+30&&timestamp>=now-300,'Stale timestamp.');
    const signature=read('x-arcwell-signature');
    assert(typeof signature==='string'&&signature.startsWith('sha256=')&&same(signature.slice(7),hmac(stamp+'.'+rawBody,config.webhookSecret)),'Invalid webhook signature.');
    const event=JSON.parse(rawBody);
    assert(object(event)&&event.version==='1'&&event.source===config.source&&events.has(event.event),'Unsupported webhook contract.');
    assert(typeof event.eventId==='string'&&/^[a-f0-9-]{36}$/.test(event.eventId)&&typeof event.occurredAt==='string'&&Number.isFinite(Date.parse(event.occurredAt)),'Invalid event identity.');
    assert(read('x-arcwell-event')===event.event,'Event header mismatch.');
    const keys=['version','eventId','source','event','occurredAt'];
    if(event.event!=='arcwell.test') {
        keys.push('entity','before','after','affected');
        assert(object(event.entity)&&types.has(event.entity.type)&&Number.isSafeInteger(event.entity.id)&&event.entity.id>=0,'Invalid entity.');
        for(const name of ['before','after']) {
            const state=event[name];if(state===null)continue;
            assert(object(state)&&typeof state.public==='boolean','Invalid public snapshot.');
            assert(Object.keys(state).every(key=>['public','frontendUrl','authors','categories','tags','topics','series','posts','media'].includes(key)),'Unexpected snapshot field.');
            assert(state.public||Object.keys(state).length===1,'Private snapshot contains extra data.');
            if(state.frontendUrl!=null)assert(approvedUrl(state.frontendUrl,config.frontendOrigin),'Invalid public URL.');
            for(const [key,value] of Object.entries(state))if(!['public','frontendUrl'].includes(key))assert(ids(value),'Invalid dependency IDs.');
        }
        assert(object(event.affected),'Missing dependencies.');
        for(const [key,value] of Object.entries(event.affected)) {
            assert(['posts','pages','authors','categories','tags','topics','series','media','collections'].includes(key),'Unknown dependency.');
            if(key==='collections')assert(Array.isArray(value)&&value.every(scope=>scopes.has(scope)),'Unknown collection scope.');
            else assert(ids(value),'Invalid affected IDs.');
        }
    }
    assert(Object.keys(event).every(key=>keys.includes(key)),'Unexpected event field.');
    return event;
}
/** runOnce must use a durable atomic idempotency store and record success only after work finishes. */
export async function receiveWebhook(rawBody, headers, config, {runOnce, invalidate}, now) {
    const event=verifyWebhook(rawBody,headers,config,now);
    await runOnce(event.source+':'+event.eventId,async()=>{if(event.event!=='arcwell.test')await invalidate(event);});
    return {ok:true,eventId:event.eventId};
}
