import {cache} from 'react';
import {cmsEndpoint,cardFields,homeFields} from './content.mjs';
export async function graphql(query,variables={},options={}) {
  const response=await fetch(cmsEndpoint(),{
    method:'POST',headers:{'Content-Type':'application/json',...(options.headers||{})},body:JSON.stringify({query,variables}),
    signal:AbortSignal.timeout(15000),...(options.private?{cache:'no-store'}:{next:{revalidate:60,tags:['arcwell']}}),
  });
  if(!response.ok)throw new Error('The journal is temporarily unavailable. Please try again shortly.');
  const result=await response.json();
  if(result.errors?.length){console.error('WordPress query failed:',result.errors.map(e=>e.message).join('; '));throw new Error('The journal could not load this content. Please try again shortly.');}
  return result.data;
}
export const getSite=cache(()=>graphql(`query Site { generalSettings { title description } arcwell { contractVersion frontendOrigin tagline foundingYear issueLabel } menuItems(first:100) { nodes { label url parentId locations connectedNode { node { ... on Post { frontendUrl } ... on Page { frontendUrl } ... on Category { frontendUrl } ... on ArcwellTopic { frontendUrl } ... on ArcwellSeries { frontendUrl } } } } } pages(first:100) { nodes { databaseId title slug uri frontendUrl arcwellPresentation { template } } } categories(first:50) { nodes { name slug frontendUrl count } } }`));
export const getHome=cache(()=>graphql(`query Home { arcwellHomepage { ${homeFields} } posts(first:7) { nodes { ${cardFields} } } }`));
export async function getPosts({after=null,search=null,categoryName=null,tag=null,authorName=null}={}) {
  return (await graphql(`query Journal($after:String,$search:String,$category:String,$tag:String,$author:String) { posts(first:12,after:$after,where:{search:$search,categoryName:$category,tag:$tag,authorName:$author}) { nodes { ${cardFields} } pageInfo { hasNextPage endCursor } } }`,{after,search,category:categoryName,tag,author:authorName})).posts;
}
export async function getPost(slug) {return (await graphql(`query Article($slug:ID!){post(id:$slug,idType:SLUG){${cardFields} content arcwellSeries(first:10){nodes{title slug frontendUrl}}}}`,{slug})).post;}
export async function getPage(uri) {return (await graphql(`query Page($uri:ID!){page(id:$uri,idType:URI){databaseId title slug content excerpt frontendUrl arcwellPresentation { template values { heading body } } featuredImage {node {sourceUrl altText}}}}`,{uri})).page;}
export async function getTopics(after=null) {return (await graphql(`query Topics($after:String){arcwellTopics(first:12,after:$after){nodes{name slug description frontendUrl arcwellPromoHeading arcwellImage{sourceUrl altText} count} pageInfo{hasNextPage endCursor}}}`,{after})).arcwellTopics;}
export async function getTopic(slug,after=null) {return (await graphql(`query Topic($slug:ID!,$after:String){arcwellTopic(id:$slug,idType:SLUG){name description posts(first:12,after:$after){nodes{${cardFields}} pageInfo{hasNextPage endCursor}}}}`,{slug,after})).arcwellTopic;}
export async function getTaxonomy(type,slug) {return (await graphql(`query Taxonomy($slug:ID!){${type}(id:$slug,idType:SLUG){name description}}`,{slug}))[type];}
export async function getSeriesList(after=null) {return (await graphql(`query SeriesList($after:String){arcwellSeriesItems(first:12,after:$after){nodes{databaseId title slug excerpt frontendUrl arcwellArtLabel arcwellEditionLabel publishedPostCount} pageInfo{hasNextPage endCursor}}}`,{after})).arcwellSeriesItems;}
export async function getSeries(slug,after=null) {return (await graphql(`query Series($slug:ID!,$after:String){arcwellSeries(id:$slug,idType:SLUG){databaseId title slug excerpt content frontendUrl arcwellArtLabel arcwellEditionLabel publishedPostCount arcwellPosts(first:12,after:$after){nodes{${cardFields}} pageInfo{hasNextPage endCursor}}}}`,{slug,after})).arcwellSeries;}
export async function getAuthor(slug) {return (await graphql(`query Author($slug:ID!){user(id:$slug,idType:SLUG){name slug description arcwellSpecialty frontendUrl}}`,{slug})).user;}
