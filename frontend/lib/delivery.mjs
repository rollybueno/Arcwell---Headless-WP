import {mkdir,writeFile,stat} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import {resolve} from 'node:path';
import lockfile from 'proper-lockfile';
export function deliveryStore(directory=process.env.ARCWELL_DELIVERY_DIR||'.arcwell-deliveries') {
 return async function runOnce(key,work){
   const base=resolve(directory);await mkdir(base,{recursive:true,mode:0o700});
   const id=createHash('sha256').update(key).digest('hex');const done=resolve(base,id+'.done');
   const completed=async()=>{try{await stat(done);return true;}catch(e){if(e.code!=='ENOENT')throw e;return false;}};
   if(await completed())return;
   let compromised=null;
   const release=await lockfile.lock(done,{realpath:false,stale:30000,update:10000,retries:0,onCompromised:error=>{compromised=error;}});
   try {
     if(await completed())return;
     await work();
     if(compromised)throw compromised;
     await writeFile(done,new Date().toISOString(),{mode:0o600});
   }finally{await release();}
 };
}
