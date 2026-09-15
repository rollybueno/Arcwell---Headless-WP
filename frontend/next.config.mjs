export default {
  poweredByHeader: false,
  async headers() {
    return [{source:'/api/arcwell/:path*',headers:[{key:'Cache-Control',value:'private, no-store'},{key:'Referrer-Policy',value:'no-referrer'},{key:'X-Robots-Tag',value:'noindex'}]}];
  },
};
