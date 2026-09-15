import {NextResponse} from 'next/server';
import {revalidateTag} from 'next/cache';
import {receiveWebhook,verifyWebhook} from '../../../../../packages/arcwell-contract/server.mjs';
import {contractConfig} from '../../../../lib/preview.mjs';
import {deliveryStore} from '../../../../lib/delivery.mjs';
export const runtime='nodejs';
export async function POST(request){
 const config=contractConfig();if(!config.source||config.webhookSecret.length<32)return NextResponse.json({error:'Publishing connection is not configured on the frontend yet.'},{status:503});
 let body='';let size=0;const reader=request.body?.getReader();if(!reader)return NextResponse.json({error:'Missing body.'},{status:400});
 const decoder=new TextDecoder();while(true){const {done,value}=await reader.read();if(done)break;size+=value.byteLength;if(size>262144){await reader.cancel();return NextResponse.json({error:'Body too large.'},{status:413});}body+=decoder.decode(value,{stream:true});}body+=decoder.decode();
 try{verifyWebhook(body,request.headers,config);}catch{return NextResponse.json({error:'Invalid publishing event or signature.'},{status:401});}
 try{const result=await receiveWebhook(body,request.headers,config,{runOnce:deliveryStore(),invalidate:async()=>{revalidateTag('arcwell',{expire:0});}});return NextResponse.json(result);}
 catch{return NextResponse.json({error:'Unable to complete delivery. Please retry.'},{status:503});}
}
