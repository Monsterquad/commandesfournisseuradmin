<?php

declare(strict_types=1);

class StockPermanent
{
    public static function setStateForProduct(int $id_product, int $state): void
    {
        $sql = sprintf('REPLACE INTO %s%s (id_product, state) VALUES (%s, %s)', _DB_PREFIX_, 'mq_stock_permanent', $id_product, $state);
        if (!Db::getInstance()->execute($sql)) {
            throw new Exception('Erreur sql StockPermanent : ' . Db::getInstance()->getMsgError());
        }
    }

    public static function getStateForProduct(int $id_product): int
    {
        $query = new DbQuery();
        $query->select('state')
            ->from('mq_stock_permanent')
            ->where(sprintf('id_product=%d', $id_product));
        $state = \Db::getInstance()->getValue($query);
        if (\Db::getInstance()->getMsgError() !== '') {
            throw new Exception(sprintf('Erreur sql StockPermanent : %s : %s', $query->build(), \Db::getInstance()->getMsgError()));
        }

        return (int) $state;
    }

    /**
     * @param array<int, int> $productIds id_order_detail => id_product
     *
     * @return array<int, int> id_product => state
     */
    public static function getStateForProducts(array $productIds): array
    {
        $productIds = array_filter($productIds);
        if (empty($productIds)) {
            return [];
        }

        $query = new DbQuery();
        $query->select('state, id_product')
            ->from('mq_stock_permanent')
            ->where(sprintf('id_product IN (%s)', implode(',', $productIds)));
        $states = \Db::getInstance()->executeS($query);
        if (!is_array($states)) {
            throw new Exception(sprintf('Erreur sql StockPermanent : %s : %s', $query->build(), \Db::getInstance()->getMsgError()));
        }

        return array_column($states, 'state', 'id_product');
    }
}
