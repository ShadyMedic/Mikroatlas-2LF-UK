<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Controllers\Controller;
use Mikroatlas\Models\MetadataManager;

class Metadata extends Controller
{

    /**
     * @inheritDoc
     */
    public function process(array $args = []): int
    {
        switch (array_shift($args)) {
            case 'missingKeys':
                $result = $this->loadMissingMetadataKeys($args);
                break;
            case 'valueStructure':
                $result = $this->loadValueStructure($args);
                break;
            default:
                throw new \InvalidArgumentException('Invalid action type.', 400002);
        }

        header('Content-type: application/json');
        self::$isApiRequest = true;
        echo json_encode($result);
        return 200;
    }

    private function loadMissingMetadataKeys($args) : array
    {
        $microbeId = array_shift($args);

        if (is_null($microbeId)) {
            throw new \InvalidArgumentException('No microbe ID provided.', 400001);
        }

        $metaManager = new MetadataManager();
        return $metaManager->loadMetadataKeys($microbeId);
    }

    private function loadValueStructure(array $args) : array
    {
        //TODO do one of the following:
        /*
            1. Datatype is primitive? Return [0 => [type => 'text/int/float/url/file', maxlength => 127 OR filetype => 'jpg/...']]
            2. Datatype is enum? Return [0 => [type => 'select', options => ['Positive', 'Negative']]]
            3. Datatype is object? Return [
                0 => [type => 'text/int/float/url/file', maxlength => 127 OR filetype => 'jpg/...'],
                1 => [type => 'select', options => ['Positive', 'Negative']],
                2 => [
                    0 => [type => 'text/int/float/url/file', maxlength => 127 OR filetype => 'jpg/...'],
                    1 => [type => 'select', options => ['Positive', 'Negative']],
                    //etc nested objects
                ]
                //etc more attributes
            ]
        */

        return [];
    }
}