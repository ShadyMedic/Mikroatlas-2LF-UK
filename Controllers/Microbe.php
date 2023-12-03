<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\MetadataManager;
use Mikroatlas\Models\Microorganism;
use Mikroatlas\Controllers\Controller;

class Microbe extends Controller
{

    /**
     * @inheritDoc
     * @param array $args Array of category URLs to enter
     */
    public function process(array $args = []): int
    {
        $microbe = new Microorganism(['micor_url' => array_shift($args)]);
        $microbe->loadIdFromUrl();
        $microbe->load($microbe->getId());

        self::$data['layout']['title'] = $microbe->name;
        self::$data['layout']['page_id'] = 'microbe';

        $metaManager = new MetadataManager();

        self::$data['microbe']['name'] = $microbe->name;
        // self::$data['microbe']['img'] = '$microbe_img';
        self::$data['microbe']['metadata'] = $metaManager->loadAllMetadata($microbe->getId());

        self::$views[] = 'microbe';
        // self::$cssFiles[] = 'microbe';

        return 200;
    }
}