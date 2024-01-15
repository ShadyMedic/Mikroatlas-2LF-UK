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
            $statement = $db->prepare('SELECT micorcat_id, micorcat_name, micorcat_url, micorcat_icon FROM microorganism_category WHERE micorcat_parent = ?');
            $parameters[] = $categoryId;
        } else {
            $statement = $db->prepare('SELECT micorcat_id, micorcat_name, micorcat_url, micorcat_icon FROM microorganism_category WHERE micorcat_parent IS NULL');
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
            $queryTemp = 'SELECT micorcat_id FROM microorganism_category WHERE micorcat_url = ? AND micorcat_parent ';
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
                SELECT micorcat_name, micorcat_url, micorcat_parent
                FROM microorganism_category
                WHERE micorcat_id = ?
                UNION ALL
                SELECT c.micorcat_name, c.micorcat_url, c.micorcat_parent
                FROM category_path cp
                JOIN microorganism_category c ON cp.micorcat_parent = c.micorcat_id
            )
            SELECT micorcat_name, micorcat_url FROM category_path;
        ');
        $result = $statement->execute([$currentCategoryId]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        return array_reverse($statement->fetchAll(PDO::FETCH_COLUMN));
    }
}

