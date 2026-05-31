/**
 * 小红书数据抓取工具
 * 使用 Playwright 模拟浏览器访问
 */

const { chromium } = require('playwright');
const https = require('https');
const http = require('http');

// 命令行参数
const args = process.argv.slice(2);
const command = args[0] || 'help';
const userId = args[1] || '';
const cookie = args[2] || '';

async function scrapeUserNotes(userId, cookie = '') {
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
    
    // 设置额外的请求头
    await page.setExtraHTTPHeaders({
        'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
    });
    
    try {
        // 访问用户主页
        const url = `https://www.xiaohongshu.com/user/profile/${userId}`;
        console.log(`正在访问: ${url}`);
        
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
        
        const initialStateMatch = content.match(/window\.__INITIAL_STATE__\s*=\s*({.+?});/s);
        if (initialStateMatch) {
            try {
                const data = JSON.parse(initialStateMatch[1]);
                
                // 提取用户信息
                if (data['user']) {
                    userData.nickname = data['user'].nickname || '';
                    userData.avatar = data['user'].avatar || '';
                    userData.fans = data['user'].fans || 0;
                }
                
                // 提取作品列表
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
                console.log('JSON解析失败:', e.message);
            }
        }
        
        // 如果没有获取到数据，尝试滚动页面加载更多
        if (notes.length === 0) {
            console.log('尝试滚动页面加载更多内容...');
            for (let i = 0; i < 3; i++) {
                await page.evaluate(() => window.scrollBy(0, 500));
                await page.waitForTimeout(1000);
            }
            
            // 再次尝试提取
            const newContent = await page.content();
            const newMatch = newContent.match(/window\.__INITIAL_STATE__\s*=\s*({.+?});/s);
            if (newMatch) {
                try {
                    const data = JSON.parse(newMatch[1]);
                    if (data['noteList']) {
                        notes = data['noteList'].map(note => ({
                            note_id: note.id || note.note_id || '',
                            title: note.title || note.display_title || '',
                            cover_url: note.cover_url || '',
                            liked_count: parseInt(note.liked_count || 0),
                            collected_count: parseInt(note.collected_count || 0),
                            comment_count: parseInt(note.comment_count || 0),
                            published_at: note.time ? new Date(note.time * 1000).toISOString() : null
                        }));
                    }
                    if (data['user']) {
                        userData.nickname = data['user'].nickname || userData.nickname;
                        userData.avatar = data['user'].avatar || userData.avatar;
                        userData.fans = data['user'].fans || userData.fans;
                    }
                } catch (e) {
                    console.log('滚动后JSON解析失败');
                }
            }
        }
        
        // 输出结果
        const result = {
            success: true,
            user_id: userId,
            user: userData,
            notes: notes,
            count: notes.length
        };
        
        console.log(JSON.stringify(result, null, 2));
        return result;
        
    } catch (error) {
        console.error('抓取失败:', error.message);
        return {
            success: false,
            error: error.message,
            user_id: userId
        };
    } finally {
        await browser.close();
    }
}

/**
 * 从分享链接获取用户ID
 */
async function resolveShortUrl(shortUrl) {
    return new Promise((resolve) => {
        const urlObj = new URL(shortUrl);
        const client = urlObj.protocol === 'https:' ? https : http;
        
        client.get(shortUrl, { followAllRedirects: true }, (res) => {
            const finalUrl = res.url || shortUrl;
            const match = finalUrl.match(/xiaohongshu\.com\/(?:discovery\/)?profile\/([a-zA-Z0-9]+)/);
            resolve(match ? match[1] : null);
        }).on('error', () => resolve(null));
    });
}

// 主入口
async function main() {
    switch (command) {
        case 'scrape':
            if (!userId) {
                console.log(JSON.stringify({ success: false, error: '请提供用户ID' }));
                process.exit(1);
            }
            
            // 如果是短链接，先解析
            if (userId.includes('xhslink.com') || userId.includes('xhs.cn')) {
                const resolvedId = await resolveShortUrl(userId);
                if (resolvedId) {
                    await scrapeUserNotes(resolvedId, cookie);
                } else {
                    console.log(JSON.stringify({ success: false, error: '无法解析短链接' }));
                }
            } else {
                await scrapeUserNotes(userId, cookie);
            }
            break;
            
        case 'resolve':
            if (!userId) {
                console.log(JSON.stringify({ success: false, error: '请提供链接' }));
                process.exit(1);
            }
            const resolvedId = await resolveShortUrl(userId);
            console.log(JSON.stringify({ success: true, user_id: resolvedId }));
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

main().catch(console.error);