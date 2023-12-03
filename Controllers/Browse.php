<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\CategoryManager;

/**
 * @see Controller
 */
class Browse extends Controller
{

    /**
     * @inheritDoc
     * @param array $args Array of category URLs to enter
     */
    public function process(array $args = []): int
    {
        $catManager = new CategoryManager();
        self::$data['layout']['title'] = 'Seznam mikroorganismů';
        self::$data['layout']['page_id'] = 'browse';

        $categoryId = $catManager->loadCategoryId($args);

        self::$data['browse']['folder'] = $catManager->loadCategoryPath($categoryId);

        self::$data['browse']['categories'] = $catManager->loadChildrenCategories($categoryId);
        self::$data['browse']['microbes'] = $catManager->loadMicrobes($categoryId);

        self::$views[] = 'browse';
        self::$cssFiles[] = 'browse';

        return 200;
    }
}

