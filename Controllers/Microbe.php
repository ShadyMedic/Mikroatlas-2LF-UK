<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\LinkManager;
use Mikroatlas\Models\MetadataManager;
use Mikroatlas\Models\MetadataOwner;
use Mikroatlas\Models\Microorganism;

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

        self::$data['layout']['title'] = $microbe->latinName;
        self::$data['layout']['page_id'] = 'microbe';

        $metaManager = new MetadataManager();
        $linkMananger = new LinkManager();

        self::$data['microbe']['id'] = $microbe->getId();
        self::$data['microbe']['name'] = $microbe->latinName;
        // self::$data['microbe']['img'] = '$microbe_img';
        self::$data['microbe']['metadata'] = $metaManager->loadAllMetadata($microbe->getId(), MetadataOwner::MICROBE);
        self::$data['microbe']['conditions'] = $linkMananger->loadConditions($microbe->getId());

        self::$views[] = 'microbe';
        self::$jsFiles[] = 'microbe';

        return 200;
    }
}