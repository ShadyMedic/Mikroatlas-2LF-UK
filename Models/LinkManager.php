<?php

namespace Mikroatlas\Models;

use PDO;

class LinkManager
{
    public function loadMicrobes(int $conditionId): array
    {
        $db = Db::connect();
        $statement = $db->prepare(
            'SELECT micor_latinname,micor_czechname,micor_url FROM microorganism WHERE micor_id IN (
                 SELECT miccon_microorganism FROM microorganism_condition WHERE miccon_condition = ?)'
        );
        $result = $statement->execute([$conditionId]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $microbes = [];

        foreach ($result as $microbeData) {
            $microbes[] = new Microorganism($microbeData);
        }

        return $microbes;
    }

    public function loadConditions(int $microbeId): array
    {
        $db = Db::connect();
        $statement = $db->prepare(
            'SELECT con_id,con_name FROM `condition` WHERE con_id IN (
                 SELECT miccon_condition FROM microorganism_condition WHERE miccon_microorganism = ?)'
        );
        $result = $statement->execute([$microbeId]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $categoryManager = new CategoryManager();
        $conditions = [];
        foreach ($result as $conditionData) {
            $path = $categoryManager->loadCategoryPath($conditionData['con_id'], CategoryType::CONDITION);
            $path = array_map(function($p) { return $p['con_url']; }, $path);
            array_shift($path); //Remove the INDEX element
            $conditions[] = new Condition(array_merge($conditionData, ['con_url' => implode('/', $path)]));
        }

        return $conditions;
    }
}

