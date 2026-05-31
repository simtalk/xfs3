<?php
/**
 * Playwright 安装检查和引导页面
 */

// 自动初始化：如果 config.php 不存在，复制示例文件
$configFile = dirname(__FILE__) . '/config.php';
$configExample = dirname(__FILE__) . '/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/includes/xhs_api.php';

header('Content-Type: text/html; charset=UTF-8');

$xhsApi = new XhsApi();
$status = $xhsApi->checkPlaywright();

$message = '';
if (isset($_GET['install'])) {
    $result = $xhsApi->installPlaywright();
    $message = $result['success'] ? 
        '<div class="alert alert-success">✓ Playwright 安装完成！请刷新页面验证。</div>' : 
        '<div class="alert alert-error">✗ 安装失败: ' . ($result['error'] ?? '未知错误') . '</div>';
    $status = $xhsApi->checkPlaywright();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playwright 安装检查</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 30px; text-align: center; }
        .card { background: #fff; border-radius: 12px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .check-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee; }
        .check-item:last-child { border-bottom: none; }
        .check-label { font-weight: 500; color: #333; }
        .check-value { font-size: 14px; }
        .status-ok { color: #28a745; }
        .status-fail { color: #dc3545; }
        .status-pending { color: #ffc107; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: #fff; border: none; border-radius: 25px; font-size: 16px; text-decoration: none; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; margin: 15px 0; overflow-x: auto; }
        h2 { color: #333; margin: 20px 0 15px; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Playwright 安装状态检查</h1>
        
        <?php if ($message): ?>
        <?= $message ?>
        <?php endif; ?>
        
        <div class="card">
            <h2>环境状态</h2>
            
            <div class="check-item">
                <span class="check-label">Node.js 可用</span>
                <span class="check-value <?= $status['node_available'] ? 'status-ok' : 'status-fail' ?>">
                    <?= $status['node_available'] ? '✓ 已安装 (' . htmlspecialchars($status['node_version']) . ')' : '✗ 未安装' ?>
                </span>
            </div>
            
            <div class="check-item">
                <span class="check-label">npm 可用</span>
                <span class="check-value <?= $status['npm_available'] ? 'status-ok' : 'status-fail' ?>">
                    <?= $status['npm_available'] ? '✓ 可用' : '✗ 不可用' ?>
                </span>
            </div>
            
            <div class="check-item">
                <span class="check-label">抓取脚本</span>
                <span class="check-value <?= $status['script_exists'] ? 'status-ok' : 'status-fail' ?>">
                    <?= $status['script_exists'] ? '✓ 已存在' : '✗ 不存在' ?>
                </span>
            </div>
            
            <div class="check-item">
                <span class="check-label">Playwright 模块</span>
                <span class="check-value <?= $status['playwright_installed'] ? 'status-ok' : 'status-fail' ?>">
                    <?= $status['playwright_installed'] ? '✓ 已安装' : '✗ 未安装' ?>
                </span>
            </div>
        </div>
        
        <?php if (!$status['playwright_installed'] || !$status['node_available']): ?>
        <div class="card">
            <h2>安装指南</h2>
            
            <?php if (!$status['node_available']): ?>
            <h3>1. 安装 Node.js</h3>
            <p style="color: #666; margin-bottom: 15px;">系统未检测到 Node.js，请先安装：</p>
            <div class="code-block">
# Debian/Ubuntu
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# CentOS/RHEL
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo yum install -y nodejs

# macOS
brew install node

# Windows
# 下载 https://nodejs.org/ 安装
            </div>
            <?php endif; ?>
            
            <?php if ($status['node_available'] && $status['npm_available'] && !$status['playwright_installed']): ?>
            <h3>安装 Playwright</h3>
            <p style="color: #666; margin-bottom: 15px;">点击下方按钮安装 Playwright 和 Chromium 浏览器：</p>
            <form method="get">
                <input type="hidden" name="install" value="1">
                <button type="submit" class="btn">🔽 安装 Playwright</button>
            </form>
            <p style="color: #999; font-size: 12px; margin-top: 10px;">安装可能需要几分钟，取决于网络速度</p>
            
            <p style="margin-top: 20px; color: #666;">或者通过命令行安装：</p>
            <div class="code-block">
cd <?= htmlspecialchars(__DIR__) ?>
npm install
npx playwright install chromium
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>依赖说明</h2>
            <ul>
                <li><strong>Node.js</strong> - JavaScript 运行环境</li>
                <li><strong>Playwright</strong> - 自动化浏览器测试框架，用于模拟真实用户访问小红书</li>
                <li><strong>Chromium</strong> - Playwright 的浏览器引擎</li>
            </ul>
            <p style="margin-top: 15px; color: #666;">
                使用 Playwright 可以更好地绕过小red书的反爬机制，获取更完整的数据。
            </p>
        </div>
        <?php else: ?>
        <div class="card">
            <h2>✓ Playwright 已就绪</h2>
            <p style="color: #28a745;">所有组件已正确安装，可以开始使用小红书监控功能。</p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn">← 返回主页</a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card" style="text-align: center; color: #999;">
            <p><a href="index.php" style="color: #667eea;">返回主页</a></p>
        </div>
    </div>
</body>
</html>