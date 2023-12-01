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

        $categoryId = $catManager->loadCategoryId($args);
        if (!empty($args)) {
            self::$data['browse']['folder'] = implode('/', $catManager->translateCategories($args));
        }
        self::$data['browse']['categories'] = $catManager->loadChildrenCategories($categoryId);
        self::$data['browse']['microbes'] = $catManager->loadMicrobes($categoryId);

        self::$views[] = 'browse';

        return 200;
    }
}

