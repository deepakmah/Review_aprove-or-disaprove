<?php

namespace Exinent\DisableNewsletterSuccess\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\ScopeInterface;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ReviewObserver implements ObserverInterface
{
    protected $_scopeConfig;
    protected $logger;
    protected $transportBuilder;
    protected $_productRepository;
    protected $_reviewFactory;
    protected $_storeManager;
    protected $resource;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        TransportBuilder $transportBuilder,
        ProductRepositoryInterface $productRepository,
        ReviewFactory $reviewFactory,
        StoreManagerInterface $storeManager,
        ResourceConnection $resource
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
        $this->_storeManager = $storeManager;
        $this->_productRepository = $productRepository;
        $this->_reviewFactory = $reviewFactory;
        $this->resource = $resource;
    }

    public function execute(Observer $observer)
    {
        $review = $observer->getEvent()->getDataObject();
        $reviewId = $review->getReviewId();
        $connection = $this->resource->getConnection();

        // Load review details
        $reviewData = $this->_reviewFactory->create()->load($reviewId);

        // Skip email if manually approved/disapproved
        if ($reviewData->getStatusId() == Review::STATUS_NOT_APPROVED) {
            return;
        }

        // Check if this review was auto-approved before
        $tableName = $this->resource->getTableName('review');
        $isAutoApproved = $connection->fetchOne("SELECT is_auto_approved FROM {$tableName} WHERE review_id = ?", [$reviewId]);

        if (!$isAutoApproved) {
            // If it's the first time, send the email and set the flag
            $this->sendReviewEmail($reviewData);

            // Mark review as auto-approved in DB
            $connection->update($tableName, ['is_auto_approved' => 1], ['review_id = ?' => $reviewId]);
        }
    }

    private function sendReviewEmail($reviewData)
    {
        $productId = $reviewData->getEntityPkValue();
        $product = $this->_productRepository->getById($productId);
        $store = $this->_storeManager->getStore();
    
        // Approve/Disapprove links
        $approveLink = $store->getBaseUrl() . 'review/action/approve?id=' . $reviewData->getReviewId();
        $disapproveLink = $store->getBaseUrl() . 'review/action/disapprove?id=' . $reviewData->getReviewId();
    
        // Get review details
        $reviewTitle = $reviewData->getTitle();
        $reviewDetail = $reviewData->getDetail();
        $reviewDate = $reviewData->getCreatedAt();
        $productSku = $product->getSku();
        $productLink = $store->getBaseUrl() . 'catalog/product/view/id/' . $productId;
    
        // Email template parameters
        $templateParams = [
            'review_id' => $reviewData->getReviewId(),
            'product_name' => $product->getName(),
            'sku' => $productSku,
            'rtitle' => $reviewTitle,
            'rdetail' => $reviewDetail,
            'rdate' => $reviewDate,
            'prodlink' => $productLink,
            'approve_link' => $approveLink,
            'disapprove_link' => $disapproveLink
        ];
    
        // Multiple Recipients
        $receiverEmails = [
            'sandeep.kumar@exinent.com',
            'deepak.maheshwari@exinent.com'
            // 'rostrom@allergypreventionteam.com'
        ];
        
        $receiverName = $this->_scopeConfig->getValue('trans_email/ident_support/name', ScopeInterface::SCOPE_STORE);
    
        // Prepare Email
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('review_email_observer')
            ->setTemplateOptions(['area' => 'frontend', 'store' => $store->getId()])
            ->setTemplateVars($templateParams)
            ->setFrom('general');
    
        // Add multiple recipients
        foreach ($receiverEmails as $email) {
            $transport->addTo($email, $receiverName);
        }
    
        $transport = $transport->getTransport();
    
        try {
            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
    
    

}
