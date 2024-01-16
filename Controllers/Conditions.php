<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\CategoryManager;
use Mikroatlas\Models\CategoryType;
use Mikroatlas\Models\LinkManager;
use Mikroatlas\Models\MetadataManager;
use Mikroatlas\Models\MetadataOwner;

/**
 * @see Controller
 */
class Conditions extends Controller
{

    /**
     * @inheritDoc
     * @param array $args Array of category URLs to enter
     */
    public function process(array $args = []): int
    {
        $catManager = new CategoryManager();
        $metaManager = new MetadataManager();
        $linkManager = new LinkManager();
        self::$data['layout']['title'] = 'Seznam onemocnění';
        self::$data['layout']['page_id'] = 'conditions';

        $conditionId = $catManager->loadCategoryId($args, CategoryType::CONDITION);

        self::$data['conditions']['folder'] = $catManager->loadCategoryPath($conditionId, CategoryType::CONDITION);

        self::$data['conditions']['categories'] = $catManager->loadChildrenCategories($conditionId, CategoryType::CONDITION);
        self::$data['conditions']['metadata'] = $metaManager->loadAllMetadata($conditionId, MetadataOwner::CONDITION);
        self::$data['conditions']['microbes'] = $linkManager->loadMicrobes($conditionId);

        self::$views[] = 'conditions';
        self::$cssFiles[] = 'categories';

        return 200;
    }
}

