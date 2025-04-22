<?php

namespace Exinent\DisableNewsletterSuccess\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $connection = $setup->getConnection();
            $tableName = $setup->getTable('review');

            // Check if column already exists
            if (!$connection->tableColumnExists($tableName, 'is_auto_approved')) {
                $connection->addColumn(
                    $tableName,
                    'is_auto_approved',
                    [
                        'type' => Table::TYPE_SMALLINT,
                        'nullable' => false,
                        'default' => 0,
                        'comment' => 'Is Auto Approved'
                    ]
                );
            }
        }

        $setup->endSetup();
    }
}
