<?php
/**
 * 小红书API抓取类
 * 使用 Playwright 自动化浏览器进行数据抓取
 */

require_once __DIR__ . '/playwright.php';

class XhsApi {
    private $playwright;
    
    public function __construct() {
        $this->playwright = new PlaywrightExecutor();
    }
    
    /**
     * 获取用户信息
     */
    public function getUserInfo($userId) {
        global $xhs_config;
        $cookie = $xhs_config['cookie'] ?? '';
        
        $result = $this->playwright->scrapeUserNotes($userId, $cookie);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? '获取用户信息失败'
            ];
        }
        
        $userData = $result['user'] ?? [];
        $notes = $result['notes'] ?? [];
        
        if (empty($userData['nickname']) && empty($notes)) {
            return [
                'success' => false,
                'message' => $result['error'] ?? '无法获取用户数据，请检查Cookie是否有效'
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'nickname' => $userData['nickname'] ?? $userId,
                'avatar' => $userData['avatar'] ?? '',
                'fans' => $userData['fans'] ?? 0,
            ]
        ];
    }
    
    /**
     * 获取用户作品列表
     */
    public function getUserNotes($userId, $page = 1, $pageSize = 20) {
        global $xhs_config;
        $cookie = $xhs_config['cookie'] ?? '';
        
        $result = $this->playwright->scrapeUserNotes($userId, $cookie);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? '获取作品列表失败',
                'notes' => []
            ];
        }
        
        $notes = $result['notes'] ?? [];
        
        return [
            'success' => true,
            'notes' => $notes,
            'total' => count($notes)
        ];
    }
    
    /**
     * 获取最新的作品
     */
    public function getLatestNotes($userId, $limit = 10) {
        global $xhs_config;
        $cookie = $xhs_config['cookie'] ?? '';
        
        $result = $this->playwright->scrapeUserNotes($userId, $cookie);
        
        if (!$result['success'] || empty($result['notes'])) {
            return [];
        }
        
        $notes = $result['notes'];
        
        usort($notes, function($a, $b) {
            return strtotime($b['published_at'] ?? 0) - strtotime($a['published_at'] ?? 0);
        });
        
        return array_slice($notes, 0, $limit);
    }
    
    /**
     * 通过分享链接获取用户ID
     */
    public function getUserIdFromShareUrl($shareUrl) {
        $patterns = [
            '/xiaohongshu\.com\/(?:discovery\/)?profile\/([a-zA-Z0-9]+)/',
            '/xhs\.cn\/([a-zA-Z0-9]+)/',
            '/www\.xhslink\.com\/([a-zA-Z0-9]+)/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $shareUrl, $matches)) {
                return $matches[1];
            }
        }
        
        if (preg_match('/^[a-zA-Z0-9]{8,}$/', trim($shareUrl))) {
            return trim($shareUrl);
        }
        
        return null;
    }
    
    /**
     * 检查状态
     */
    public function checkStatus() {
        return $this->playwright->checkAvailability();
    }
}