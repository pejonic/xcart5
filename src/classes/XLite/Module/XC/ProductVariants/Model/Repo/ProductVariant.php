<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\XC\ProductVariants\Model\Repo;

/**
 * Product variants repository
 */
class ProductVariant extends \XLite\Model\Repo\ARepo
{
    /**
     * Allowable search params
     */
    const SEARCH_PRODUCT = 'product';

    const SKU_GENERATION_LIMIT = 50;
    const VARIANT_ID_GENERATION_LIMIT = 50;

    protected static $reservedIds = [];

    /**
     * Get default alias
     *
     * @return string
     */
    public function getDefaultAlias()
    {
        return 'v';
    }

    /**
     * Generate SKU
     *
     * @param string $sku SKU
     *
     * @return string
     */
    public function assembleUniqueSKU($sku)
    {
        $i = 0;
        $qb = $this->defineGenerateSKUQuery();
        $qbp = \XLite\Core\Database::getRepo('XLite\Model\Product')->defineGenerateSKUQuery();
        $base = $sku;

        while (
            $i < static::SKU_GENERATION_LIMIT
            && (
                0 < intval($qb->setParameter('sku', $sku)->getSingleScalarResult())
                || 0 < intval($qbp->setParameter('sku', $sku)->getSingleScalarResult())
            )
        ) {
            $i++;
            $newSku = substr(uniqid($base . '-', true), 0, 32);
            if ($newSku == $sku) {
                $newSku = md5($newSku);
            }
            $sku = $newSku;
        }

        if ($i >= static::SKU_GENERATION_LIMIT) {
            $sku = md5($sku . microtime(true));
        }

        return $sku;
    }

    /**
     * Define query for generate SKU
     *
     * @return \XLite\Model\QueryBuilder\AQueryBuilder
     */
    public function defineGenerateSKUQuery()
    {
        return $this->getQueryBuilder()
            ->from($this->_entityName, 'v')
            ->select('COUNT(v.id) cnt')
            ->andWhere('v.sku = :sku');
    }

    /**
     * @param string $part
     * @return string
     */
    private function processVariantIdPart($part)
    {
        $parts = preg_split("/(-|\s)/", $part);
        $parts = array_filter(array_map('trim', $parts));

        if (count($parts) == 1 && is_numeric(reset($parts))) {
            return array_pop($parts);
        }

        return implode('', array_map(function ($v) {
            return strtolower(mb_substr($v, 0, 1));
        }, $parts));
    }

    /**
     * @param array $parts
     * @return string
     */
    private function processVariantIdParts($parts)
    {
        return implode('-', array_filter(array_map(function ($v) {
            return $this->processVariantIdPart($v);
        }, $parts)));
    }

    /**
     * Generate SKU
     *
     * @param string|\XLite\Module\XC\ProductVariants\Model\ProductVariant $base
     * @param bool $reserveInRuntime Reserve id in runtime
     *
     * @return string
     */
    public function assembleUniqueVariantId($base, $reserveInRuntime = true)
    {
        if ($base instanceof \XLite\Module\XC\ProductVariants\Model\ProductVariant) {
            $parts = [];

            foreach ($base->getAttributeValueC() as $value) {
                $parts[] = $value->asString();
            }

            foreach ($base->getAttributeValueS() as $value) {
                $parts[] = $value->asString();
            }

            if ($base->getProduct()) {
                $base = $base->getProduct()->getSku();
            } elseif ($base->getOrderItems()->first()) {
                $base = $base->getOrderItems()->first()->getSku();
            } else {
                $base = 'v';
            }

            $base .= '-' . $this->processVariantIdParts($parts);
        }

        $i = 0;
        $qb = $this->defineGenerateVariantIdQuery();
        $variantId = $base;

        while (
            $i < static::VARIANT_ID_GENERATION_LIMIT
            && (
                0 < intval($qb->setParameter('variant_id', $variantId)->getSingleScalarResult())
                || in_array($variantId, static::$reservedIds)
            )
        ) {
            $i++;
            $newVariantId = mb_substr($base . '-' . $i, 0, 32);
            if ($newVariantId == $variantId) {
                $newVariantId = md5($newVariantId);
            }
            $variantId = $newVariantId;
        }

        if ($i >= static::VARIANT_ID_GENERATION_LIMIT) {
            $variantId = md5($variantId . microtime(true));
        }

        if ($reserveInRuntime) {
            static::$reservedIds[] = $variantId;
        }

        return $variantId;
    }

    /**
     * Define query for generate variant id
     *
     * @return \XLite\Model\QueryBuilder\AQueryBuilder
     */
    public function defineGenerateVariantIdQuery()
    {
        return $this->getQueryBuilder()
            ->from($this->_entityName, 'v')
            ->select('COUNT(v.id) cnt')
            ->andWhere('v.variant_id = :variant_id');
    }

    /**
     * Get modifier types by product
     *
     * @param \XLite\Model\Product $product Product
     *
     * @return array
     */
    public function getModifierTypesByProduct(\XLite\Model\Product $product)
    {
        $price = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product AND v.defaultPrice = :false')
            ->setParameter('product', $product)
            ->setParameter('false', false)
            ->setMaxResults(1)
            ->getResult();

        $quantity = $product->hasIncompleteVariantsList()
            || $this->createQueryBuilder('v')
                ->andWhere('v.product = :product AND v.defaultAmount = :false')
                ->setParameter('product', $product)
                ->setParameter('false', false)
                ->setMaxResults(1)
                ->getResult();

        $weight = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product AND v.defaultWeight = :false')
            ->setParameter('product', $product)
            ->setParameter('false', false)
            ->setMaxResults(1)
            ->getResult();

        $sku = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product AND v.sku IS NOT NULL AND v.sku != :empty')
            ->setParameter('product', $product)
            ->setParameter('empty', '')
            ->setMaxResults(1)
            ->getResult();

        return [
            'price'    => !empty($price),
            'quantity' => !empty($quantity),
            'weight'   => !empty($weight),
            'sku'      => !empty($sku),
        ];
    }

    /**
     * Prepare certain search condition
     *
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder Query builder to prepare
     * @param mixed $value Condition OPTIONAL
     *
     * @return void
     */
    protected function prepareCndProduct(\Doctrine\ORM\QueryBuilder $queryBuilder, $value = null)
    {
        if ($value) {
            $queryBuilder->andWhere('v.product = :product')
                ->setParameter('product', $value);
        }
    }

    /**
     * Update single entity
     *
     * @param \XLite\Model\AEntity $entity Entity to use
     * @param array $data Data to save OPTIONAL
     *
     * @return void
     */
    protected function performUpdate(\XLite\Model\AEntity $entity, array $data = [])
    {
        parent::performUpdate($entity, $data);

        if ($entity->getProduct()) {
            $entity->getProduct()->updateQuickData();
        }
    }
}
