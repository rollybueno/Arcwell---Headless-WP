import {NextResponse} from 'next/server';
import {cookies,draftMode} from 'next/headers';
import {verifyPreview,createPreviewSession} from '../../../../../packages/arcwell-contract/server.mjs';
import {contractConfig,previewCookie,retrievePreview} from '../../../../lib/preview.mjs';
export async function GET(request){
 const config=contractConfig();if(!config.source||config.previewSecret.length<32||!process.env.WP_PREVIEW_USERNAME||!process.env.WP_PREVIEW_APP_PASSWORD)return NextResponse.json({error:'Preview is not configured on the frontend yet.'},{status:503});
 const payload=request.nextUrl.searchParams.get('payload');const signature=request.nextUrl.searchParams.get('signature');let claims;
 try{claims=verifyPreview(payload,signature,config);}catch{return NextResponse.json({error:'This preview link is invalid or expired. Open a new preview from WordPress.'},{status:401});}
 try{await retrievePreview(claims);}catch{return NextResponse.json({error:'The preview account could not retrieve this content.'},{status:403});}
 (await draftMode()).enable();
 (await cookies()).set(previewCookie,createPreviewSession(payload,signature,config),{httpOnly:true,secure:config.frontendOrigin.startsWith('https:'),sameSite:'lax',path:'/',maxAge:1800});
 return NextResponse.redirect(claims.frontendUrl,303);
}
