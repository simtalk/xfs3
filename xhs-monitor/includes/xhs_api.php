<?php
/**
 * 小红书API抓取类
 * 支持 Playwright 和 cURL 两种方式
 */

require_once __DIR__ . '/playwright.php';

class XhsApi {
    private $baseUrl = 'https://www.xiaohongshu.com';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    /**
     * 获取用户信息
     * @param string $userId 用户ID
     * @return array
     */
    public function getUserInfo($userId) {
        // 先尝试使用 cURL（不依赖 Node.js）
        $result = $this->curlScrape($userId);
        
        if ($result['success']) {
            return $result;
        }
        
        // 备选：尝试 Playwright
        try {
            $playwright = new PlaywrightExecutor();
            $pwResult = $playwright->scrapeUserNotes($userId);
            
            if ($pwResult['success']) {
                $userData = $pwResult['user'] ?? [];
                return [
                    'success' => !empty($userData['nickname']),
                    'data' => [
                        'user_id' => $userId,
                        'nickname' => $userData['nickname'] ?? $userId,
                        'avatar' => $userData['avatar'] ?? '',
                        'fans' => $userData['fans'] ?? 0,
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $pwResult['error'] ?? '获取用户信息失败'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $result['message'] ?? $e->getMessage()
            ];
        }
    }
    
    /**
     * 使用 cURL 抓取数据
     */
    private function curlScrape($userId) {
        $url = "{$this->baseUrl}/user/profile/{$userId}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: ' . $this->userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Referer: https://www.xiaohongshu.com/'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (empty($response) || $httpCode !== 200) {
            return ['success' => false, 'message' => 'HTTP请求失败，状态码: ' . $httpCode];
        }
        
        // 检查是否需要登录
        if (stripos($response, '验证中心') !== false || stripos($response, 'captcha') !== false) {
            return ['success' => false, 'message' => '需要登录或遇到验证码'];
        }
        
        // 解析数据
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.+?});/s', $response, $matches)) {
            try {
                $data = json_decode($matches[1], true);
                if ($data && isset($data['user'])) {
                    return [
                        'success' => true,
                        'data' => [
                            'user_id' => $userId,
                            'nickname' => $data['user']['nickname'] ?? '',
                            'avatar' => $data['user']['avatar'] ?? '',
                            'fans' => $data['user']['fans'] ?? 0,
                        ]
                    ];
                }
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'JSON解析失败'];
            }
        }
        
        return ['success' => false, 'message' => '无法从页面提取用户信息'];
    }
    
    /**
     * 获取用户作品列表
     */
    public function getUserNotes($userId, $page = 1, $pageSize = 20) {
        // 使用 cURL
        $url = "{$this->baseUrl}/user/profile/{$userId}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: ' . $this->userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (empty($response)) {
            return ['success' => false, 'message' => '获取作品列表失败', 'notes' => []];
        }
        
        $notes = [];
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.+?});/s', $response, $matches)) {
            try {
                $data = json_decode($matches[1], true);
                if ($data && isset($data['noteList'])) {
                    foreach ($data['noteList'] as $note) {
                        $notes[] = [
                            'note_id' => $note['id'] ?? '',
                            'title' => $note['title'] ?? $note['display_title'] ?? '',
                            'cover_url' => $note['cover_url'] ?? '',
                            'liked_count' => (int)($note['liked_count'] ?? 0),
                            'published_at' => isset($note['time']) ? date('Y-m-d H:i:s', $note['time']) : null
                        ];
                    }
                }
            } catch (Exception $e) {
                // 解析失败
            }
        }
        
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
        $result = $this->getUserNotes($userId);
        
        if ($result['success'] && !empty($result['notes'])) {
            usort($result['notes'], function($a, $b) {
                return strtotime($b['published_at'] ?? 0) - strtotime($a['published_at'] ?? 0);
            });
            return array_slice($result['notes'], 0, $limit);
        }
        
        return [];
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
        
        // 直接输入纯ID
        if (preg_match('/^[a-zA-Z0-9]{8,}$/', trim($shareUrl))) {
            return trim($shareUrl);
        }
        
        return null;
    }
    
    /**
     * 检查状态
     */
    public function checkStatus() {
        // 检查 curl 是否可用
        $curlAvailable = function_exists('curl_init');
        
        // 检查 Playwright
        $playwrightAvailable = false;
        try {
            $playwright = new PlaywrightExecutor();
            $status = $playwright->checkAvailability();
            $playwrightAvailable = $status['node_available'] && $status['playwright_installed'];
        } catch (Exception $e) {
            // Playwright 不可用
        }
        
        return [
            'curl_available' => $curlAvailable,
            'playwright_available' => $playwrightAvailable,
            'method' => $playwrightAvailable ? 'playwright' : ($curlAvailable ? 'curl' : 'none')
        ];
    }
}