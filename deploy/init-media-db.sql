CREATE DATABASE IF NOT EXISTS `withu_media` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS `withu`@`127.0.0.1` IDENTIFIED BY 'withu_dev';
GRANT ALL PRIVILEGES ON `withu_media`.* TO `withu`@`127.0.0.1`;

CREATE USER IF NOT EXISTS `withu`@`localhost` IDENTIFIED BY 'withu_dev';
GRANT ALL PRIVILEGES ON `withu_media`.* TO `withu`@`localhost`;

FLUSH PRIVILEGES;
