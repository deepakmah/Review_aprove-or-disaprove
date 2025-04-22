<?php
namespace Exinent\DisableNewsletterSuccess\Controller\Action;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Review\Model\Review;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Store\Model\StoreManagerInterface;

class Approve extends Action
{
    protected $reviewModel;
    protected $productRepository;
    protected $storeManager;
    protected $resultRedirectFactory;

    public function __construct(
        Context $context,
        Review $reviewModel,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        RedirectFactory $resultRedirectFactory
    ) {
        parent::__construct($context);
        $this->reviewModel = $reviewModel;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function execute()
    {
        $reviewId = (int) $this->getRequest()->getParam('id');
        if ($reviewId) {
            $review = $this->reviewModel->load($reviewId);
            if ($review->getId()) {
                $review->setStatusId(Review::STATUS_APPROVED)->save();
                $this->messageManager->addSuccessMessage(__('Review approved successfully.'));

                // Get product ID and generate product URL
                $productId = $review->getEntityPkValue();
                $product = $this->productRepository->getById($productId);
                $productUrl = $this->storeManager->getStore()->getBaseUrl() . $product->getUrlKey() . '.html#reviews';

                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setUrl($productUrl);
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setRefererUrl();
    }
}
