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
}

