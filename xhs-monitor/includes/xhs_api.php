<?php
/**
 * 小红书API抓取类
 * 使用 Playwright 自动化浏览器进行数据抓取
 * 参考 Spider_XHS 的封装思路
 */

require_once __DIR__ . '/playwright.php';

class XhsApi {
    private $baseUrl = 'https://www.xiaohongshu.com';
    private $playwright;
    
    public function __construct() {
        $this->playwright = new PlaywrightExecutor();
    }
    
    /**
     * 获取用户信息
     * @param string $userId 用户ID
     * @return array
     */
    public function getUserInfo($userId) {
        $result = $this->playwright->scrapeUserNotes($userId);
        
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['error'] ?? '获取用户信息失败'];
        }
        
        $userData = $result['user'] ?? [];
        
        return [
            'success' => !empty($userData['nickname']),
            'data' => [
                'user_id' => $userId,
                'nickname' => $userData['nickname'] ?? '',
                'avatar' => $userData['avatar'] ?? '',
                'fans' => $userData['fans'] ?? 0,
                'liked' => 0,
                'collected' => 0,
                'tags' => []
            ]
        ];
    }
    
    /**
     * 获取用户作品列表
     * @param string $userId 用户ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getUserNotes($userId, $page = 1, $pageSize = 20) {
        $result = $this->playwright->scrapeUserNotes($userId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? '获取作品列表失败',
                'notes' => []
            ];
        }
        
        $notes = $result['notes'] ?? [];
        
        // 分页处理
        $start = ($page - 1) * $pageSize;
        $paginatedNotes = array_slice($notes, $start, $pageSize);
        
        return [
            'success' => true,
            'notes' => $paginatedNotes,
            'total' => count($notes),
            'page' => $page,
            'pageSize' => $pageSize
        ];
    }
    
    /**
     * 获取最新的作品
     * @param string $userId 用户ID
     * @param int $limit 数量
     * @return array
     */
    public function getLatestNotes($userId, $limit = 10) {
        $result = $this->playwright->scrapeUserNotes($userId);
        
        if (!$result['success'] || empty($result['notes'])) {
            return [];
        }
        
        $notes = $result['notes'];
        
        // 按发布时间排序（最新的在前）
        usort($notes, function($a, $b) {
            $timeA = strtotime($a['published_at'] ?? 0);
            $timeB = strtotime($b['published_at'] ?? 0);
            return $timeB - $timeA;
        });
        
        return array_slice($notes, 0, $limit);
    }
    
    /**
     * 通过分享链接获取用户ID
     * @param string $shareUrl 分享链接
     * @return string|null
     */
    public function getUserIdFromShareUrl($shareUrl) {
        // 处理各种分享链接格式
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
        
        // 尝试短链接解析
        if (strpos($shareUrl, 'xhslink.com') !== false || strpos($shareUrl, 'xhs.cn') !== false) {
            $result = $this->playwright->resolveShortUrl($shareUrl);
            if ($result['success'] && $result['user_id']) {
                return $result['user_id'];
            }
        }
        
        return null;
    }
    
    /**
     * 获取用户ID的有效性
     */
    public function validateUserId($userId) {
        $result = $this->getUserInfo($userId);
        return $result['success'];
    }
    
    /**
     * 检查 Playwright 是否可用
     */
    public function checkPlaywright() {
        return $this->playwright->checkAvailability();
    }
    
    /**
     * 安装 Playwright
     */
    public function installPlaywright() {
        return $this->playwright->install();
    }
}