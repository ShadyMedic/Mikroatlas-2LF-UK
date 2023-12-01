<?php

namespace Mikroatlas\Models;

use Mikroatlas\Models\DatabaseRecord;

class Category implements DatabaseRecord
{
    private int $id;
    public string $name;
    public string $url;
    public int $parent; //ID of the parent category
    public string $icon;

    public function __construct(array $dbData) {
        foreach ($dbData as $key => $value)
        {
            switch ($key) {
                case 'cat_id':
                    $this->id = $value;
                    break;
                case 'cat_name':
                    $this->name = $value;
                    break;
                case 'cat_url':
                    $this->url = $value;
                    break;
                case 'cat_parent':
                    $this->parent = $value;
                    break;
                case 'cat_icon':
                    $this->icon = $value;
                    break;
                default:
                    throw new \RuntimeException("Unknown field $key passed to Category::__construct().");
            }
        }
    }

    public function create(array $data): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501001);
    }

    public function update(array $data): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501002);
    }

    public function load(int $id): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501003);
    }

    public function delete(): bool
    {
        throw new \BadMethodCallException('Method '.__METHOD__.' in class '.self::class.' is not implemented.', 501004);
    }

    public function sanitize(): void
    {
        $this->name = htmlspecialchars($this->name, ENT_QUOTES);
        $this->url = htmlspecialchars($this->url, ENT_QUOTES);
        $this->icon = htmlspecialchars($this->icon, ENT_QUOTES);
    }

    public function getId(): ?int {
        return $this->id;
    }
}