import {origin} from '../lib/content.mjs';
export default function robots(){const local=['localhost','127.0.0.1'].includes(new URL(origin()).hostname);return {rules:{userAgent:'*',...(local?{disallow:'/'}:{allow:'/',disallow:'/api/'})},sitemap:origin()+'/sitemap.xml'};}
