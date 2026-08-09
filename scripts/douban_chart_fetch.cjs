#!/usr/bin/env node
// ============================================================
// douban_chart_fetch.cjs —— 豆瓣新片/新剧榜单抓取桥（node）
// ------------------------------------------------------------
// 背景：豆瓣对 PHP curl/stream 的指纹返回"登录跳转页"，而 Node
// 原生 https 直连正常（浏览器级指纹）。此脚本由 PHP 侧（或手动）
// 调用，把榜单缓存到 runtime/cache/douban/chart_<type>.json，
// 供 api/douban_chart.php 直接读取。
// 用法：node scripts/douban_chart_fetch.cjs [movie|tv|all] [limit]
// ============================================================
const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const CACHE_DIR = path.join(ROOT, 'runtime', 'cache', 'douban');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

function fetchJson(url, timeout = 15000) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const mod = u.protocol === 'https:' ? https : http;
    const req = mod.request({
      hostname: u.hostname,
      path: u.pathname + u.search,
      method: 'GET',
      headers: {
        'User-Agent': UA,
        'Referer': 'https://movie.douban.com/',
        'Accept': 'application/json,text/html;q=0.9,*/*;q=0.8',
        'Accept-Language': 'zh-CN,zh;q=0.9',
        'Accept-Encoding': 'identity',
      },
      timeout,
    }, (res) => {
      const chunks = [];
      res.on('data', (d) => chunks.push(d));
      res.on('end', () => {
        const body = Buffer.concat(chunks).toString('utf8');
        if (res.statusCode >= 400) return reject(new Error('HTTP ' + res.statusCode));
        try { resolve(JSON.parse(body)); }
        catch (e) { reject(new Error('JSON 解析失败: ' + body.slice(0, 120))); }
      });
    });
    req.on('timeout', () => { req.destroy(new Error('timeout')); });
    req.on('error', reject);
    req.end();
  });
}

async function fetchChart(type, limit) {
  // 电影 tag=最新（新片），剧集 tag=热门（新剧/热播）
  const tag = type === 'movie' ? '最新' : '热门';
  const url = 'https://movie.douban.com/j/search_subjects?type=' + type +
    '&tag=' + encodeURIComponent(tag) + '&sort=recommend&page_limit=' + limit + '&page_start=0';
  const json = await fetchJson(url);
  const subjects = Array.isArray(json.subjects) ? json.subjects : [];
  const list = subjects.slice(0, limit).map((s) => ({
    title: String(s.title || '').trim(),
    url: String(s.url || ''),
    cover: String(s.cover || ''),
    id: String(s.id || ''),
    rate: String(s.rate || ''),
    episodes_info: String(s.episodes_info || ''),
    source: 'cz', // 点击后走 cz 源模糊搜索
  }));
  if (!list.length) throw new Error('豆瓣榜单无数据 type=' + type);
  return { success: true, type, list, cached: false, fetched_at: new Date().toISOString().slice(0, 19).replace('T', ' ') };
}

(async () => {
  const argType = (process.argv[2] || 'all').toLowerCase();
  const limit = Math.max(1, Math.min(30, parseInt(process.argv[3] || '12', 10) || 12));
  const types = argType === 'all' ? ['movie', 'tv'] : (argType === 'movie' || argType === 'tv' ? [argType] : ['movie', 'tv']);
  if (!fs.existsSync(CACHE_DIR)) fs.mkdirSync(CACHE_DIR, { recursive: true });
  let ok = 0;
  for (const t of types) {
    try {
      const data = await fetchChart(t, limit);
      fs.writeFileSync(path.join(CACHE_DIR, 'chart_' + t + '.json'), JSON.stringify(data));
      console.log('OK ' + t + ' -> ' + data.list.length + ' 条');
      ok++;
    } catch (e) {
      console.log('FAIL ' + t + ': ' + e.message);
    }
  }
  process.exit(ok > 0 ? 0 : 1);
})();
