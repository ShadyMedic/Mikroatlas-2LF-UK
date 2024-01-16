<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\CategoryManager;
use Mikroatlas\Models\CategoryType;

/**
 * @see Controller
 */
class BrowseMicrobes extends Controller
{

    /**
     * @inheritDoc
     * @param array $args Array of category URLs to enter
     */
    public function process(array $args = []): int
    {
        $catManager = new CategoryManager();
        self::$data['layout']['title'] = 'Seznam mikroorganismů';
        self::$data['layout']['page_id'] = 'browse-mikrobes';

        $categoryId = $catManager->loadCategoryId($args, CategoryType::MICROBE);

        self::$data['browsemicrobes']['folder'] = $catManager->loadCategoryPath($categoryId, CategoryType::MICROBE);

        self::$data['browsemicrobes']['categories'] = $catManager->loadChildrenCategories($categoryId, CategoryType::MICROBE);
        self::$data['browsemicrobes']['microbes'] = $catManager->loadMicrobes($categoryId);

        self::$views[] = 'browse-microbes';
        self::$cssFiles[] = 'categories';

        return 200;
    }
}

