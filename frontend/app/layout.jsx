import './prototype.css';
import './live.css';
import {getSite} from '../lib/wp.mjs';
import {origin,route,text} from '../lib/content.mjs';
import {Navigation} from '../components/Controls';
export const metadata={metadataBase:new URL(origin()),title:{default:'Arcwell',template:'%s — Arcwell'},description:'An independent journal for the curious.',robots:process.env.NODE_ENV==='production'?{index:true,follow:true}:{index:false,follow:false}};
function Brand(){return <a className="brand" href="/" aria-label="Arcwell home"><svg viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M20 2v36M2 20h36M7.3 7.3l25.4 25.4M7.3 32.7 32.7 7.3" stroke="currentColor" strokeWidth="5"/></svg>arcwell</a>;}
export default async function Layout({children}) {
 let site;try{site=await getSite();}catch{site={arcwell:{},menuItems:{nodes:[]},pages:{nodes:[]},generalSettings:{}};}
 const about=site.pages.nodes.find(p=>p.arcwellPresentation?.template==='about'&&p.frontendUrl);
 const fallback=[{href:'/journal/',label:'The journal'},{href:'/topics/',label:'Topics'},{href:'/series/',label:'Series'},...(about?[{href:route(about.frontendUrl),label:'About us'}]:[])];
 const menus=location=>site.menuItems.nodes.filter(i=>!i.parentId&&i.locations?.includes(location)).map(i=>({label:text(i.label),href:route(i.connectedNode?.node?.frontendUrl||i.url)})).filter(i=>i.href);
 const nav=menus('ARCWELL_PRIMARY');const explore=menus('ARCWELL_FOOTER_EXPLORE');const footer=menus('ARCWELL_FOOTER_ABOUT');
 return <html lang="en"><body><a className="skip" href="#main">Skip to content</a><div className="wrap"><div className="topline"><span>{site.arcwell.tagline||'An independent journal for the curious'}</span><span className="live"><span className="dot"/> Fresh perspectives, thoughtfully told</span><span>{site.arcwell.foundingYear?'Est. '+site.arcwell.foundingYear:''}{site.arcwell.issueLabel?' · '+site.arcwell.issueLabel:''}</span></div><header className="header"><Brand/><Navigation items={nav.length?nav:fallback}/></header></div>{children}<footer><div className="wrap"><div className="footer-top"><div><Brand/><p>{site.arcwell.tagline||'A wider view of the world. Stories worth slowing down for.'}</p></div><div className="footer-links"><div><span className="eyebrow">Explore</span>{(explore.length?explore:fallback).map((i,n)=><a key={n} href={i.href}>{i.label}</a>)}</div><div><span className="eyebrow">Stay curious</span>{footer.map((i,n)=><a key={n} href={i.href}>{i.label}</a>)}<a href="/search/">Search the journal</a><a href="/feed.xml">RSS feed</a></div></div></div><div className="footer-bottom"><span>© {new Date().getFullYear()} {text(site.generalSettings.title)||'Arcwell'}</span><span>Made for a more curious world.</span></div></div></footer></body></html>;
}
