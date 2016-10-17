<?php

namespace Oro\Bundle\InventoryBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\CatalogBundle\Fallback\Provider\CategoryFallbackProvider;
use Oro\Bundle\CatalogBundle\Fallback\Provider\ParentCategoryFallbackProvider;
use Oro\Bundle\EntityBundle\Fallback\Provider\SystemConfigFallbackProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\InventoryBundle\Migrations\Schema\v1_0\RenameInventoryConfigSectionQuery;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\MigrationBundle\Migration\Extension\RenameExtension;
use Oro\Bundle\MigrationBundle\Migration\Extension\RenameExtensionAwareInterface;

class OroInventoryBundleInstaller implements Installation, ExtendExtensionAwareInterface, RenameExtensionAwareInterface
{
    const INVENTORY_LEVEL_TABLE_NAME = 'oro_inventory_level';
    const OLD_WAREHOUSE_INVENTORY_TABLE = 'oro_warehouse_inventory_lev';
    const ORO_B2B_WAREHOUSE_INVENTORY_TABLE = 'orob2b_warehouse_inventory_lev';

    /** @var ExtendExtension */
    protected $extendExtension;

    /**
     * @var RenameExtension
     */
    private $renameExtension;

    /**
     * {@inheritdoc}
     */
    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function setRenameExtension(RenameExtension $renameExtension)
    {
        $this->renameExtension = $renameExtension;
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        if (($schema->hasTable(self::OLD_WAREHOUSE_INVENTORY_TABLE) ||
                $schema->hasTable(self::ORO_B2B_WAREHOUSE_INVENTORY_TABLE))
            && !$schema->hasTable(self::INVENTORY_LEVEL_TABLE_NAME))
        {
            $this->renameTablesUpdateRelation($schema, $queries);

            return;
        }

        /** Tables generation **/
        $this->createOroInventoryLevelTable($schema);

        /** Foreign keys generation **/
        $this->addOroInventoryLevelForeignKeys($schema);

        $this->addManageInventoryFieldToProduct($schema);
        $this->addManageInventoryFieldToCategory($schema);

        $queries->addPostQuery(
            new RenameInventoryConfigSectionQuery('oro_warehouse', 'oro_inventory', 'manage_inventory')
        );
    }

    protected function renameTablesUpdateRelation(Schema $schema, QueryBag $queries)
    {
        $extension = $this->renameExtension;

        // rename orob2b namespace
        if ($schema->hasTable(self::ORO_B2B_WAREHOUSE_INVENTORY_TABLE)) {
            $extension->renameTable($schema, $queries, self::ORO_B2B_WAREHOUSE_INVENTORY_TABLE, self::OLD_WAREHOUSE_INVENTORY_TABLE);
            $schema->getTable(self::ORO_B2B_WAREHOUSE_INVENTORY_TABLE)->dropIndex('uidx_orob2b_wh_wh_inventory_lev');
            $extension->addUniqueIndex(
                $schema,
                $queries,
                self::OLD_WAREHOUSE_INVENTORY_TABLE,
                ['warehouse_id', 'product_unit_precision_id'],
                'uidx_oro_wh_wh_inventory_lev'
            );
        }

        if ($schema->hasTable(self::OLD_WAREHOUSE_INVENTORY_TABLE)) {
            // drop warehouse indexes
            $schema->getTable(self::OLD_WAREHOUSE_INVENTORY_TABLE)->dropIndex('uidx_oro_wh_wh_inventory_lev');

            // drop warehouse column
            $inventoryTable = $schema->getTable(self::OLD_WAREHOUSE_INVENTORY_TABLE);
            $warehouseForeignKey = $this->getConstraintName($inventoryTable, 'warehouse_id');
            $inventoryTable->removeForeignKey($warehouseForeignKey);

            // rename entity
            $extension->renameTable($schema, $queries, self::OLD_WAREHOUSE_INVENTORY_TABLE, self::INVENTORY_LEVEL_TABLE_NAME);
        }
    }

    /**
     * Create oro_inventory_level table
     *
     * @param Schema $schema
     */
    protected function createOroInventoryLevelTable(Schema $schema)
    {
        $table = $schema->createTable(self::INVENTORY_LEVEL_TABLE_NAME);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('quantity', 'decimal', ['precision' => 20, 'scale' => 10]);
        $table->addColumn('product_id', 'integer');
        $table->addColumn('product_unit_precision_id', 'integer');
        $table->setPrimaryKey(['id']);
    }

    /**
     * Add oro_inventory_level foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroInventoryLevelForeignKeys(Schema $schema)
    {
        $table = $schema->getTable(self::INVENTORY_LEVEL_TABLE_NAME);

        /** PRODUCT */
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_product'),
            ['product_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );

        /** PRODUCT UNIT PRECISION */
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_product_unit_precision'),
            ['product_unit_precision_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * @param Schema $schema
     */
    protected function addManageInventoryFieldToProduct(Schema $schema)
    {
        $productTable = $schema->getTable('oro_product');
        $fallbackTable = $schema->getTable('oro_entity_fallback_value');
        $this->extendExtension->addManyToOneRelation(
            $schema,
            $productTable,
            'manageInventory',
            $fallbackTable,
            'id',
            [
                'entity' => [
                    'label' => 'oro.inventory.manage_inventory.label',
                ],
                'extend' => [
                    'owner' => ExtendScope::OWNER_CUSTOM,
                    'cascade' => ['all'],
                ],
                'form' => [
                    'is_enabled' => false,
                ],
                'view' => [
                    'is_displayable' => false,
                ],
                'fallback' => [
                    'fallbackList' => [
                        CategoryFallbackProvider::FALLBACK_ID => ['fieldName' => 'manageInventory'],
                        SystemConfigFallbackProvider::FALLBACK_ID => ['configName' => 'oro_inventory.manage_inventory'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param Schema $schema
     */
    protected function addManageInventoryFieldToCategory(Schema $schema)
    {
        $categoryTable = $schema->getTable('oro_catalog_category');
        $fallbackTable = $schema->getTable('oro_entity_fallback_value');
        $this->extendExtension->addManyToOneRelation(
            $schema,
            $categoryTable,
            'manageInventory',
            $fallbackTable,
            'id',
            [
                'entity' => [
                    'label' => 'oro.inventory.manage_inventory.label',
                ],
                'extend' => [
                    'owner' => ExtendScope::OWNER_CUSTOM,
                    'cascade' => ['all'],
                ],
                'form' => [
                    'is_enabled' => false,
                ],
                'view' => [
                    'is_displayable' => false,
                ],
                'fallback' => [
                    'fallbackList' => [
                        ParentCategoryFallbackProvider::FALLBACK_ID => ['fieldName' => 'manageInventory'],
                        SystemConfigFallbackProvider::FALLBACK_ID => ['configName' => 'oro_inventory.manage_inventory'],
                    ],
                ],
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getMigrationVersion()
    {
        return 'v1_0';
    }
}
