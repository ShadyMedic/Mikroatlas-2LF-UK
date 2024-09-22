<?php

namespace Mikroatlas\Models;

use Mikroatlas\Models\DatabaseRecord;
use Mikroatlas\Models\Sanitizable;

class Microorganism implements DatabaseRecord, Sanitizable
{
    private int $id;
    public string $latinName;
    public ?string $czechName;
    public string $url;
    public ?int $category; //ID of the category

    public function __construct(array $dbData) {
        foreach ($dbData as $key => $value)
        {
            switch ($key) {
                case 'micor_id':
                    $this->id = $value;
                    break;
                case 'micor_latinname':
                    $this->latinName = $value;
                    break;
                case 'micor_czechname':
                    $this->czechName = $value;
                    break;
                case 'micor_url':
                    $this->url = $value;
                    break;
                case 'micor_category':
                    $this->parent = $value;
                    break;
                default:
                    throw new \RuntimeException("Unknown field $key passed to Microorganism::__construct().");
            }
        }
    }

    public function create(array $data): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501005);
    }

    public function update(array $data): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501006);
    }

    public function load(int $id): bool
    {
        $db = Db::connect();
        $statement = $db->prepare('SELECT * FROM microorganism WHERE micor_id = ? LIMIT 1');
        $result = $statement->execute([$this->id]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }
        $this->__construct($statement->fetch(\PDO::FETCH_ASSOC));

        return $result;
    }

    public function delete(): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501008);
    }

    public function sanitize(): void
    {
        $this->latinName = htmlspecialchars($this->latinName, ENT_QUOTES);
        $this->czechName = htmlspecialchars($this->czechName, ENT_QUOTES);
        $this->url = htmlspecialchars($this->url, ENT_QUOTES);
    }

    public function getId()
    {
        return $this->id;
    }

    public function loadIdFromUrl(): void
    {
        $db = Db::connect();
        $statement = $db->prepare('SELECT micor_id FROM microorganism WHERE micor_url = ? LIMIT 1;');
        $result = $statement->execute([$this->url]);
        if ($result === false) {
            throw new \RuntimeException('Database query wasn\'t completed successfully');
        }

        $this->id = $statement->fetchColumn();
    }

    public function addMetadataValue(array $formData): bool
    {
        $mm = new MetadataManager();
        $key = array_key_first($formData);
        if (!str_contains($key, '-')) {
            //Non-object value, $formData contains just one element (and maybe its settings)
            return $mm->addMetadataRecord($this->id, MetadataOwner::MICROORGANISM, (int)$key, $formData);
        } else {
            //Object value, $formData contains elements for all of its attributes
            $objectKeyId = substr($key, 0, strpos($key, '-'));
            $formDataWithRootKeyIdStripped = [];
            foreach ($formData as $key => $value) {
                $formDataWithRootKeyIdStripped[substr($key, strlen($objectKeyId) + 1)] = $value;
            }
            $value = $this->buildObjectValue($formDataWithRootKeyIdStripped);
            return $mm->addMetadataRecord($this->id, MetadataOwner::MICROORGANISM, (int)$objectKeyId, [$objectKeyId => $value]);
        }
    }

    private function buildObjectValue($formData): array
    {
        $object = [];
        $attrKeyIds = array_keys($formData);
        for ($i = 0; $i < count($attrKeyIds); $i++) {
            $attrKeyId = $attrKeyIds[$i];
            $attrValue = $formData[$attrKeyId];

            if (str_contains($attrKeyId, '-')) {
                $innerObjectKeyId = substr($attrKeyId, 0, strpos($attrKeyId, '-'));
                $innerObjectAttributes = [];
                for ($j = $i; $j < count($formData); $j++) {
                    if (str_starts_with($attrKeyIds[$j], $innerObjectKeyId . '-')) {
                        $innerObjectAttributes[substr($attrKeyIds[$j], strlen($innerObjectKeyId) + 1)] = $formData[$attrKeyIds[$j]];
                        unset($attrKeyIds[$j]);
                    }
                }
                $attrKeyId = $innerObjectKeyId;
                $attrValue = $this->buildObjectValue($innerObjectAttributes);
            }
            $object[$attrKeyId] = $attrValue;
        }
        return $object;
    }
}

