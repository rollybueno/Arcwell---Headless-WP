import sanitizeHtml from 'sanitize-html';
export const origin = () => (process.env.FRONTEND_URL || 'http://localhost:3000').replace(/\/$/, '');
export const cmsEndpoint = () => process.env.WP_GRAPHQL_URL || 'http://localhost:10048/arcwell/graphql';
export function text(html = '') { return sanitizeHtml(String(html || ''), {allowedTags:[],allowedAttributes:{}}).replace(/&#(\d+);/g,(_,n)=>String.fromCodePoint(Number(n))).replace(/&amp;/g,'&').replace(/&quot;/g,'"').replace(/&#039;/g,"'").replace(/&lt;/g,'<').replace(/&gt;/g,'>'); }
export function safeUrl(url) { if(typeof url!=='string'||!url.trim())return null; try {const parsed=new URL(url,origin());return ['http:','https:'].includes(parsed.protocol)?parsed.href:null;} catch{return null;} }
export function route(url) {
  const safe=safeUrl(url);if(!safe)return null;
  const value=new URL(safe);return value.origin===origin()?value.pathname+value.search+value.hash:safe;
}
export function html(value='') {
  return sanitizeHtml(value || '', {
    allowedTags:[...sanitizeHtml.defaults.allowedTags,'img','figure','figcaption','iframe'],
    allowedAttributes:{...sanitizeHtml.defaults.allowedAttributes,'*':['class','id'],img:['src','alt','width','height','loading','srcset','sizes'],iframe:['src','title','width','height','allowfullscreen','loading'],a:['href','title','rel','target']},
    allowedSchemes:['http','https','mailto','tel'], allowedIframeHostnames:['www.youtube.com','www.youtube-nocookie.com','player.vimeo.com'],
    transformTags:{a:(tag,attrs)=>({tagName:tag,attribs:{...attrs,href:route(attrs.href)||'#',rel:'noopener noreferrer'}}),img:(tag,attrs)=>({tagName:tag,attribs:{...attrs,loading:'lazy'}})},
  });
}
export const articlePath = post => '/articles/'+encodeURIComponent(post.slug)+'/';
export const minutes = post => Math.max(1,Math.ceil(text(post.content||post.excerpt).split(/\s+/).filter(Boolean).length/220))+' min read';
export const publicNodes = connection => (connection?.nodes||[]).filter(node=>node?.frontendUrl);
export const imageFields = 'sourceUrl altText caption arcwellCredit { photographer sourceUrl licenseLabel licenseUrl } mediaDetails { width height }';
export const cardFields = `databaseId slug title excerpt content date modified frontendUrl featuredImage { node { ${imageFields} } } author { node { name slug description arcwellSpecialty frontendUrl } } categories { nodes { name slug frontendUrl } }`;
export const homeFields = `heading emphasis intro heroOverlay manifestoHeading manifestoEmphasis sourcePage { databaseId } hero { ${cardFields} } editorsPicks { ${cardFields} } featuredTopic { name slug description frontendUrl arcwellPromoHeading arcwellImage { ${imageFields} } } featuredSeries { databaseId title slug excerpt frontendUrl arcwellArtLabel arcwellEditionLabel publishedPostCount } aboutPage { title slug frontendUrl }`;

export function postImage(post) {
 if(post.featuredImage?.node?.sourceUrl)return post.featuredImage.node;
 const image=html(post.content).match(/<img\b[^>]*>/i)?.[0];
 const src=image?.match(/\bsrc="([^"]+)"/i)?.[1];
 if(!safeUrl(src))return null;
 return {sourceUrl:src.replace(/&amp;/g,'&'),altText:text(image?.match(/\balt="([^"]*)"/i)?.[1]||'')};
}
