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
                $files = $this->processUploadedFiles($_FILES);
                if (gettype($files) === 'integer') { return $files; } //Upload error
                $formData = array_replace($_POST, $files); //There can't be duplicate keys anyway and unlike array_merge, this doesn't reset keys
                $result = $this->addValue($formData);
                if (!$result) { return 500; }
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

    private function addValue(array $formData) : bool
    {
        $microbeId = $formData['microbe-id'];
        unset($formData['microbe-id']);
        $microbe = new Microorganism(['micor_id' => $microbeId]);
        /*return*/ $microbe->addMetadataValue($formData); die();
    }

    private function processUploadedFiles(array $filesData) : false|array
    {
        $result = [];

        foreach ($filesData as $inputName => $fileInfo) {
            $mimeType = $fileInfo['type'];
            $fileSize = $fileInfo['size'];
            $tmpName = $fileInfo['tmp_name'];
            $fileError = $fileInfo['error'];

            if ($fileSize > 1.048576E7) {
                return 413; //Payload too large (10 MB application limit)
            }

            switch ($fileError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return 413; //Payload too large
                case UPLOAD_ERR_PARTIAL:
                    return 409; //Conflict
                case UPLOAD_ERR_NO_FILE:
                    return 400; //Bad request
                case UPLOAD_ERR_NO_TMP_DIR:
                case UPLOAD_ERR_CANT_WRITE:
                case UPLOAD_ERR_EXTENSION:
                    return 500; //Internal server error
            }
            $fileExtension = pathinfo($tmpName, PATHINFO_EXTENSION);
            $fileName = '/uploads/'.substr(base_convert(bin2hex(random_bytes(6)), 16, 36), 0, 8).$fileExtension;
            move_uploaded_file($tmpName, $_SERVER['DOCUMENT_ROOT'].$fileName);
            $result[$inputName] = $fileName;
        }
        return $result;
    }
}