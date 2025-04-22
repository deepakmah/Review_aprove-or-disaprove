<?php

namespace Exinent\DisableNewsletterSuccess\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Magento\Framework\App\ResourceConnection;

class ReviewSaveBeforeObserver implements ObserverInterface
{
    protected $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function execute(Observer $observer)
    {
        $review = $observer->getDataByKey('object');
        $connection = $this->resource->getConnection();

        // Log for debugging
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/reviews.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('Review Save Before Triggered');

        // Check for spam keywords
        $valid = !(
            strpos($review->getDetail(), 'href') !== false ||
            strpos($review->getDetail(), 'SELECT') !== false ||
            strpos($review->getDetail(), 'delay') !== false ||
            strpos($review->getDetail(), 'sleep') !== false
        );

        // Auto-approve if rating >= 4
        $rating = $review->getRatings();
        if ($valid && isset($rating[1]) && $rating[1] >= 4) {
            $review->setStatusId(Review::STATUS_APPROVED);

            // Mark the review as auto-approved
            $tableName = $this->resource->getTableName('review');
            $connection->update($tableName, ['is_auto_approved' => 1], ['review_id = ?' => $review->getReviewId()]);
        }
    }
}
