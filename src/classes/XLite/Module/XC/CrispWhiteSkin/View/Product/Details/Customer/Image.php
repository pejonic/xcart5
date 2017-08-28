<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\XC\CrispWhiteSkin\View\Product\Details\Customer;

/**
 * Image
 */
class Image extends \XLite\View\Product\Details\Customer\Image implements \XLite\Base\IDecorator
{
    /**
     * Return true if image is zoomable
     *
     * @param $image \XLite\Model\Image\Product\Image
     *
     * @return boolean
     */
    protected function isImageZoomable($image)
    {
        return $image->getWidth() > 366 || $image->getHeight() > 440;
    }

    /**
     * Register CSS files
     *
     * @return array
     */
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();
        $list[] = 'product/details/parts/cloud-zoom.css';

        return $list;
    }

    /**
     * Get zoom layer width
     *
     * @return integer
     */
    protected function getZoomWidth()
    {
        return 366;
    }

    /**
     * Get zoom layer height
     *
     * @return integer
     */
    protected function getZoomHeight()
    {
        return 440;
    }
}
