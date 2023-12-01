<?php

namespace Mikroatlas\Models;

use Mikroatlas\Models\DatabaseRecord;
use Mikroatlas\Models\Sanitizable;

class Microorganism implements DatabaseRecord, Sanitizable
{
    private int $id;
    public string $latinName;
    public string $czechName;
    public string $url;
    public int $category; //ID of the category

    public function __construct(array $dbData) {
        foreach ($dbData as $key => $value)
        {
            switch ($key) {
                case 'micor_id':
                    $this->id = $value;
                    break;
                case 'micor_latinname':
                    $this->name = $value;
                    break;
                case 'micor_czechname':
                    $this->url = $value;
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
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501007);
    }

    public function delete(): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501008);
    }

    public function sanitize(): void
    {
        // TODO: Implement sanitize() method.
    }
}