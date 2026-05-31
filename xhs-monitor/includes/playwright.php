<?php
/**
 * Playwright 执行器
 * PHP 调用 Node.js Playwright 脚本的封装
 */

class PlaywrightExecutor {
    private $nodePath;
    private $scriptPath;
    private $timeout = 120; // 超时时间（秒）
    
    public function __construct() {
        // 尝试自动检测 node 和 npm 路径
        $this->nodePath = $this->findNode();
        $this->scriptPath = __DIR__ . '/../scripts/scraper.js';
    }
    
    /**
     * 查找 Node.js 可执行文件路径
     */
    private function findNode() {
        $paths = ['node', '/usr/bin/node', '/usr/local/bin/node', '/opt/node/bin/node'];
        
        foreach ($paths as $path) {
            $result = exec($path . ' --version 2>/dev/null');
            if (!empty($result) && strpos($result, 'v') === 0) {
                return $path;
            }
        }
        
        return 'node';
    }
    
    /**
     * 执行 Playwright 脚本
     * @param string $command 命令 (scrape/resolve)
     * @param string $param 参数 (用户ID或链接)
     * @param string $cookie 可选的Cookie
     * @return array
     */
    public function execute($command, $param, $cookie = '') {
        // 检查脚本文件是否存在
        if (!file_exists($this->scriptPath)) {
            return [
                'success' => false,
                'error' => 'Playwright脚本文件不存在: ' . $this->scriptPath
            ];
        }
        
        // 构建命令
        $cmd = sprintf(
            '%s %s %s %s %s 2>&1',
            escapeshellcmd($this->nodePath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($command),
            escapeshellarg($param),
            $cookie ? escapeshellarg($cookie) : ''
        );
        
        // 执行命令
        $startTime = microtime(true);
        $output = shell_exec($cmd);
        $duration = round(microtime(true) - $startTime, 2);
        
        // 解析输出
        $result = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => '无法解析脚本输出',
                'raw_output' => $output,
                'duration' => $duration
            ];
        }
        
        $result['duration'] = $duration;
        return $result;
    }
    
    /**
     * 抓取用户作品
     */
    public function scrapeUserNotes($userId, $cookie = '') {
        return $this->execute('scrape', $userId, $cookie);
    }
    
    /**
     * 解析短链接
     */
    public function resolveShortUrl($url) {
        return $this->execute('resolve', $url);
    }
    
    /**
     * 检查 Playwright 是否可用
     */
    public function checkAvailability() {
        $nodeVersion = shell_exec($this->nodePath . ' --version 2>&1');
        $npmAvailable = exec('which npm 2>/dev/null') !== '';
        
        $scriptExists = file_exists($this->scriptPath);
        
        // 检查 node_modules 是否存在
        $playwrightInstalled = is_dir(__DIR__ . '/../node_modules/playwright');
        
        return [
            'node_available' => !empty(trim($nodeVersion)),
            'node_version' => trim($nodeVersion),
            'npm_available' => $npmAvailable,
            'script_exists' => $scriptExists,
            'playwright_installed' => $playwrightInstalled
        ];
    }
    
    /**
     * 安装 Playwright
     */
    public function install() {
        $cwd = __DIR__ . '/..';
        chdir($cwd);
        
        // 检查 package.json
        if (!file_exists($cwd . '/package.json')) {
            return ['success' => false, 'error' => 'package.json 不存在'];
        }
        
        // 执行 npm install
        $output = shell_exec('cd ' . escapeshellarg($cwd) . ' && npm install 2>&1');
        
        // 安装浏览器
        $output2 = shell_exec('cd ' . escapeshellarg($cwd) . ' && npx playwright install chromium 2>&1');
        
        return [
            'success' => true,
            'message' => 'Playwright 安装完成',
            'npm_output' => $output,
            'install_output' => $output2
        ];
    }
}