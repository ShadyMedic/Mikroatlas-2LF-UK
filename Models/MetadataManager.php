<?php

namespace Mikroatlas\Models;

use http\Exception\BadMethodCallException;
use PDO;

class MetadataManager
{
    public function loadAllMetadata(int $id, MetadataOwner $metadataOwner): array
    {
        switch ($metadataOwner) {
            case MetadataOwner::MICROORGANISM:
                $filteringColumn = 'mdvalue_microorganism';
                break;
            case MetadataOwner::CONDITION:
                $filteringColumn = 'mdvalue_condition';
                break;
            case MetadataOwner::OBJECT:
                $filteringColumn = 'mdvalue_object';
                break;
        }
        $db = Db::connect();
        $statement = $db->prepare('
            SELECT
                mdkey_name AS \'key\',
                COALESCE(mdtype_valuetable, mdenum_optiontable) AS \'valueTable\',
                mdvalue_valueid AS \'valueId\',
                COALESCE(mdtype_name, mdenum_name, mdobject_name) AS \'typeName\',
                mdkey_objectId AS \'objectId\'
            FROM metadata_value
            JOIN metadata_key ON metadata_value.mdvalue_key = metadata_key.mdkey_id
            LEFT JOIN metadata_type ON mdkey_typeid = mdtype_id
            LEFT JOIN metadata_enum ON mdkey_enumid = mdenum_id
            LEFT JOIN metadata_object ON mdkey_objectid = mdobject_id
            LEFT JOIN metadata_value_object ON mdkey_datatype = \'object\' AND mdvalue_valueid = mdvalobject_id
            WHERE '.$filteringColumn.'= ? AND mdkey_hidden = FALSE;
        ');
        $statement->execute([$id]);

        $metadataList = $statement->fetchAll();
        $finalMetadata = [];

        foreach ($metadataList as $metadataEntry) {
            $key = $metadataEntry['key'];
            $valueTable = $metadataEntry['valueTable'];
            $valueId = $metadataEntry['valueId'];
            $typeName = $metadataEntry['typeName'];
            $objectId = $metadataEntry['objectId'];

            $res = [];
            $res['key'] = $key;

            if (empty($objectId)) {
                $res['isObject'] = false;
                $res['value'] = $this->getHtmlValue($valueTable, $typeName, $valueId);
            } else {
                $objectMetadata = $this->loadAllMetadata($valueId, MetadataOwner::OBJECT);
                $res['isObject'] = true;
                $res['value'] = $objectMetadata;
            }

            $finalMetadata[] = $res;
        }

        return $finalMetadata;
    }

    private function getHtmlValue(string $valueTable, string $typeName, $valueId)
    {
        $db = Db::connect();

        $query = 'SELECT * FROM '.$valueTable.' WHERE mdval'.$typeName.'_id = ? LIMIT 1';
        $statement = $db->prepare($query);
        $statement->execute([$valueId]);
        $value = $statement->fetch();
        $htmlValue = null;

        switch ($typeName) {
            case 'image':
                $htmlValue = '<img src="'.$value['mdvalimage_value'].'" 
                        width="'.($value['mdvalimage_width'] ?? 'auto').'"
                        height="'.($value['mdvalimage_height'] ?? 'auto').'"
                        '.($value['mdvalimage_allowinvert'] ? ' class="dark-mode-invert"' : '').'
                    />';
                break;
            case 'video':
                $htmlValue = '<video
                        width="'.($value['mdvalvideo_width'] ?? 'auto').'"
                        height="'.($value['mdvalvideo_height'] ?? 'auto').'"
                        '.($value['mdvalvideo_mute'] ? ' muted' : '')
                    .($value['mdvalvideo_controls'] ? ' controls' : '')
                    .($value['mdvalvideo_autoplay'] ? ' autoplay' : '').
                    '><source src="'.$value['mdvalvideo_value'].'" type="'.$value['mdvalvideo_type'].'"></video>';
                break;
            case 'link':
                $htmlValue = '<a href="'.$value['mdvallink_value'].'" target="'.$value['mdvallink_targetmode'].'" />Odkaz</a>';
                break;
            case 'text':
            case 'string':
            case 'float':
            case 'int':
                $htmlValue = $value['mdval'.$typeName.'_value'];
                break;
            default:
                //Enum
                $htmlValue = $value['mdval'.$typeName.'_value'];
        }

        return $htmlValue;
    }

    public function loadMetadataKeys(?int $avoidDuplicatesFromMicrobeId = null): array
    {
        if (is_null($avoidDuplicatesFromMicrobeId)) {
            $query = '
                SELECT mdkey_id, mdkey_name, mdkey_datatype, mdkey_islist, mdtype_displayname
                FROM metadata_key
                LEFT JOIN metadata_type ON metadata_type.mdtype_id = metadata_key.mdkey_typeid
                WHERE mdkey_disabled = FALSE AND mdkey_isattribute = FALSE
            ';
            $parameters = [];
        } else {
            $query = '
                SELECT mdkey_id, mdkey_name, mdkey_datatype, mdkey_islist, mdtype_displayname
                FROM metadata_key
                LEFT JOIN metadata_type ON metadata_type.mdtype_id = metadata_key.mdkey_typeid
                WHERE mdkey_disabled = FALSE AND mdkey_isattribute = FALSE AND mdkey_id NOT IN (
                    SELECT mdvalue_key FROM metadata_value WHERE mdvalue_microorganism = ?
                );
            ';
            $parameters = [$avoidDuplicatesFromMicrobeId];
        }

        $db = Db::connect();
        $statement = $db->prepare($query);
        $statement->execute($parameters);

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $final = [];

        foreach ($result as $metadataKey) {
            $final[] = [
                'id' => $metadataKey['mdkey_id'],
                'name' => $metadataKey['mdkey_name'],
                'datatype' => $metadataKey['mdkey_datatype'] === 'object' ? 'Složená hodnota' : (
                    $metadataKey['mdkey_datatype'] === 'enum' ? 'Výběr z možností' : $metadataKey['mdtype_displayname']),
                'list' => ($metadataKey['mdkey_islist'] == 1)
            ];
        }

        return $final;
    }

    public function loadValueStructure(int $metadataKeyId): array
    {
        $db = Db::connect();
        $statement = $db->prepare('
            SELECT
                mdkey_datatype AS "datatype",
                COALESCE(mdkey_typeid, mdkey_enumid, mdkey_objectid) AS datatypeId,
                mdkey_islist AS "isList",
                COALESCE(mdtype_name, mdenum_name, mdobject_name) AS "datatypeName",
                COALESCE(mdtype_valuetable, mdenum_optiontable) AS "valueTable"
            FROM metadata_key
            LEFT JOIN metadata_type ON metadata_key.mdkey_typeid = metadata_type.mdtype_id
            LEFT JOIN metadata_enum ON metadata_key.mdkey_enumid = metadata_enum.mdenum_id
            LEFT JOIN metadata_object ON metadata_key.mdkey_objectid = metadata_object.mdobject_id
            WHERE mdkey_id = ?;
        ');
        $statement->execute([$metadataKeyId]);

        $keyInfo = $statement->fetch();

        $result = [
            'keyId'=>$metadataKeyId,
            'multipleValues'=> (bool)$keyInfo['isList'],
            'type' => $keyInfo['datatype']
        ];

        switch ($keyInfo['datatype']) {
            case 'primitive':
                switch ($keyInfo['datatypeName']) {
                    case 'int':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'number';
                        $result['controls']['attributes']['step'] = '1';
                        $result['controls']['attributes']['min'] = '-2147483648';
                        $result['controls']['attributes']['max'] = '2147483647';
                        break;
                    case 'float':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'number';
                        $result['controls']['attributes']['step'] = 'any';
                        $result['controls']['attributes']['min'] = '-3.402823466E+38';
                        $result['controls']['attributes']['max'] = '3.402823466E+38';
                        break;
                    case 'string':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'text';
                        $result['controls']['attributes']['maxlength'] = '127';
                        break;
                    case 'text':
                        $result['controls']['tag'] = 'textarea';
                        $result['controls']['requiresClosing'] = true;
                        $result['controls']['attributes']['maxlength'] = '65535';
                        break;
                    case 'image':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'file';
                        $result['controls']['attributes']['accept'] = 'image/*';
                        $result['controls']['settings'][0]['tag'] = 'input';
                        $result['controls']['settings'][0]['requiresClosing'] = false;
                        $result['controls']['settings'][0]['attributes']['name'] = '{{{parent}}}:width';
                        $result['controls']['settings'][0]['attributes']['placeholder'] = 'Šířka obrázku';
                        $result['controls']['settings'][0]['attributes']['type'] = 'number';
                        $result['controls']['settings'][0]['attributes']['step'] = '1';
                        $result['controls']['settings'][0]['attributes']['min'] = '1';
                        $result['controls']['settings'][0]['attributes']['max'] = '5000';
                        $result['controls']['settings'][1]['tag'] = 'input';
                        $result['controls']['settings'][1]['requiresClosing'] = false;
                        $result['controls']['settings'][1]['attributes']['name'] = '{{{parent}}}:height';
                        $result['controls']['settings'][1]['attributes']['placeholder'] = 'Výška obrázku';
                        $result['controls']['settings'][1]['attributes']['type'] = 'number';
                        $result['controls']['settings'][1]['attributes']['step'] = '1';
                        $result['controls']['settings'][1]['attributes']['min'] = '1';
                        $result['controls']['settings'][1]['attributes']['max'] = '5000';
                        $result['controls']['settings'][2]['tag'] = 'span';
                        $result['controls']['settings'][2]['requiresClosing'] = true;
                        $result['controls']['settings'][2]['content'] = "Povolit inverzi";
                        $result['controls']['settings'][3]['tag'] = 'input';
                        $result['controls']['settings'][3]['requiresClosing'] = false;
                        $result['controls']['settings'][3]['attributes']['name'] = '{{{parent}}}:allowinvert';
                        $result['controls']['settings'][3]['attributes']['type'] = 'hidden';
                        $result['controls']['settings'][3]['attributes']['value'] = '0';
                        $result['controls']['settings'][4]['tag'] = 'input';
                        $result['controls']['settings'][4]['requiresClosing'] = false;
                        $result['controls']['settings'][4]['attributes']['name'] = '{{{parent}}}:allowinvert';
                        $result['controls']['settings'][4]['attributes']['type'] = 'checkbox';
                        $result['controls']['settings'][4]['attributes']['value'] = '1';
                        break;
                    case 'video':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'file';
                        $result['controls']['attributes']['accept'] = 'video/*';
                        $result['controls']['settings'][0]['tag'] = 'input';
                        $result['controls']['settings'][0]['requiresClosing'] = false;
                        $result['controls']['settings'][0]['attributes']['name'] = '{{{parent}}}:width';
                        $result['controls']['settings'][0]['attributes']['placeholder'] = 'Šírka videa';
                        $result['controls']['settings'][0]['attributes']['type'] = 'number';
                        $result['controls']['settings'][0]['attributes']['step'] = '1';
                        $result['controls']['settings'][0]['attributes']['min'] = '1';
                        $result['controls']['settings'][0]['attributes']['max'] = '5000';
                        $result['controls']['settings'][1]['tag'] = 'input';
                        $result['controls']['settings'][1]['requiresClosing'] = false;
                        $result['controls']['settings'][1]['attributes']['name'] = '{{{parent}}}:height';
                        $result['controls']['settings'][1]['attributes']['placeholder'] = 'Výška videa';
                        $result['controls']['settings'][1]['attributes']['type'] = 'number';
                        $result['controls']['settings'][1]['attributes']['step'] = '1';
                        $result['controls']['settings'][1]['attributes']['min'] = '1';
                        $result['controls']['settings'][1]['attributes']['max'] = '5000';
                        $result['controls']['settings'][2]['tag'] = 'span';
                        $result['controls']['settings'][2]['requiresClosing'] = true;
                        $result['controls']['settings'][2]['content'] = "Ztlumit video";
                        $result['controls']['settings'][3]['tag'] = 'input';
                        $result['controls']['settings'][3]['requiresClosing'] = false;
                        $result['controls']['settings'][3]['attributes']['name'] = '{{{parent}}}:mute';
                        $result['controls']['settings'][3]['attributes']['type'] = 'hidden';
                        $result['controls']['settings'][3]['attributes']['value'] = '0';
                        $result['controls']['settings'][4]['tag'] = 'input';
                        $result['controls']['settings'][4]['requiresClosing'] = false;
                        $result['controls']['settings'][4]['attributes']['name'] = '{{{parent}}}:mute';
                        $result['controls']['settings'][4]['attributes']['type'] = 'checkbox';
                        $result['controls']['settings'][4]['attributes']['value'] = '1';
                        $result['controls']['settings'][4]['attributes']['checked'] = 'true';
                        $result['controls']['settings'][5]['tag'] = 'span';
                        $result['controls']['settings'][5]['requiresClosing'] = true;
                        $result['controls']['settings'][5]['content'] = "Povolit ovládání videa";
                        $result['controls']['settings'][6]['tag'] = 'input';
                        $result['controls']['settings'][6]['requiresClosing'] = false;
                        $result['controls']['settings'][6]['attributes']['name'] = '{{{parent}}}:controls';
                        $result['controls']['settings'][6]['attributes']['type'] = 'hidden';
                        $result['controls']['settings'][6]['attributes']['value'] = '0';
                        $result['controls']['settings'][7]['tag'] = 'input';
                        $result['controls']['settings'][7]['requiresClosing'] = false;
                        $result['controls']['settings'][7]['attributes']['name'] = '{{{parent}}}:controls';
                        $result['controls']['settings'][7]['attributes']['type'] = 'checkbox';
                        $result['controls']['settings'][7]['attributes']['value'] = '1';
                        $result['controls']['settings'][7]['attributes']['checked'] = 'true';
                        $result['controls']['settings'][8]['tag'] = 'span';
                        $result['controls']['settings'][8]['requiresClosing'] = true;
                        $result['controls']['settings'][8]['content'] = "Automaticky spustit video";
                        $result['controls']['settings'][9]['tag'] = 'input';
                        $result['controls']['settings'][9]['requiresClosing'] = false;
                        $result['controls']['settings'][9]['attributes']['name'] = '{{{parent}}}:autoplay';
                        $result['controls']['settings'][9]['attributes']['type'] = 'hidden';
                        $result['controls']['settings'][9]['attributes']['value'] = '0';
                        $result['controls']['settings'][10]['tag'] = 'input';
                        $result['controls']['settings'][10]['requiresClosing'] = false;
                        $result['controls']['settings'][10]['attributes']['name'] = '{{{parent}}}:autoplay';
                        $result['controls']['settings'][10]['attributes']['type'] = 'checkbox';
                        $result['controls']['settings'][10]['attributes']['value'] = '1';
                        break;
                    case 'link':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'url';
                        $result['controls']['attributes']['maxlength'] = '512';
                        $result['controls']['settings'][0]['tag'] = 'select';
                        $result['controls']['settings'][0]['requiresClosing'] = true;
                        $result['controls']['settings'][0]['attributes']['name'] = '{{{parent}}}:targetmode';
                        $result['controls']['settings'][0]['options'] = [
                            '_blank' => 'Otevřít na nové záložce',
                            '_self' => 'Otevřít místo aktuální stránky'
                        ];
                        break;
                }
                break;
            case 'enum':
                $result['controls']['tag'] = 'select';
                $result['controls']['requiresClosing'] = true;
                $result['controls']['attributes'] = [];

                $statement = $db->prepare('SELECT * FROM '. $keyInfo['valueTable'].';');
                $statement->execute();
                $result['controls']['options'] = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
                break;
            case 'object':
                $result['controls']['tag'] = 'fieldset';
                $result['controls']['requiresClosing'] = true;
                $result['controls']['attributes'] = [];

                $statement = $db->prepare('
                    SELECT mdobjattr_mdkey_id FROM metadata_objectattributes WHERE mdobjattr_mdobj_id = ?;
                ');
                $statement->execute([$keyInfo['datatypeId']]);
                $attributesIds = $statement->fetchAll(PDO::FETCH_COLUMN);
                foreach ($attributesIds as $attributeId) {
                    $result['controls']['parts'][] = $this->loadValueStructure($attributeId);
                }
                break;
        }
        return $result;
    }

    public function addMetadataRecord(int $metadataOwnerId, MetadataOwner $metadataOwnerType, int $keyId, array $value): bool
    {
        print_r($keyId);
        print_r($value);
        echo "----\n";

        $db = Db::connect();
        //Load table name and prefix to save value into
        if (is_array($value[$keyId])) {
            $valueTable = 'metadata_value_object';
            $valueTablePrefix = 'mdvalobject_';
            $valueColumn = $valueTablePrefix.'comment';
            $datatype = 'object';
            $finalValue = null; //The comment column has use only when inserting objects manually
        } else {
            $statement = $db->prepare('
                SELECT
                    mdkey_datatype AS "datatype",
                    COALESCE(mdtype_valuetable, mdenum_optiontable) AS "valueTable",
                    COALESCE(mdtype_valuetableprefix, mdenum_optiontableprefix) AS "tablePrefix"
                FROM metadata_key
                LEFT JOIN metadata_type ON metadata_key.mdkey_typeid = metadata_type.mdtype_id
                LEFT JOIN metadata_enum ON metadata_key.mdkey_enumid = metadata_enum.mdenum_id
                WHERE mdkey_id = ?;
            ');
            $statement->execute([$keyId]);
            $data = $statement->fetch();
            $valueTable = $data['valueTable'];
            $valueTablePrefix = $data['tablePrefix'].'_';
            $valueColumn = $valueTablePrefix.'value';
            $datatype = $data['datatype'];
            $finalValue = $value[$keyId]; //Primitive data type or enum case ID
        }

        //Save the value
        if ($datatype !== 'enum') {
            $columnNames = [$valueColumn];
            $columnValues = [$finalValue];
            foreach ($value as $inputName => $inputValue) {
                if (str_starts_with($inputName, $keyId.':')) {
                    $columnNames[] = $valueTablePrefix.substr($inputName, strlen($keyId.':'));
                    $columnValues[] = $inputValue;
                }
            }
            $statement = $db->prepare('
                    INSERT INTO '.$valueTable.' ('.implode(',', $columnNames).') VALUES ('
                .implode(',', array_fill(0, count($columnValues), '?')).
                ');
            ');
            $statement->execute($columnValues);
            $valueId = $db->lastInsertId();
        } else {
            $valueId = $value[$keyId];
        }

        //Link the value
        $statement = $db->prepare('
                INSERT INTO metadata_value (
                    mdvalue_'.strtolower($metadataOwnerType->name).',
                    mdvalue_key,
                    mdvalue_valueid
                    )
                VALUES (?,?,?)
            ');
        $statement->execute([$metadataOwnerId, $keyId, $valueId]);
        if ($datatype === 'object') {
            echo "Saving object attributes:";
            print_r($value[$keyId]);
            //Save all object attributes
            foreach ($value[$keyId] as $attributeKeyId => $attributeValue) {
                if (!str_contains($attributeKeyId, ':')) {
                    $relatedValues = array_filter($value[$keyId], function($i) use ($attributeKeyId)
                        { return str_starts_with($i, $attributeKeyId.':'); }, ARRAY_FILTER_USE_KEY);
                    $relatedValues[$attributeKeyId] = $attributeValue;
                    foreach ($value as $aki => $av) {
                        if (str_starts_with($aki,$attributeKeyId.':')) {
                            $relatedValues[$aki] = $av;
                        }
                    }
                    echo "recursing with attributes:";
                    print_r([$valueId, MetadataOwner::OBJECT, $attributeKeyId, $relatedValues]);
                    $this->addMetadataRecord($valueId, MetadataOwner::OBJECT, $attributeKeyId, $relatedValues);
                }
            }
        }
        return true;
    }
}