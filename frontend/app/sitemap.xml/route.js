import {graphql} from '../../lib/wp.mjs';
import {origin} from '../../lib/content.mjs';
const escape=s=>s.replace(/[<>&"']/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&apos;'}[c]));
export async function GET(){
 const urls=new Set([origin()+'/',origin()+'/journal/',origin()+'/topics/',origin()+'/series/']);
 for(const type of ['posts','pages','arcwellSeriesItems']){let after=null;do{const data=await graphql(`query Map($after:String){${type}(first:100,after:$after){nodes{frontendUrl} pageInfo{hasNextPage endCursor}}}`,{after});for(const node of data[type].nodes)if(node.frontendUrl)urls.add(node.frontendUrl);after=data[type].pageInfo.hasNextPage?data[type].pageInfo.endCursor:null;}while(after);}
 return new Response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+[...urls].map(url=>'<url><loc>'+escape(url)+'</loc></url>').join('')+'</urlset>',{headers:{'Content-Type':'application/xml; charset=utf-8'}});
}
