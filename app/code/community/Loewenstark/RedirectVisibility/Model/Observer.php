<?php

class Loewenstark_RedirectVisibility_Model_Observer
{
    /**
     * @mageEvent catalog_controller_product_init_before
     */
    public function redirectToMasterArticle($event)
    {
        $controller = $event->getControllerAction();
        /* @var $controller Mage_Core_Controller_Front_Action */
        $id = (int)$controller->getRequest()->getParam('id', 0);
        if($id)
        {
            $child = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToSelect(array('sku', 'visibility'))
                    ->addAttributeToFilter('entity_id', $id)
                    ->addStoreFilter()
                    ->setPage(1,1)
                    ->getFirstItem();
            /* @var $child Mage_Catalog_Model_Product */
            if($child && $child->getId() && $child->getVisibility() != Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
            {
                return $this;
            }
            $resource = Mage::getSingleton('core/resource');
            /* @var $resource Mage_Core_Model_Resource */
            $conn = $resource->getConnection('core_write');
            /* @var $conn Varien_Db_Adapter_Interface */

            $sql = $conn->select()
                    ->from($resource->getTableName('catalog_product_super_link'), 'parent_id')
                    ->where('product_id = ?', $child->getId());
            $parent_ids = $conn->fetchCol($sql);
            if($parent_ids)
            {
                $collection = Mage::getModel('catalog/product')->getCollection()
                        ->addAttributeToFilter('entity_id', array('in' => $parent_ids))
                        ->addStoreFilter()
                        ->addUrlRewrite(Mage::app()->getStore()->getRootCategoryId());
                Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
                Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($collection);
                $collection->setPage(1,1);
                $item = $collection->getFirstItem();
                /* @var $item Mage_Catalog_Model_Product */
                if($item && $item->getId())
                {
                    $url = $item->getProductUrl();
                    $controller->getResponse()->setRedirect($url, 301)->sendResponse();
                }
            }
        }
    }
}