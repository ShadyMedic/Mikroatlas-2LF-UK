<?php

namespace Mikroatlas\Models;

use http\Exception\BadMethodCallException;
use PDO;

class CategoryManager
{
    public function loadChildrenCategories(int $categoryId, CategoryType $categoryType)
    {
        $this->loadDbTableData($categoryType, $tableName, $columnPrefix);
        $db = Db::connect();
        $parameters = [];
        if (!is_null($categoryId)) {
            $statement = $db->prepare('SELECT '.$columnPrefix.'_id, '.$columnPrefix.'_name, '.$columnPrefix.'_url, '.$columnPrefix.'_icon FROM '.$tableName.' WHERE '.$columnPrefix.'_parent = ?');
            $parameters[] = $categoryId;
        } else {
            $statement = $db->prepare('SELECT '.$columnPrefix.'_id, '.$columnPrefix.'_name, '.$columnPrefix.'_url, '.$columnPrefix.'_icon FROM '.$tableName.' WHERE '.$columnPrefix.'_parent IS NULL');
        }

        $result = $statement->execute($parameters);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        foreach ($result as $categoryInfo) {
            if ($categoryType === CategoryType::MICROBE) {
                $categories[] = new MicrobeCategory($categoryInfo);
            } else if ($categoryType === CategoryType::CONDITION) {
                $categories[] = new Condition($categoryInfo);
            }
        }

        return $categories;
    }

    public function loadMicrobes(int $categoryId)
    {
        $db = Db::connect();
        $statement = $db->prepare('SELECT micor_id, micor_latinname, micor_czechname, micor_url FROM microorganism WHERE micor_category = ?');
        $result = $statement->execute([$categoryId]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $microbes = [];
        foreach ($result as $microbeInfo) {
            $microbes[] = new Microorganism($microbeInfo);
        }

        return $microbes;
    }

    public function loadCategoryId(array $categoryUrlPath, CategoryType $categoryType) {
        $this->loadDbTableData($categoryType, $tableName, $columnPrefix);
        array_unshift($categoryUrlPath, 'INDEX');
        $query = '';
        for ($i = 0; $i < count($categoryUrlPath); $i++) {
            $queryTemp = 'SELECT '.$columnPrefix.'_id FROM '.$tableName.' WHERE '.$columnPrefix.'_url = ? AND '.$columnPrefix.'_parent ';
            if ($i === 0) {
                $queryTemp .= 'IS NULL';
            } else {
                $queryTemp .= '= (';
            }
            $query = $queryTemp.$query.')';
        }
        $query = substr($query, 0, strlen($query) - 1);

        $db = Db::connect();
        $statement = $db->prepare($query);
        $result = $statement->execute(array_reverse($categoryUrlPath));
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        return $statement->fetchColumn();
    }

    public function loadCategoryPath(int $currentCategoryId, CategoryType $categoryType)
    {
        $this->loadDbTableData($categoryType, $tableName, $columnPrefix);
        $db = Db::connect();
        //SQL query by BingAI
        $statement = $db->prepare('
            WITH RECURSIVE category_path AS (
                SELECT '.$columnPrefix.'_name, '.$columnPrefix.'_url, '.$columnPrefix.'_parent
                FROM '.$tableName.'
                WHERE '.$columnPrefix.'_id = ?
                UNION ALL
                SELECT c.'.$columnPrefix.'_name, c.'.$columnPrefix.'_url, c.'.$columnPrefix.'_parent
                FROM category_path cp
                JOIN '.$tableName.' c ON cp.'.$columnPrefix.'_parent = c.'.$columnPrefix.'_id
            )
            SELECT '.$columnPrefix.'_name, '.$columnPrefix.'_url FROM category_path;
        ');
        $result = $statement->execute([$currentCategoryId]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        return array_reverse($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function loadDbTableData(CategoryType $categoryType, &$tableName, &$columnPrefix) {
        switch ($categoryType) {
            case CategoryType::MICROBE:
                $tableName = 'microorganism_category';
                $columnPrefix = 'micorcat';
                break;
            case CategoryType::CONDITION:
                $tableName = '`condition`'; //Escaped with ` because "condition" is a MySQL keyword
                $columnPrefix = 'con';
                break;
        }
    }
}

