import {cookies,draftMode} from 'next/headers';
import {cache} from 'react';
import {readPreviewSession,previewHeaders} from '../../packages/arcwell-contract/server.mjs';
import {graphql} from './wp.mjs';
import {origin,cardFields,homeFields} from './content.mjs';
export const contractConfig=()=>({source:process.env.ARCWELL_SOURCE_ID||'',frontendOrigin:origin(),previewSecret:process.env.ARCWELL_PREVIEW_SECRET||'',webhookSecret:process.env.ARCWELL_WEBHOOK_SECRET||''});
export const previewCookie='arcwell_preview';
export async function retrievePreview(claims) {
 if(!process.env.WP_PREVIEW_USERNAME||!process.env.WP_PREVIEW_APP_PASSWORD)throw new Error('Preview account is not configured.');
 const type={post:'post',page:'page',arcwell_series:'arcwellSeries'}[claims.entityType];if(!type)throw new Error('Invalid preview type.');
 const fields=claims.entityType==='post'?cardFields+' content':claims.entityType==='page'?`databaseId title slug content arcwellPresentation {template values {heading body}} arcwellHomepage {${homeFields}}`:`databaseId title slug content arcwellPosts(first:50){nodes{${cardFields}}}`;
 const Authorization='Basic '+Buffer.from(process.env.WP_PREVIEW_USERNAME+':'+process.env.WP_PREVIEW_APP_PASSWORD).toString('base64');
 const data=await graphql(`query Preview($id:ID!){${type}(id:$id,idType:DATABASE_ID,asPreview:true){${fields}}}`,{id:String(claims.databaseId)},{private:true,headers:{Authorization,...previewHeaders(claims)}});
 if(!data[type])throw new Error('Preview content is not accessible.');return data[type];
}
export const previewForPath=cache(async(path)=>{
 if(!(await draftMode()).isEnabled)return null;
 const cookie=(await cookies()).get(previewCookie)?.value;if(!cookie)return null;
 try {
   // Untrusted decode supplies only the expected ID; signature verification precedes all authenticated reads.
   const id=JSON.parse(Buffer.from(cookie.split('.')[0],'base64url').toString()).databaseId;
   const claims=readPreviewSession(cookie,id,contractConfig());
   if(new URL(claims.frontendUrl).pathname.replace(/\/$/,'')!==path.replace(/\/$/,''))return null;
   return {claims,node:await retrievePreview(claims)};
 }catch{return null;}
});
