<?php
/**
 * 数据库初始化脚本
 * 运行此脚本创建所需的数据库表
 */

// 自动初始化：如果 config.php 不存在，复制示例文件
$configFile = dirname(__FILE__) . '/config.php';
$configExample = dirname(__FILE__) . '/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}

require_once dirname(__FILE__) . '/config.php';

// 创建数据库连接
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']}",
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 创建数据库
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$db_config['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE {$db_config['database']}");
    
    // 创建用户表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(255) NOT NULL UNIQUE COMMENT '小红书用户ID',
            `nickname` VARCHAR(255) DEFAULT '' COMMENT '用户昵称',
            `avatar` VARCHAR(512) DEFAULT '' COMMENT '头像URL',
            `note` VARCHAR(255) DEFAULT '' COMMENT '备注',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 创建作品表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `note_id` VARCHAR(255) NOT NULL UNIQUE COMMENT '作品ID',
            `user_id` VARCHAR(255) NOT NULL COMMENT '用户ID',
            `title` VARCHAR(500) DEFAULT '' COMMENT '作品标题',
            `cover_url` VARCHAR(512) DEFAULT '' COMMENT '封面URL',
            `liked_count` INT DEFAULT 0 COMMENT '点赞数',
            `collected_count` INT DEFAULT 0 COMMENT '收藏数',
            `comment_count` INT DEFAULT 0 COMMENT '评论数',
            `published_at` DATETIME DEFAULT NULL COMMENT '发布时间',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_note_id` (`note_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_published_at` (`published_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 创建监控记录表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `monitor_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(255) NOT NULL COMMENT '用户ID',
            `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
            `old_count` INT DEFAULT 0 COMMENT '旧数量',
            `new_count` INT DEFAULT 0 COMMENT '新数量',
            `details` TEXT DEFAULT NULL COMMENT '详情JSON',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 创建邮件发送记录表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `email_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(255) NOT NULL COMMENT '用户ID',
            `email` VARCHAR(255) NOT NULL COMMENT '邮箱地址',
            `subject` VARCHAR(500) NOT NULL COMMENT '邮件主题',
            `status` TINYINT DEFAULT 0 COMMENT '状态: 0=待发送, 1=已发送, 2=失败',
            `error_msg` TEXT DEFAULT NULL COMMENT '错误信息',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 创建设置表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `key_name` VARCHAR(100) NOT NULL UNIQUE,
            `key_value` TEXT DEFAULT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "数据库初始化成功！\n";
    echo "请配置 config.php 后重新运行。\n";
    
} catch (PDOException $e) {
    echo "数据库初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}