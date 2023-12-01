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
        $statement = $db->prepare('SELECT micor_id, micor_latinname, micor_czechname FROM microorganism WHERE micor_category = ?');
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
        array_unshift($categoryUrlPath, '');
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

    public function translateCategories(array $categoriesUrls)
    {
        $db = Db::connect();
        $placeholders = rtrim(str_repeat('?,',count($categoriesUrls)), ',');
        $statement = $db->prepare('SELECT cat_name FROM category WHERE cat_url IN ('.$placeholders.')'); //Depends on parents always having lower IDs than children
        $result = $statement->execute($categoriesUrls);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}

