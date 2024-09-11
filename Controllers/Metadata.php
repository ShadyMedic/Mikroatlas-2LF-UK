<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\MetadataManager;
use Mikroatlas\Models\Microorganism;

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
            case 'addValue':
                $formUrl = $_POST['form-url'];
                unset($_POST['form-url']);
                $result = $this->addValue($args);
                $this->redirect($formUrl);
            default:
                throw new \InvalidArgumentException('Invalid action type.', 400002);
        }

        $this->setJsonResponse(json_encode($result));
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
        $keyId = array_shift($args);

        if (is_null($keyId)) {
            throw new \InvalidArgumentException('No metadata key ID provided.', 400003);
        }

        $metaManager = new MetadataManager();
        return $metaManager->loadValueStructure($keyId);
    }

    private function addValue(array $args) : bool
    {
        $microbeId = $_POST['microbe-id'];
        unset($_POST['microbe-id']);
        $microbe = new Microorganism(['micor_id' => $microbeId]);
        $formData = array_replace($_POST, $_FILES); //There can't be duplicate keys anyway and unlike array_merge, this doesn't reset keys
        return $microbe->addMetadataValue($formData);
    }
}