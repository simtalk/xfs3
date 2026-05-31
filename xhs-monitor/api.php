<?php
/**
 * API接口处理文件
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

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

$response = ['success' => false, 'message' => ''];

try {
    $db = Database::getInstance();
    $mailer = new Mailer();
    $xhsApi = new XhsApi();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'add_user':
            $userInput = trim($_POST['user_id'] ?? '');
            $note = trim($_POST['note'] ?? '');
            
            if (empty($userInput)) {
                $response['message'] = '请输入用户链接或ID';
                break;
            }
            
            // 尝试从链接提取用户ID
            $userId = $xhsApi->getUserIdFromShareUrl($userInput);
            
            // 如果提取失败，假设输入的就是用户ID
            if (!$userId) {
                $userId = $userInput;
            }
            
            // 先保存用户（即使后面抓取失败，用户也能被保存）
            $db->addUser($userId, '', '', $note);
            
            // 获取用户信息（可能失败）
            $userInfo = $xhsApi->getUserInfo($userId);
            
            if ($userInfo['success'] && !empty($userInfo['data']['nickname'])) {
                $data = $userInfo['data'];
                $db->addUser($userId, $data['nickname'], $data['avatar'], $note);
                
                // 获取作品列表
                $notes = $xhsApi->getLatestNotes($userId, 10);
                foreach ($notes as $noteData) {
                    if (!empty($noteData['note_id'])) {
                        $db->addNote($noteData['note_id'], $userId, $noteData);
                    }
                }
                
                $response['success'] = true;
                $response['message'] = '用户 ' . $data['nickname'] . ' 已添加';
            } else {
                // 抓取失败，但用户已保存
                $response['success'] = true;
                $response['message'] = '用户已添加（抓取失败: ' . ($userInfo['message'] ?? '未知错误') . '，后续可重试）';
            }
            break;
            
        case 'delete_user':
            $userId = trim($_POST['user_id'] ?? '');
            
            if (empty($userId)) {
                $response['message'] = '用户ID不能为空';
                break;
            }
            
            $db->deleteUser($userId);
            $response['success'] = true;
            $response['message'] = '用户已删除';
            break;
            
        case 'refresh_user':
            $userId = trim($_POST['user_id'] ?? '');
            
            if (empty($userId)) {
                $response['message'] = '用户ID不能为空';
                break;
            }
            
            // 获取用户信息
            $userInfo = $xhsApi->getUserInfo($userId);
            
            if ($userInfo['success']) {
                $data = $userInfo['data'];
                $db->addUser($userId, $data['nickname'], $data['avatar']);
            }
            
            // 获取最新作品
            $latestNotes = $xhsApi->getLatestNotes($userId, 20);
            $newCount = 0;
            
            foreach ($latestNotes as $noteData) {
                $existing = $db->getLatestNote($userId);
                $existingTime = $existing ? strtotime($existing['published_at']) : 0;
                $newTime = strtotime($noteData['published_at'] ?? 0);
                
                if ($newTime > $existingTime) {
                    $db->addNote($noteData['note_id'], $userId, $noteData);
                    $newCount++;
                }
            }
            
            $db->addLog($userId, 'manual_refresh', 0, $newCount, ['notes' => $newCount]);
            
            $response['success'] = true;
            $response['new_count'] = $newCount;
            $response['message'] = $newCount > 0 ? "发现 {$newCount} 篇新作品" : '暂无新作品';
            break;
            
        case 'save_settings':
            $notifyEmails = trim($_POST['notify_emails'] ?? '');
            
            // 验证邮箱格式
            $emails = array_filter(array_map('trim', explode(',', $notifyEmails)));
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response['message'] = "邮箱格式不正确: {$email}";
                    break 2;
                }
            }
            
            $db->setSetting('notify_emails', $notifyEmails);
            $response['success'] = true;
            $response['message'] = '设置已保存';
            break;
            
        case 'get_users':
            $users = $db->getUsers();
            $result = [];
            foreach ($users as $user) {
                $user['note_count'] = $db->getNoteCount($user['user_id']);
                $user['latest_note'] = $db->getLatestNote($user['user_id']);
                $result[] = $user;
            }
            $response['success'] = true;
            $response['data'] = $result;
            break;
            
        case 'get_logs':
            $limit = intval($_GET['limit'] ?? 50);
            $logs = $db->getLogs(null, $limit);
            $response['success'] = true;
            $response['data'] = $logs;
            break;
            
        case 'test_email':
            $email = trim($_POST['email'] ?? '');
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = '邮箱格式不正确';
                break;
            }
            
            $result = $mailer->send($email, '测试邮件 - 小红书监控提醒', '<p>这是一封测试邮件，用于验证邮件发送功能是否正常。</p><p>时间: ' . date('Y-m-d H:i:s') . '</p>');
            
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = '测试邮件发送成功';
            } else {
                $response['message'] = '发送失败: ' . $result['message'];
            }
            break;
            
        default:
            $response['message'] = '未知操作';
    }
    
} catch (Exception $e) {
    $response['message'] = '系统错误: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);