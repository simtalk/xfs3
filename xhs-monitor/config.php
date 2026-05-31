<?php
/**
 * 小红书用户作品更新提醒系统 - 配置文件
 * 
 * 配置说明：
 * 1. 将此文件复制为 config.php
 * 2. 修改下方配置项
 * 3. 确保 data 目录可写
 */

// 数据库配置
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'xhs_monitor',
    'charset' => 'utf8mb4'
];

// QQ邮箱SMTP配置
$smtp_config = [
    'host' => 'smtp.qq.com',
    'port' => 587,
    'username' => 'your-email@qq.com',      // 替换为您的QQ邮箱
    'password' => 'your-auth-code',          // 替换为QQ邮箱授权码
    'from_name' => '小红书监控提醒',
    'debug' => false
];

// 小红书Cookie配置（用于登录状态抓取）
$xhs_config = [
    'cookie' => '',  // 填入小红书登录后的Cookie
];

// 监控配置
$monitor_config = [
    'check_interval' => 600,  // 检查间隔（秒），默认10分钟
    'max_retry' => 3,         // 最大重试次数
    'log_days' => 30           // 日志保留天数
];

// 安全配置
$security_config = [
    'admin_password' => 'admin123',  // 管理后台密码，请修改
    'session_timeout' => 3600         // 会话超时时间（秒）
];