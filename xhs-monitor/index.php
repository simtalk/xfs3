<?php
/**
 * 小红书用户作品更新提醒系统 - 主页面
 */

session_start();

// 自动初始化：如果 config.php 不存在，复制示例文件
$configFile = __DIR__ . '/config.php';
$configExample = __DIR__ . '/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/xhs_api.php';

// 检查是否已安装
$configExists = file_exists(__DIR__ . '/config.php') && filesize(__DIR__ . '/config.php') > 100;

// 检查数据库是否已初始化
$dbInitialized = false;
$db = null;
$error = '';

try {
    $db = Database::getInstance();
    // 测试数据库连接并检查表是否存在
    $db->getConnection()->query("SELECT 1 FROM users LIMIT 1");
    $dbInitialized = true;
} catch (PDOException $e) {
    // 表不存在，检查是否是连接问题还是表问题
    $errorMsg = $e->getMessage();
    if (strpos($errorMsg, "doesn't exist") !== false || strpos($errorMsg, "1146") !== false) {
        $error = '数据库表未初始化，请先运行 <a href="install.php" style="color:#667eea;">install.php</a>';
    } else {
        $error = '数据库连接失败: ' . $errorMsg;
    }
} catch (Exception $e) {
    $error = '数据库连接失败: ' . $e->getMessage();
}

// 处理AJAX请求
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: text/html; charset=UTF-8');

// 如果数据库未初始化，显示初始化提示页面
if (!$dbInitialized && empty($action)) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>小红书作品更新提醒系统 - 未初始化</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 16px; padding: 50px; max-width: 500px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        .icon { font-size: 80px; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 30px; line-height: 1.6; }
        .btn { display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 30px; text-decoration: none; font-size: 18px; font-weight: 500; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .note { margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 10px; text-align: left; }
        .note h3 { color: #333; margin-bottom: 10px; font-size: 16px; }
        .note ol { padding-left: 20px; color: #666; font-size: 14px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚀</div>
        <h1>初始化提示</h1>
        <p><?= $error ?></p>
        <a href="install.php" class="btn">前往初始化 →</a>
        <div class="note">
            <h3>📋 初始化步骤</h3>
            <ol>
                <li>点击上方按钮或访问 <code>install.php</code></li>
                <li>确保数据库配置正确（config.php）</li>
                <li>点击初始化按钮创建数据表</li>
                <li>返回首页开始使用</li>
            </ol>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>小红书作品更新提醒系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; color: #fff; padding: 40px 0; }
        .header h1 { font-size: 36px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .card { background: #fff; border-radius: 16px; padding: 30px; margin-bottom: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .card-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #666; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 16px; border: 2px solid #e8e8e8; border-radius: 10px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 25px; font-size: 16px; font-weight: 500; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #f5f5f5; color: #666; }
        .btn-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-sm { padding: 8px 16px; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .user-card { background: #fafafa; border-radius: 12px; padding: 20px; position: relative; }
        .user-card img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
        .user-card h3 { margin: 15px 0 5px; color: #333; }
        .user-card p { color: #999; font-size: 14px; }
        .user-card .note-count { position: absolute; top: 20px; right: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; }
        .user-card .actions { margin-top: 15px; display: flex; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .tab { padding: 10px 20px; color: #666; text-decoration: none; border-radius: 8px; transition: all 0.3s; }
        .tab.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .tab:hover:not(.active) { background: #f5f5f5; }
        .log-list { max-height: 400px; overflow-y: auto; }
        .log-item { padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .log-item:last-child { border-bottom: none; }
        .log-time { color: #999; font-size: 12px; }
        .log-action { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
        .log-action.new_notes { background: #d4edda; color: #155724; }
        .log-action.error { background: #f8d7da; color: #721c24; }
        .log-action.update { background: #cce5ff; color: #004085; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state svg { width: 80px; height: 80px; margin-bottom: 20px; opacity: 0.5; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-item { background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%); padding: 20px; border-radius: 12px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        @media (max-width: 768px) { .stats { grid-template-columns: repeat(2, 1fr); } .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📕 小红书作品更新提醒系统</h1>
            <p>监控小红书用户动态，通过邮件接收更新通知</p>
        </div>
        
        <?php if ($error): ?>
        <div class="card">
            <div class="alert alert-warning">
                <strong>⚠️ 系统提示:</strong> <?= $error ?><br>
                请先运行 <a href="install.php" style="color:#667eea;">install.php</a> 初始化数据库。
            </div>
        </div>
        <?php endif; ?>
        
        <div class="stats">
            <?php
            $users = $dbInitialized ? $db->getUsers() : [];
            $logs = $dbInitialized ? $db->getLogs(null, 100) : [];
            $todayLogs = $dbInitialized ? array_filter($logs, function($log) {
                return strtotime($log['created_at']) > strtotime('today');
            }) : [];
            $newNotesToday = $dbInitialized ? array_sum(array_column($todayLogs, 'new_count')) : 0;
            $errorCount = $dbInitialized ? count(array_filter($logs, function($log) { return $log['action'] === 'error'; })) : 0;
            ?>
            <div class="stat-item">
                <div class="stat-value"><?= count($users) ?></div>
                <div class="stat-label">监控用户</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $newNotesToday ?></div>
                <div class="stat-label">今日新增作品</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= count($todayLogs) ?></div>
                <div class="stat-label">今日监控次数</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $errorCount ?></div>
                <div class="stat-label">错误数</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>➕</span> 添加监控用户
            </div>
            <form id="addUserForm" onsubmit="addUser(event)">
                <div class="form-group">
                    <label>小红书用户链接或ID</label>
                    <input type="text" id="userInput" placeholder="请输入用户主页链接或用户ID" required>
                </div>
                <div class="form-group">
                    <label>备注（可选）</label>
                    <input type="text" id="noteInput" placeholder="输入备注信息，方便识别">
                </div>
                <button type="submit" class="btn">添加监控</button>
            </form>
            <div id="formMessage"></div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>👥</span> 监控中的用户
                <span style="font-size: 14px; font-weight: normal; color: #999; margin-left: auto;">共 <?= count($users) ?> 人</span>
            </div>
            
            <?php if (empty($users)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <p>暂无监控用户</p>
                <p style="font-size: 14px; margin-top: 10px;">在上方添加小红书用户链接开始监控</p>
            </div>
            <?php else: ?>
            <div class="grid">
                <?php foreach ($users as $user): 
                    $noteCount = $dbInitialized ? $db->getNoteCount($user['user_id']) : 0;
                    $latestNote = $dbInitialized ? $db->getLatestNote($user['user_id']) : null;
                ?>
                <div class="user-card">
                    <?php if ($user['avatar']): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="avatar">
                    <?php else: ?>
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23999'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E" alt="avatar">
                    <?php endif; ?>
                    <span class="note-count"><?= $noteCount ?> 篇作品</span>
                    <h3><?= htmlspecialchars($user['nickname'] ?: '未知用户') ?></h3>
                    <p><?= htmlspecialchars($user['user_id']) ?></p>
                    <?php if ($user['note']): ?>
                    <p style="color: #667eea; font-size: 12px;">📝 <?= htmlspecialchars($user['note']) ?></p>
                    <?php endif; ?>
                    <?php if ($latestNote): ?>
                    <p style="margin-top: 10px; font-size: 12px;">
                        最新: <?= htmlspecialchars(mb_substr($latestNote['title'], 0, 20)) ?><?= mb_strlen($latestNote['title']) > 20 ? '...' : '' ?>
                    </p>
                    <?php endif; ?>
                    <div class="actions">
                        <button class="btn btn-sm btn-secondary" onclick="refreshUser('<?= $user['user_id'] ?>')">🔄 刷新</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser('<?= $user['user_id'] ?>')">🗑️ 删除</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>📊</span> 最近监控日志
            </div>
            <?php if (empty($logs)): ?>
            <div class="empty-state">
                <p>暂无监控记录</p>
            </div>
            <?php else: ?>
            <div class="log-list">
                <?php foreach (array_slice($logs, 0, 20) as $log): ?>
                <div class="log-item">
                    <div>
                        <strong><?= htmlspecialchars($log['nickname'] ?: $log['user_id']) ?></strong>
                        <span class="log-action <?= $log['action'] ?>"><?= $log['action'] ?></span>
                        <?php if ($log['action'] === 'new_notes'): ?>
                        发布了 <?= $log['new_count'] ?> 篇作品
                        <?php elseif ($log['action'] === 'error'): ?>
                        <span style="color: #721c24;">检查失败</span>
                        <?php else: ?>
                        已更新数据
                        <?php endif; ?>
                    </div>
                    <div class="log-time"><?= $log['created_at'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>⚙️</span> 通知设置
            </div>
            <form id="emailForm" onsubmit="saveEmailSettings(event)">
                <div class="form-group">
                    <label>通知邮箱（多个用英文逗号分隔）</label>
                    <input type="text" id="notifyEmails" value="<?= $dbInitialized ? htmlspecialchars($db->getSetting('notify_emails', '')) : '' ?>" placeholder="example@qq.com, example2@qq.com">
                </div>
                <button type="submit" class="btn">保存设置</button>
            </form>
            <div id="settingsMessage"></div>
        </div>
        
        <div class="card">
            <div class="card-title">
                <span>🔑</span> 小红书Cookie设置
            </div>
            <form id="cookieForm" onsubmit="saveCookieSettings(event)">
                <div class="form-group">
                    <label>登录Cookie（用于获取用户数据）</label>
                    <textarea id="xhsCookie" rows="3" placeholder="请输入小红书登录后的Cookie，格式如：web_session=xxx; web_id=xxx; ..."><?= isset($xhs_config['cookie']) ? htmlspecialchars($xhs_config['cookie']) : '' ?></textarea>
                    <small style="color: #999; display: block; margin-top: 5px;">
                        获取方法：在小红书网页版登录后，按F12打开开发者工具 -> Application -> Cookies -> 复制全部Cookie值
                    </small>
                </div>
                <button type="submit" class="btn">保存Cookie</button>
            </form>
            <div id="cookieMessage"></div>
        </div>
        
        <div class="card" style="text-align: center; color: #999; font-size: 14px;">
            <p>📌 提示：系统默认每10分钟检查一次更新。如需修改定时任务，请编辑 cron.php 或修改服务器定时任务。</p>
            <p style="margin-top: 10px;">
                定时任务示例: <code>*/10 * * * * /usr/bin/php <?= __DIR__ ?>/cron.php</code>
            </p>
        </div>
    </div>
    
    <script>
    async function addUser(e) {
        e.preventDefault();
        const userInput = document.getElementById('userInput').value;
        const noteInput = document.getElementById('noteInput').value;
        const messageDiv = document.getElementById('formMessage');
        
        messageDiv.innerHTML = '<div class="alert alert-warning">正在添加...</div>';
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_user');
            formData.append('user_id', userInput);
            formData.append('note', noteInput);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                messageDiv.innerHTML = '<div class="alert alert-success">✓ ' + result.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                messageDiv.innerHTML = '<div class="alert alert-error">✗ ' + result.message + '</div>';
            }
        } catch (err) {
            messageDiv.innerHTML = '<div class="alert alert-error">✗ 请求失败: ' + err.message + '</div>';
        }
    }
    
    async function deleteUser(userId) {
        if (!confirm('确定要删除此用户的监控吗？')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert(result.message);
            }
        } catch (err) {
            alert('请求失败: ' + err.message);
        }
    }
    
    async function refreshUser(userId) {
        try {
            const formData = new FormData();
            formData.append('action', 'refresh_user');
            formData.append('user_id', userId);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('刷新成功！发现 ' + (result.new_count || 0) + ' 篇新作品');
                location.reload();
            } else {
                alert(result.message);
            }
        } catch (err) {
            alert('请求失败: ' + err.message);
        }
    }
    
    async function saveEmailSettings(e) {
        e.preventDefault();
        const emails = document.getElementById('notifyEmails').value;
        const messageDiv = document.getElementById('settingsMessage');
        
        try {
            const formData = new FormData();
            formData.append('action', 'save_settings');
            formData.append('notify_emails', emails);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                messageDiv.innerHTML = '<div class="alert alert-success">✓ 设置已保存</div>';
            } else {
                messageDiv.innerHTML = '<div class="alert alert-error">✗ ' + result.message + '</div>';
            }
        } catch (err) {
            messageDiv.innerHTML = '<div class="alert alert-error">✗ 请求失败</div>';
        }
    }
    
    async function saveCookieSettings(e) {
        e.preventDefault();
        const cookie = document.getElementById('xhsCookie').value;
        const messageDiv = document.getElementById('cookieMessage');
        
        try {
            const formData = new FormData();
            formData.append('action', 'save_cookie');
            formData.append('cookie', cookie);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                messageDiv.innerHTML = '<div class="alert alert-success">✓ Cookie已保存</div>';
            } else {
                messageDiv.innerHTML = '<div class="alert alert-error">✗ ' + result.message + '</div>';
            }
        } catch (err) {
            messageDiv.innerHTML = '<div class="alert alert-error">✗ 请求失败</div>';
        }
    }
    </script>
</body>
</html>