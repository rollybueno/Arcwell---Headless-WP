import test from 'node:test';
import assert from 'node:assert/strict';
import {html,safeUrl,publicNodes,text} from '../lib/content.mjs';
import {deliveryStore} from '../lib/delivery.mjs';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
test('content rendering removes script execution and unsafe embeds',()=>{
 const value=html('<script>alert(1)</script><img src="javascript:alert(1)" onerror="alert(2)"><a href="javascript:alert(1)">bad</a><iframe src="https://evil.example/embed"></iframe><p>Keep this</p>');
 assert(!value.includes('<script'));assert(!value.includes('onerror'));assert(!value.includes('javascript:'));assert(!value.includes('evil.example'));assert(value.includes('<p>Keep this</p>'));
});
test('missing images and unsafe URLs are rejected',()=>{for(const value of [null,undefined,'','javascript:alert(1)','data:text/html,test'])assert.equal(safeUrl(value),null);assert.equal(safeUrl('https://example.com/image.jpg'),'https://example.com/image.jpg');});
test('public collections exclude unavailable routes',()=>{assert.deepEqual(publicNodes({nodes:[{databaseId:1,frontendUrl:null},{databaseId:2,frontendUrl:'http://localhost:3000/articles/live/'}]}).map(n=>n.databaseId),[2]);assert.equal(text('<p>A &amp; B</p>'),'A & B');});
test('durable receiver acknowledges only completed work',async()=>{
 const dir=await mkdtemp(join(tmpdir(),'arcwell-delivery-'));const once=deliveryStore(dir);let calls=0;
 try{await assert.rejects(once('source:failure',async()=>{throw Error('cache unavailable');}));await once('source:failure',async()=>calls++);await once('source:failure',async()=>calls++);assert.equal(calls,1);
 let release;const work=new Promise(r=>release=r);const first=once('source:concurrent',()=>work);await new Promise(r=>setTimeout(r,30));await assert.rejects(once('source:concurrent',async()=>{}));release();await first;
 }finally{await rm(dir,{recursive:true,force:true});}
});
