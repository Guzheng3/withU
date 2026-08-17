#!/usr/bin/env node
/**
 * 通用 MCP streamable HTTP 客户端（最小实现，无第三方依赖）
 *
 * 用法：
 *   node mcp-image-analyze.cjs <image_url> <prompt_text>
 *
 * 通过 ModelScope 的 Qwen-Image-Understanding MCP 调用 interpret_image_content。
 * 端点从配置读取（支持 --url 覆盖，或用环境变量 MCP_IMAGE_URL）。
 */
const http = require('http');
const https = require('https');

const MCP_URL =
  process.env.MCP_IMAGE_URL ||
  'https://mcp.api-inference.modelscope.net/5f067b422b6f44/mcp';

const imageUrl = process.argv[2];
const promptText = process.argv.slice(3).join(' ') || '解读图片内容';

if (!imageUrl) {
  console.error('用法: node mcp-image-analyze.cjs <image_url> [prompt_text]');
  process.exit(2);
}

const REQUEST_TIMEOUT = 180000;

function postJson(url, body, sessionId) {
  const u = new URL(url);
  const mod = u.protocol === 'https:' ? https : http;
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json, text/event-stream',
  };
  if (sessionId) headers['Mcp-Session-Id'] = sessionId;
  return new Promise((resolve, reject) => {
    const req = mod.request(
      u,
      {
        method: 'POST',
        headers,
        timeout: REQUEST_TIMEOUT,
      },
      (res) => {
        let data = '';
        res.setEncoding('utf8');
        res.on('data', (c) => (data += c));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
      }
    );
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', reject);
    req.write(JSON.stringify(body));
    req.end();
  });
}

/** 从 body 中提取 JSON（兼容纯 JSON 与 SSE 格式） */
function extractJson(body) {
  const trimmed = body.trim();
  if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
    return JSON.parse(trimmed);
  }
  // SSE: data: {...}
  const lines = trimmed
    .split(/\r?\n/)
    .filter((l) => l.startsWith('data:'))
    .map((l) => l.slice(5).trim());
  if (lines.length === 0) throw new Error('无法解析 MCP 响应: ' + body.slice(0, 300));
  return JSON.parse(lines.join(''));
}

async function main() {
  // 1. initialize
  let resp = await postJson(MCP_URL, {
    jsonrpc: '2.0',
    id: 1,
    method: 'initialize',
    params: {
      protocolVersion: '2025-03-26',
      capabilities: {},
      clientInfo: { name: 'mcp-image-analyze', version: '1.0.0' },
    },
  });
  if (resp.status !== 200) {
    console.error('initialize 失败 HTTP ' + resp.status + ': ' + resp.body.slice(0, 300));
    process.exit(1);
  }
  const sessionId = resp.headers['mcp-session-id'] || resp.headers['Mcp-Session-Id'];

  // 2. notifications/initialized
  await postJson(MCP_URL, { jsonrpc: '2.0', method: 'notifications/initialized', params: {} }, sessionId);

  // 3. tools/call
  resp = await postJson(
    MCP_URL,
    {
      jsonrpc: '2.0',
      id: 2,
      method: 'tools/call',
      params: {
        name: 'interpret_image_content',
        arguments: { image_url: imageUrl, text: promptText },
      },
    },
    sessionId
  );
  if (resp.status !== 200) {
    console.error('tools/call 失败 HTTP ' + resp.status + ': ' + resp.body.slice(0, 300));
    process.exit(1);
  }
  const parsed = extractJson(resp.body);
  if (parsed.error) {
    console.error('MCP 返回错误: ' + JSON.stringify(parsed.error));
    process.exit(1);
  }
  const result = parsed.result || {};
  const content = result.content || [];
  for (const item of content) {
    if (item.type === 'text') {
      console.log(item.text);
    }
  }
  if (content.length === 0) {
    console.log(JSON.stringify(result, null, 2));
  }
}

main().catch((e) => {
  console.error('调用失败: ' + e.message);
  process.exit(1);
});
