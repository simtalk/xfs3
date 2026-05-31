<?php
/**
 * 数据库连接和操作类
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        global $db_config;
        
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $db_config['host'],
                $db_config['database'],
                $db_config['charset']
            );
            
            $this->pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // 用户相关操作
    public function addUser($userId, $nickname = '', $avatar = '', $note = '') {
        // 确保user_id长度合适
        $userId = substr($userId, 0, 64);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO users (user_id, nickname, avatar, note) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE nickname = VALUES(nickname), avatar = VALUES(avatar)
        ");
        return $stmt->execute([$userId, $nickname, $avatar, $note]);
    }
    
    public function getUsers() {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getUser($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function deleteUser($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    // 作品相关操作
    public function addNote($noteId, $userId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notes (note_id, user_id, title, cover_url, liked_count, collected_count, comment_count, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title),
                cover_url = VALUES(cover_url),
                liked_count = VALUES(liked_count),
                collected_count = VALUES(collected_count),
                comment_count = VALUES(comment_count),
                published_at = VALUES(published_at)
        ");
        
        return $stmt->execute([
            $noteId,
            $userId,
            $data['title'] ?? '',
            $data['cover_url'] ?? '',
            $data['liked_count'] ?? 0,
            $data['collected_count'] ?? 0,
            $data['comment_count'] ?? 0,
            $data['published_at'] ?? null
        ]);
    }
    
    public function getNotes($userId, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notes 
            WHERE user_id = ? 
            ORDER BY published_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getNoteCount($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function getLatestNote($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notes 
            WHERE user_id = ? 
            ORDER BY published_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    // 监控日志
    public function addLog($userId, $action, $oldCount = 0, $newCount = 0, $details = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO monitor_logs (user_id, action, old_count, new_count, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $action, $oldCount, $newCount, $details ? json_encode($details) : null]);
    }
    
    public function getLogs($userId = null, $limit = 100) {
        if ($userId) {
            $stmt = $this->pdo->prepare("
                SELECT l.*, u.nickname 
                FROM monitor_logs l 
                LEFT JOIN users u ON l.user_id = u.user_id 
                WHERE l.user_id = ? 
                ORDER BY l.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt = $this->pdo->query("
                SELECT l.*, u.nickname 
                FROM monitor_logs l 
                LEFT JOIN users u ON l.user_id = u.user_id 
                ORDER BY l.created_at DESC 
                LIMIT $limit
            ");
        }
        return $stmt->fetchAll();
    }
    
    // 邮件日志
    public function addEmailLog($userId, $email, $subject, $status = 0, $errorMsg = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_logs (user_id, email, subject, status, error_msg)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $email, $subject, $status, $errorMsg]);
    }
    
    public function getEmailLogs($userId = null, $limit = 50) {
        if ($userId) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT $limit");
        }
        return $stmt->fetchAll();
    }
    
    // 设置相关
    public function getSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT key_value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['key_value'] : $default;
    }
    
    public function setSetting($key, $value) {
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (key_name, key_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)
        ");
        return $stmt->execute([$key, $value]);
    }
}