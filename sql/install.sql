CREATE TABLE {prefix}commandes_fournisseurs
(
    `id_supplier_order`    INT         UNSIGNED AUTO_INCREMENT PRIMARY KEY, # inutile, mais obligatoire pour ObjectModel
    `reference`            VARCHAR(64)          NOT NULL,                   # actuellement 32 mais à 64 dans les dernières versions
    `id_product`           INT(10)     UNSIGNED NOT NULL,                   # utilisé au cas ou on utiliserai la données plus tard.
    `id_product_attribute` INT(10)     UNSIGNED,                            # utilisé au cas ou on utiliserai la données plus tard.
    `date`                 DATE                 NOT NULL
)
    ENGINE = InnoDB
    COLLATE = utf8_unicode_ci;

CREATE INDEX commandes_fournisseurs_index ON {prefix}commandes_fournisseurs (reference);


CREATE TABLE IF NOT EXISTS {prefix}mq_stock_permanent (
    id_product INT UNSIGNED NOT NULL,
    state TINYINT UNSIGNED NOT NULL,
    constraint mq_stock_permanent_pk primary key (id_product)
);