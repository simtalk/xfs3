# 小红书作品更新提醒系统

基于 [XhsSkills](https://github.com/cv-cat/XhsSkills) 封装的 PHP 网页版小红书用户监控工具，通过 QQ 邮箱发送更新提醒。

## 功能特点

- 📕 监控小红书用户作品更新
- 🌐 使用 Playwright 自动化浏览器抓取数据（模拟真实用户访问）
- 📧 通过 QQ 邮箱发送通知
- 🌐 简洁易用的 Web 管理界面
- ⏰ 支持定时自动检查
- 📊 查看监控历史记录

## 技术架构

```
┌─────────────────────────────────────────────────────────┐
│                    Web 管理界面                          │
│                       index.php                          │
└─────────────────────────┬───────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────┐
│                      API 接口                            │
│                       api.php                            │
└─────────────────────────┬───────────────────────────────┘
                          │
         ┌────────────────┼────────────────┐
         ▼                ▼                ▼
    ┌─────────┐     ┌───────────┐     ┌─────────┐
    │Database │     │  Mailer   │     │  XhsApi │
    │  MySQL  │     │   SMTP    │     │Playwright│
    └─────────┘     └───────────┘     └────┬─────┘
                                          │
                               ┌──────────▼──────────┐
                               │   Node.js + Playwright│
                               │     自动化浏览器     │
                               └──────────┬──────────┘
                                          │
                               ┌──────────▼──────────┐
                               │    小红书网站       │
                               │ xiaohongshu.com     │
                               └─────────────────────┘
```

## 目录结构

```
xhs-monitor/
├── index.php              # 主页面
├── api.php                # API接口
├── config.php             # 配置文件
├── config.example.php     # 配置示例
├── install.php            # 数据库初始化
├── install-playwright.php  # Playwright安装检查
├── cron.php               # 定时监控脚本
├── package.json           # Node.js依赖配置
├── scripts/
│   └── scraper.js         # Playwright抓取脚本
├── includes/
│   ├── database.php       # 数据库操作类
│   ├── mailer.php         # 邮件发送类
│   ├── playwright.php     # Playwright执行器
│   └── xhs_api.php        # 小红书API类（基于Playwright）
├── data/                  # 数据目录（日志等）
└── README.md
```

## 安装步骤

### 1. 环境要求

- PHP 7.4+
- MySQL 5.7+
- Node.js 18+ (用于运行 Playwright)
- PHP 扩展: pdo, pdo_mysql, curl, json

### 2. 安装 Node.js 和 Playwright

```bash
# 安装 Node.js (如果尚未安装)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# 进入项目目录
cd /path/to/xhs-monitor

# 安装依赖
npm install

# 安装 Playwright 浏览器
npx playwright install chromium
```

或访问 `http://your-domain/install-playwright.php` 进行可视化安装。

### 3. 配置数据库

编辑 `config.php` 文件：

```php
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => 'your_password',
    'database' => 'xhs_monitor',
    'charset' => 'utf8mb4'
];
```

### 3. 配置 QQ 邮箱 SMTP

```php
$smtp_config = [
    'host' => 'smtp.qq.com',
    'port' => 587,
    'username' => 'your-email@qq.com',      // 你的QQ邮箱
    'password' => 'your-auth-code',          // QQ邮箱授权码
    'from_name' => '小红书监控提醒'
];
```

### 4. 获取 QQ 邮箱授权码

1. 登录 [QQ邮箱](https://mail.qq.com)
2. 进入设置 → 账户
3. 找到"POP3/IMAP/SMTP/Exchange/CardDAV/CalDAV服务"
4. 开启"SMTP服务"并获取授权码

### 5. 初始化数据库

在浏览器访问: `http://your-domain/install.php`

或通过命令行:
```bash
php install.php
```

### 6. 设置定时任务

Linux/Mac (添加 crontab):
```bash
crontab -e

# 每10分钟执行一次
*/10 * * * * /usr/bin/php /path/to/xhs-monitor/cron.php >> /path/to/xhs-monitor/data/cron.log 2>&1
```

Windows (使用任务计划程序):
```cmd
schtasks /create /sc minute /mo 10 /tn "XHS Monitor" /tr "php.exe C:\path\to\xhs-monitor\cron.php"
```

## 使用说明

### 添加监控用户

1. 打开首页 `http://your-domain/`
2. 在"添加监控用户"区域输入小红书用户主页链接
3. 可选填写备注信息
4. 点击"添加监控"

支持的链接格式：
- `https://www.xiaohongshu.com/user/profile/xxxxx`
- `https://www.xiaohongshu.com/discovery/profile/xxxxx`
- 直接输入用户ID

### 配置通知邮箱

1. 在"通知设置"区域输入通知邮箱
2. 多个邮箱用英文逗号分隔
3. 点击"保存设置"

### 手动刷新

- 点击用户卡片中的"刷新"按钮可立即检查更新

## API 接口

| 接口 | 方法 | 参数 | 说明 |
|------|------|------|------|
| `/api.php?action=add_user` | POST | user_id, note | 添加用户 |
| `/api.php?action=delete_user` | POST | user_id | 删除用户 |
| `/api.php?action=refresh_user` | POST | user_id | 刷新用户 |
| `/api.php?action=save_settings` | POST | notify_emails | 保存设置 |
| `/api.php?action=get_users` | GET | - | 获取用户列表 |
| `/api.php?action=get_logs` | GET | limit | 获取日志 |
| `/api.php?action=test_email` | POST | email | 测试邮件 |

## 邮件通知示例

当有新作品发布时，会收到类似邮件：

```
主题: 📕 用户名 发布了新作品！

正文:
┌─────────────────────────────────┐
│  小红书作品更新提醒              │
├─────────────────────────────────┤
│  👋 您好！您关注的小红书用户      │
│  [用户名] 刚刚发布了 1 篇新作品！ │
│                                 │
│  📝 作品标题                    │
│  🕐 发布时间: 2026-01-01 12:00  │
│                                 │
│  ❤️ 点赞  ⭐ 收藏  💬 评论       │
│                                 │
│  [去小红书查看]                 │
└─────────────────────────────────┘
```

## 常见问题

### Q: 无法连接数据库
检查 `config.php` 中的数据库配置是否正确，确保 MySQL 服务已启动。

### Q: 邮件发送失败
1. 检查 SMTP 配置是否正确
2. 确认 QQ 邮箱授权码有效
3. 检查服务器是否开放 SMTP 端口 (587)

### Q: 无法获取用户信息
小红书可能有反爬机制，可以尝试：
1. 使用用户分享链接而非个人主页链接
2. 确认链接格式正确

### Q: 如何查看监控日志
- Web界面: 首页底部"最近监控日志"
- 文件: `data/monitor.log`

## 安全建议

1. 修改 `config.php` 中的管理密码
2. 设置强密码保护数据库
3. 定期检查日志文件
4. 限制 API 访问频率

## 免责声明

本工具仅供学习研究使用，请勿用于商业目的或违反小红书服务条款的行为。使用者需自行承担风险。

## 参考项目

- [XhsSkills](https://github.com/cv-cat/XhsSkills) - 小红书API封装
- [Spider_XHS](https://github.com/cv-cat/Spider_XHS) - 小红书爬虫

## License

MIT License