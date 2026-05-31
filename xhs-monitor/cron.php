<?php
/**
 * 小红书作品更新监控脚本
 * 可通过cron定时执行，例如每10分钟执行一次
 * 
 * 用法: php cron.php
 * 定时任务示例 (每10分钟执行):
 *   */10 * * * * /usr/bin/php /path/to/cron.php
 */

// 自动初始化：如果 config.php 不存在，复制示例文件
$configFile = dirname(__FILE__) . '/config.php';
$configExample = dirname(__FILE__) . '/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/includes/database.php';
require_once dirname(__FILE__) . '/includes/mailer.php';
require_once dirname(__FILE__) . '/includes/xhs_api.php';

class Monitor {
    private $db;
    private $mailer;
    private $xhsApi;
    private $logFile;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new Mailer();
        $this->xhsApi = new XhsApi();
        $this->logFile = __DIR__ . '/data/monitor.log';
        
        // 确保日志目录存在
        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0755, true);
        }
    }
    
    /**
     * 执行监控任务
     */
    public function run() {
        $this->log("========== 开始监控任务 ==========");
        $startTime = microtime(true);
        
        // 获取所有监控用户
        $users = $this->db->getUsers();
        $totalUsers = count($users);
        $newNotesCount = 0;
        $errorCount = 0;
        
        $this->log("共有 {$totalUsers} 个用户需要监控");
        
        foreach ($users as $user) {
            $result = $this->checkUser($user);
            if ($result['has_new']) {
                $newNotesCount += count($result['new_notes']);
                
                // 发送邮件通知
                $this->sendNotification($user, $result['new_notes']);
            }
            
            if ($result['error']) {
                $errorCount++;
                $this->log("用户 {$user['user_id']} 检查失败: {$result['error']}");
            }
        }
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $this->log("========== 监控任务完成 ==========");
        $this->log("总计: {$totalUsers} 用户, 新增 {$newNotesCount} 作品, {$errorCount} 错误, 耗时 {$duration}s");
        
        return [
            'total_users' => $totalUsers,
            'new_notes' => $newNotesCount,
            'errors' => $errorCount,
            'duration' => $duration
        ];
    }
    
    /**
     * 检查单个用户
     */
    private function checkUser($user) {
        $userId = $user['user_id'];
        $result = ['has_new' => false, 'new_notes' => [], 'error' => null];
        
        try {
            // 获取用户最新作品
            $latestNotes = $this->xhsApi->getLatestNotes($userId, 20);
            
            if (empty($latestNotes)) {
                $result['error'] = '未获取到作品数据';
                return $result;
            }
            
            // 获取本地最新作品
            $localLatest = $this->db->getLatestNote($userId);
            $localLatestTime = $localLatest ? strtotime($localLatest['published_at']) : 0;
            
            $newNotes = [];
            foreach ($latestNotes as $note) {
                $noteTime = strtotime($note['published_at'] ?? 0);
                
                // 如果服务器时间比本地新，则为新作品
                if ($noteTime > $localLatestTime) {
                    $newNotes[] = $note;
                    
                    // 保存到数据库
                    $this->db->addNote($note['note_id'], $userId, $note);
                }
            }
            
            // 更新用户信息
            if (!empty($latestNotes)) {
                $firstNote = $latestNotes[0];
                $this->db->addUser($userId, $user['nickname'], $firstNote['cover_url'] ?? '', $user['note']);
            }
            
            if (!empty($newNotes)) {
                $result['has_new'] = true;
                $result['new_notes'] = $newNotes;
                
                // 记录监控日志
                $this->db->addLog($userId, 'new_notes', count($newNotes), 0, [
                    'notes' => array_map(function($n) { return $n['note_id']; }, $newNotes)
                ]);
                
                $this->log("用户 {$user['nickname']} 有 " . count($newNotes) . " 篇新作品");
            } else {
                $this->log("用户 {$user['nickname']} 无新作品");
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->db->addLog($userId, 'error', 0, 0, ['error' => $e->getMessage()]);
        }
        
        return $result;
    }
    
    /**
     * 发送邮件通知
     */
    private function sendNotification($user, $newNotes) {
        // 获取通知邮箱列表
        $emailList = $this->db->getSetting('notify_emails', '');
        $emails = array_filter(array_map('trim', explode(',', $emailList)));
        
        // 如果没有配置，使用默认邮箱
        if (empty($emails)) {
            $emails = [$smtp_config['username']];
        }
        
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result = $this->mailer->sendUpdateNotification($email, $user, $newNotes);
                
                // 记录邮件发送日志
                $this->db->addEmailLog(
                    $user['user_id'],
                    $email,
                    '📕 ' . ($user['nickname'] ?: '某用户') . ' 发布了新作品！',
                    $result['success'] ? 1 : 2,
                    $result['success'] ? null : $result['message']
                );
                
                $this->log("邮件通知已发送至: {$email} - " . ($result['success'] ? '成功' : '失败: ' . $result['message']));
            }
        }
    }
    
    /**
     * 记录日志
     */
    private function log($message) {
        $time = date('Y-m-d H:i:s');
        $logMessage = "[{$time}] {$message}\n";
        
        echo $logMessage;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// 执行监控
if (php_sapi_name() === 'cli') {
    $monitor = new Monitor();
    $result = $monitor->run();
    
    // 可以在这里添加更多处理逻辑
    exit(0);
}