<?php
/**
 * 邮件发送类 - 使用QQ邮箱SMTP
 */

class Mailer {
    private $config;
    
    public function __construct() {
        global $smtp_config;
        $this->config = $smtp_config;
    }
    
    /**
     * 发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件正文
     * @param bool $isHtml 是否为HTML格式
     * @return array ['success' => bool, 'message' => string]
     */
    public function send($to, $subject, $body, $isHtml = true) {
        if (empty($this->config['username']) || $this->config['username'] === 'your-email@qq.com') {
            return ['success' => false, 'message' => 'SMTP配置未完成'];
        }
        
        $to = trim($to);
        $subject = $this->encodeSubject($subject);
        
        $headers = [
            'From: ' . $this->encodeHeader($this->config['from_name']) . ' <' . $this->config['username'] . '>',
            'To: ' . $to,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8'),
            'Date: ' . date('r')
        ];
        
        $message = $isHtml ? $this->buildHtmlEmail($body) : $body;
        
        // 使用fsockopen发送邮件
        return $this->sendViaSmtp($to, $headers, $message);
    }
    
    /**
     * 通过SMTP发送邮件
     */
    private function sendViaSmtp($to, $headers, $message) {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $username = $this->config['username'];
        $password = $this->config['password'];
        
        // 基础认证
        $auth = base64_encode($username . "\0" . $password);
        
        // 构建header字符串
        $headerStr = implode("\r\n", $headers);
        
        // 使用PHPMailer风格的发送方式
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $errno = 0;
        $errstr = '';
        
        // 根据端口选择协议
        $protocol = ($port == 465) ? 'ssl://' : '';
        
        $socket = @stream_socket_client(
            $protocol . $host . ':' . $port,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            return ['success' => false, 'message' => "连接SMTP服务器失败: $errstr ($errno)"];
        }
        
        stream_set_timeout($socket, 30);
        
        // 读取服务器响应
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP服务器响应异常: ' . $response];
        }
        
        // 发送HELO
        $this->sendCommand($socket, "EHLO " . gethostname());
        $this->readResponse($socket);
        
        // STARTTLS if needed
        if ($port == 587) {
            $this->sendCommand($socket, "STARTTLS");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) === '220') {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . gethostname());
                $this->readResponse($socket);
            }
        }
        
        // AUTH LOGIN
        $this->sendCommand($socket, "AUTH LOGIN");
        $this->readResponse($socket);
        
        // 发送用户名和密码
        $this->sendCommand($socket, base64_encode($username));
        $this->readResponse($socket);
        
        $this->sendCommand($socket, base64_encode($password));
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP认证失败'];
        }
        
        // MAIL FROM
        $this->sendCommand($socket, "MAIL FROM: <{$username}>");
        $this->readResponse($socket);
        
        // RCPT TO
        $this->sendCommand($socket, "RCPT TO: <{$to}>");
        $this->readResponse($socket);
        
        // DATA
        $this->sendCommand($socket, "DATA");
        $this->readResponse($socket);
        
        // 发送邮件内容
        $emailContent = $headerStr . "\r\n\r\n" . $message . "\r\n.";
        $this->sendCommand($socket, $emailContent);
        $response = $this->readResponse($socket);
        
        // QUIT
        $this->sendCommand($socket, "QUIT");
        $this->readResponse($socket);
        
        fclose($socket);
        
        if (substr($response, 0, 3) === '250') {
            return ['success' => true, 'message' => '邮件发送成功'];
        } else {
            return ['success' => false, 'message' => '邮件发送失败: ' . $response];
        }
    }
    
    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
    }
    
    private function readResponse($socket) {
        $response = fgets($socket, 515);
        return $response;
    }
    
    /**
     * 构建HTML邮件
     */
    private function buildHtmlEmail($body) {
        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f5f5; padding: 20px; }
.container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.header { background: linear-gradient(135deg, #fe2c55, #ff6b8a); color: #fff; padding: 30px 20px; text-align: center; }
.header h1 { margin: 0; font-size: 24px; font-weight: 600; }
.content { padding: 30px 20px; }
.alert { background: #fff5f5; border-left: 4px solid #fe2c55; padding: 15px; margin: 15px 0; border-radius: 4px; }
.note-card { background: #fafafa; border-radius: 8px; padding: 20px; margin: 15px 0; }
.note-title { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 10px; }
.note-meta { color: #999; font-size: 14px; }
.stats { display: flex; gap: 20px; margin-top: 15px; }
.stat { text-align: center; }
.stat-value { font-size: 20px; font-weight: 600; color: #fe2c55; }
.stat-label { font-size: 12px; color: #999; }
.footer { background: #f9f9f9; padding: 20px; text-align: center; color: #999; font-size: 12px; }
.btn { display: inline-block; background: #fe2c55; color: #fff; padding: 12px 30px; border-radius: 25px; text-decoration: none; font-weight: 500; margin-top: 20px; }
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>📕 小红书作品更新提醒</h1>
</div>
<div class="content">
' . $body . '
</div>
<div class="footer">
<p>此邮件由系统自动发送，请勿回复</p>
</div>
</div>
</body>
</html>';
    }
    
    /**
     * 编码邮件主题
     */
    private function encodeSubject($subject) {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }
    
    /**
     * 编码邮件头
     */
    private function encodeHeader($string) {
        return '=?UTF-8?B?' . base64_encode($string) . '?=';
    }
    
    /**
     * 发送更新提醒邮件
     */
    public function sendUpdateNotification($to, $user, $newNotes, $emailTemplate = 'default') {
        $subject = '📕 ' . ($user['nickname'] ?: '某用户') . ' 发布了新作品！';
        
        $notesHtml = '';
        foreach ($newNotes as $note) {
            $publishedTime = $note['published_at'] ? date('Y-m-d H:i', strtotime($note['published_at'])) : '未知时间';
            $notesHtml .= '<div class="note-card">
<div class="note-title">📝 ' . htmlspecialchars($note['title'] ?: '无标题') . '</div>
<div class="note-meta">🕐 发布时间: ' . $publishedTime . '</div>
<div class="stats">
<div class="stat"><span class="stat-value">❤️ ' . number_format($note['liked_count']) . '</span><br><span class="stat-label">点赞</span></div>
<div class="stat"><span class="stat-value">⭐ ' . number_format($note['collected_count']) . '</span><br><span class="stat-label">收藏</span></div>
<div class="stat"><span class="stat-value">💬 ' . number_format($note['comment_count']) . '</span><br><span class="stat-label">评论</span></div>
</div>
</div>';
        }
        
        $body = '<div class="alert">
<p>👋 您好！您关注的小红书用户 <strong>' . htmlspecialchars($user['nickname'] ?: '用户') . '</strong> 刚刚发布了 ' . count($newNotes) . ' 篇新作品！</p>
</div>
' . $notesHtml . '
<p style="text-align: center;">
<a href="https://www.xiaohongshu.com/user/profile/' . $user['user_id'] . '" class="btn" target="_blank">去小红书查看</a>
</p>';
        
        return $this->send($to, $subject, $body, true);
    }
}