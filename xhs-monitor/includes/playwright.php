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
     * 使用 file_exists 和 which 的替代方案
     */
    private function findNode() {
        $paths = ['node', '/usr/bin/node', '/usr/local/bin/node', '/opt/node/bin/node', '/usr/local/bin/node'];
        
        // 尝试通过文件检查和简单命令测试
        foreach ($paths as $path) {
            // 检查文件是否存在
            if (file_exists($path) || $path === 'node') {
                // 使用 proc_open 代替 exec，避免被禁用
                $descriptorspec = [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ];
                
                $process = proc_open($path . ' --version', $descriptorspec, $pipes);
                if (is_resource($process)) {
                    $output = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    
                    if (!empty($output) && strpos($output, 'v') === 0) {
                        return $path;
                    }
                }
            }
        }
        
        return 'node';
    }
    
    /**
     * 执行命令（替代exec）
     */
    private function runCommand($cmd, &$output = '', &$returnCode = 0) {
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            return false;
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        return $returnCode === 0;
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
            '%s %s %s %s %s',
            escapeshellcmd($this->nodePath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($command),
            escapeshellarg($param),
            $cookie ? escapeshellarg($cookie) : ''
        );
        
        // 执行命令
        $startTime = microtime(true);
        
        $this->runCommand($cmd, $output, $returnCode);
        
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
        // 检查node版本
        $nodeVersion = '';
        $this->runCommand($this->nodePath . ' --version', $nodeVersion);
        
        // 检查npm
        $npmPath = dirname($this->nodePath) . '/npm';
        $npmAvailable = file_exists($npmPath) || $this->runCommand('which npm', $output);
        
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
        
        // 检查 package.json
        if (!file_exists($cwd . '/package.json')) {
            return ['success' => false, 'error' => 'package.json 不存在'];
        }
        
        // 执行 npm install
        $this->runCommand('cd ' . escapeshellarg($cwd) . ' && npm install', $npmOutput);
        
        // 安装浏览器
        $this->runCommand('cd ' . escapeshellarg($cwd) . ' && npx playwright install chromium', $installOutput);
        
        return [
            'success' => true,
            'message' => 'Playwright 安装完成',
            'npm_output' => $npmOutput,
            'install_output' => $installOutput
        ];
    }
}