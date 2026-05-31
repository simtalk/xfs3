/**
 * 小红书数据抓取工具
 * 使用 Playwright 模拟浏览器访问
 */

const { chromium } = require('playwright');

const args = process.argv.slice(2);
const command = args[0] || 'help';
const userId = args[1] || '';
const cookie = args[2] || '';

function outputResult(data) {
    console.log(JSON.stringify(data, null, 2));
}

async function scrapeUserNotes(userId, cookie = '') {
    try {
        const browser = await chromium.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
        });
        
        const context = await browser.newContext({
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            viewport: { width: 1920, height: 1080 },
            ignoreHTTPSErrors: true
        });
        
        const page = await context.newPage();
        
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
        });
        
        const url = `https://www.xiaohongshu.com/user/profile/${userId}`;
        console.error(`正在访问: ${url}`); // 使用stderr避免干扰stdout
        
        await page.goto(url, {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        
        // 等待页面加载
        await page.waitForTimeout(3000);
        
        // 获取页面内容
        const content = await page.content();
        
        // 提取 __INITIAL_STATE__
        let userData = { nickname: '', avatar: '', fans: 0, notes: [] };
        let notes = [];
        
        // 检查是否是错误页面（验证码/登录等）
        if (content.includes('验证中心') || content.includes('登录') || content.includes('captcha')) {
            await browser.close();
            return {
                success: false,
                error: '需要登录或遇到验证码，请配置Cookie或稍后重试',
                user_id: userId
            };
        }
        
        const initialStateMatch = content.match(/window\.__INITIAL_STATE__\s*=\s*({.+?});/s);
        if (initialStateMatch) {
            try {
                const data = JSON.parse(initialStateMatch[1]);
                
                if (data['user']) {
                    userData.nickname = data['user'].nickname || '';
                    userData.avatar = data['user'].avatar || '';
                    userData.fans = data['user'].fans || 0;
                }
                
                if (data['noteList']) {
                    notes = data['noteList'].map(note => ({
                        note_id: note.id || note.note_id || '',
                        title: note.title || note.display_title || '',
                        cover_url: note.cover_url || (note.image_list && note.image_list[0] ? note.image_list[0].url : ''),
                        liked_count: parseInt(note.liked_count || note.interact_info?.liked_count || 0),
                        collected_count: parseInt(note.collected_count || note.interact_info?.collected_count || 0),
                        comment_count: parseInt(note.comment_count || note.interact_info?.comment_count || 0),
                        published_at: note.time ? new Date(note.time * 1000).toISOString() : (note.published_at || null)
                    }));
                }
            } catch (e) {
                console.error('JSON解析失败:', e.message);
            }
        }
        
        await browser.close();
        
        const result = {
            success: true,
            user_id: userId,
            user: userData,
            notes: notes,
            count: notes.length
        };
        
        return result;
        
    } catch (error) {
        console.error('抓取失败:', error.message);
        return {
            success: false,
            error: error.message,
            user_id: userId
        };
    }
}

async function resolveShortUrl(shortUrl) {
    return new Promise((resolve) => {
        const https = require('https');
        const urlObj = new URL(shortUrl);
        
        const options = {
            hostname: urlObj.hostname,
            path: urlObj.pathname,
            method: 'GET',
            followAllRedirects: true
        };
        
        const req = https.request(options, (res) => {
            const finalUrl = res.socket._httpMessage.path || shortUrl;
            const match = finalUrl.match(/xiaohongshu\.com\/(?:discovery\/)?profile\/([a-zA-Z0-9]+)/);
            resolve({ success: true, user_id: match ? match[1] : null });
        });
        
        req.on('error', () => resolve({ success: false, user_id: null }));
        req.end();
    });
}

async function main() {
    switch (command) {
        case 'scrape':
            if (!userId) {
                outputResult({ success: false, error: '请提供用户ID' });
                process.exit(1);
            }
            
            if (userId.includes('xhslink.com') || userId.includes('xhs.cn')) {
                const resolved = await resolveShortUrl(userId);
                if (resolved.success && resolved.user_id) {
                    const result = await scrapeUserNotes(resolved.user_id, cookie);
                    outputResult(result);
                } else {
                    outputResult({ success: false, error: '无法解析短链接' });
                }
            } else {
                const result = await scrapeUserNotes(userId, cookie);
                outputResult(result);
            }
            break;
            
        case 'resolve':
            if (!userId) {
                outputResult({ success: false, error: '请提供链接' });
                process.exit(1);
            }
            const resolved = await resolveShortUrl(userId);
            outputResult(resolved);
            break;
            
        default:
            console.log(`
小红书数据抓取工具
用法:
  node scraper.js scrape <userId> [cookie]  抓取用户作品
  node scraper.js resolve <url>            解析短链接获取用户ID

示例:
  node scraper.js scrape 64c1234567890abcdef
  node scraper.js scrape https://www.xiaohongshu.com/discovery/profile/64c1234567890abcdef
  node scraper.js resolve https://www.xhslink.com/abc123
`);
    }
}

main().catch(e => {
    console.error('Error:', e.message);
    outputResult({ success: false, error: e.message });
});