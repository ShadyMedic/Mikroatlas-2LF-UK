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
                $result['valueTable'] = $keyInfo['valueTable'];
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
                        break;
                    case 'video':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'file';
                        $result['controls']['attributes']['accept'] = 'video/*';
                        break;
                    case 'link':
                        $result['controls']['tag'] = 'input';
                        $result['controls']['requiresClosing'] = false;
                        $result['controls']['attributes']['type'] = 'url';
                        $result['controls']['attributes']['maxlength'] = '512';
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
                $result['valueTable'] = 'metadata_value_object';
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

    public function addMetadataRecord(int $metadataOwnerId, MetadataOwner $metadataOwnerType, int $keyId, $value): bool
    {
        $db = Db::connect();
        //Load table name and prefix to save value into
        if (is_array($value)) {
            $valueTable = 'metadata_value_object';
            $valueColumn = 'mdvalobject_comment';
            $datatype = 'object';
            $finalValue = '...'; //The comment column has use only when inserting objects manually
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
            $valueColumn = $data['tablePrefix'].'_value';
            $datatype = $data['datatype'];
            $finalValue = $value; //Primitive data type or enum case ID
        }

        //Save the value
        if ($datatype !== 'enum') {
            $statement = $db->prepare('
                    INSERT INTO '.$valueTable.' ('.$valueColumn.') VALUES (?);
                ');
            $statement->execute([$finalValue]);
            $valueId = $db->lastInsertId();
        } else {
            $valueId = $value;
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
            //Save all object attributes
            foreach ($value as $attributeKeyId => $attributeValue) {
                $this->addMetadataRecord($valueId, MetadataOwner::OBJECT, $attributeKeyId, $attributeValue);
            }
        }

        return true;
    }
}