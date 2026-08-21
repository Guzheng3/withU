/**
 * README 截图自动生成脚本
 *
 * 使用 Playwright 自动登录并截取核心页面截图，输出到 assets/readme/。
 * 适用于以 Docker 部署到 3111 端口的 withUstrm 实例，或任意 BASE_URL。
 *
 * 依赖：
 *   npm i playwright-core
 *   以及本机可用的 Chromium 可执行文件（默认路径见 CHROME_PATH）
 *
 * 用法：
 *   node scripts/readme-screenshots.mjs [baseUrl]
 */
import { chromium } from 'playwright-core'
import { mkdirSync } from 'fs'

const execPath = process.env.CHROME_PATH || '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome'
const BASE = process.argv[2] || 'http://localhost:3111'
const OUT = new URL('../assets/readme/', import.meta.url).pathname
mkdirSync(OUT, { recursive: true })

const browser = await chromium.launch({ executablePath: execPath, args: ['--no-sandbox', '--disable-gpu'] })
const page = await browser.newPage({ viewport: { width: 1600, height: 950 }, deviceScaleFactor: 1 })

const loginUser = process.env.LOGIN_USER || 'qinghan'
const loginPass = process.env.LOGIN_PASS || '123456'

// 登录
await page.goto(`${BASE}/auth/login`, { waitUntil: 'domcontentloaded' })
await page.waitForSelector('#username', { timeout: 30000 })
await page.fill('#username', loginUser)
await page.fill('#password', loginPass)
await page.click('button[type="submit"]')
await page.waitForTimeout(4000)

const shots = [
  ['openlist-config', '/', 3500],
  ['media-library', '/media-library', 3500],
  ['media-detail', '/media-library/4500', 3500],
  ['settings', '/settings', 3500],
  ['logs', '/logs', 3500]
]

for (const [name, path, wait] of shots) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(wait)
  await page.screenshot({ path: `${OUT}/${name}.png` })
  console.log(`saved ${OUT}${name}.png`)
}

await browser.close()
console.log('DONE')
