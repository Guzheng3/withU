// ============================================================
// Cloudflare Worker：TMDB 反向代理（解决国内无法访问 TMDB）
//
// 路由规则：
//   /t/p/*  → https://image.tmdb.org  （海报/背景图 CDN）
//   其余    → https://api.themoviedb.org （API）
//
// 部署步骤：
//   1. dash.cloudflare.com → Workers 和 Pages → 创建 → 命名（如 tmdb-proxy）→ 部署
//   2. 编辑代码：删除默认内容，粘贴本文件，重新部署
//   3. 设置 → 域和路由 → 添加自定义域（域名 DNS 需托管在 Cloudflare），
//      如 tmdb.example.com；workers.dev 默认域在国内不稳定，勿用
//   4. 验证：
//        curl https://tmdb.example.com/3/movie/550          → 401 JSON（未带 key 的正常响应）
//        curl -o t.png https://tmdb.example.com/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png
//
// 注意：免费额度每天 10 万次请求；请勿公开分享该域名，避免被滥用。
// ============================================================
export default {
  async fetch(request) {
    const url = new URL(request.url);
    const target = url.pathname.startsWith("/t/p/")
      ? "https://image.tmdb.org"
      : "https://api.themoviedb.org";
    const resp = await fetch(target + url.pathname + url.search, request, {
      // 边缘缓存 24 小时：海报图第二次起毫秒级返回，大幅加速批量刮削
      cf: { cacheEverything: true, cacheTtl: 86400 },
    });
    const headers = new Headers(resp.headers);
    headers.set("Access-Control-Allow-Origin", "*");
    headers.set("Cache-Control", "public, max-age=86400");
    return new Response(resp.body, { status: resp.status, headers });
  },
};
