<?php

namespace Mikroatlas\Models;

use PDO;

class CategoryManager
{
    public function loadChildrenCategories(int $categoryId)
    {
        $db = Db::connect();
        $parameters = [];
        if (!is_null($categoryId)) {
            $statement = $db->prepare('SELECT cat_id, cat_name, cat_url, cat_icon FROM category WHERE cat_parent = ?');
            $parameters[] = $categoryId;
        } else {
            $statement = $db->prepare('SELECT cat_id, cat_name, cat_url, cat_icon FROM category WHERE cat_parent IS NULL');
        }

        $result = $statement->execute($parameters);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        foreach ($result as $categoryInfo) {
            $categories[] = new Category($categoryInfo);
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

    public function loadCategoryId(array $categoryUrlPath) {
        array_unshift($categoryUrlPath, 'browse');
        $query = '';
        for ($i = 0; $i < count($categoryUrlPath); $i++) {
            $queryTemp = 'SELECT cat_id FROM category WHERE cat_url = ? AND cat_parent ';
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

    public function loadCategoryPath(int $currentCategoryId)
    {
        $db = Db::connect();
        //SQL query by BingAI
        $statement = $db->prepare('
            WITH RECURSIVE category_path AS (
                SELECT cat_name, cat_url, cat_parent
                FROM category
                WHERE cat_id = ?
                UNION ALL
                SELECT c.cat_name, c.cat_url, c.cat_parent
                FROM category_path cp
                JOIN category c ON cp.cat_parent = c.cat_id
            )
            SELECT cat_name, cat_url FROM category_path;
        ');
        $result = $statement->execute([$currentCategoryId]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        return array_reverse($statement->fetchAll(PDO::FETCH_COLUMN));
    }
}

