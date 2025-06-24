CREATE TABLE IF NOT EXISTS `{prefix}mq_supplier_requests` (
    `id_request` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference` varchar(32) NOT NULL,
    `id_product` int(11) UNSIGNED NOT NULL,
    `id_product_attribute` int(11) UNSIGNED DEFAULT 0,
    `date_request` datetime NOT NULL,
    PRIMARY KEY (`id_request`),
    INDEX `idx_reference` (`reference`),
    INDEX `idx_product` (`id_product`),
    INDEX `idx_date` (`date_request`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 