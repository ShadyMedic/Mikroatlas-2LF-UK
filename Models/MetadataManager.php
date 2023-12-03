<?php

namespace Mikroatlas\Models;

class MetadataManager
{
    public function loadAllMetadata(int $microbeId): array
    {
        $db = Db::connect();
        $statement = $db->prepare('
            SELECT
                mdkey_name AS \'key\',
                COALESCE(mdtype_valuetable, mdenum_optiontable) AS \'valueTable\',
                micormd_valueid AS \'valueId\',
                COALESCE(mdtype_name, mdenum_name, mdobject_name) AS \'typeName\',
                mdkey_objectId AS \'objectId\'
            FROM metadata_value
            JOIN metadata_key ON metadata_value.micormd_key = metadata_key.mdkey_id
            LEFT JOIN metadata_type ON mdkey_typeid = mdtype_id
            LEFT JOIN metadata_enum ON mdkey_enumid = mdenum_id
            LEFT JOIN metadata_object ON mdkey_objectid = mdobject_id
            LEFT JOIN metadata_value_object ON mdkey_datatype = \'object\' AND micormd_valueid = mdvalobject_id
            WHERE micormd_microorganism = ? AND mdkey_hidden = FALSE;
        ');
        $statement->execute([$microbeId]);

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
                $query = 'SELECT * FROM '.$valueTable.' WHERE mdval'.$typeName.'_id = ? LIMIT 1';
                $statement = $db->prepare($query);
                $statement->execute([$valueId]);
                $value = $statement->fetch();
                $htmlValue = null;

                switch ($typeName) {
                    case 'image':
                        $htmlValue = '<img src="'.$value['mdvalimage_value'].'" />';
                        break;
                    case 'video':
                        //TODO
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

                $res['isObject'] = false;
                $res['value'] = $htmlValue;
            } else {
                $objectMetadata = $this->loadObjectMetadata($objectId);
                $res['isObject'] = true;
                $res['value'] = $objectMetadata;
            }

            $finalMetadata[] = $res;
        }

        return $finalMetadata;
    }

    private function loadObjectMetadata($objectId)
    {
        $db = Db::connect();
        $statement = $db->prepare('
            SELECT
                mdkey_name AS \'key\',
                COALESCE(mdtype_valuetable, mdenum_optiontable) AS \'valueTable\',
                micormd_valueid AS \'valueId\',
                COALESCE(mdtype_name, mdenum_name) AS \'typeName\'
            FROM metadata_value
            JOIN metadata_key ON metadata_value.micormd_key = metadata_key.mdkey_id
            LEFT JOIN metadata_type ON mdkey_typeid = mdtype_id
            LEFT JOIN metadata_enum ON mdkey_enumid = mdenum_id
            WHERE micormd_object = ? AND mdkey_hidden = FALSE;
        ');
        $statement->execute([$objectId]);

        $metadataList = $statement->fetchAll();
        $finalMetadata = [];

        foreach ($metadataList as $metadataEntry) {
            $key = $metadataEntry['key'];
            $valueTable = $metadataEntry['valueTable'];
            $valueId = $metadataEntry['valueId'];
            $typeName = $metadataEntry['typeName'];

            $query = 'SELECT * FROM '.$valueTable.' WHERE mdval'.$typeName.'_id = ? LIMIT 1';
            $statement = $db->prepare($query);
            $statement->execute([$valueId]);
            $value = $statement->fetch();
            $htmlValue = null;

            switch ($typeName) {
                case 'image':
                    $htmlValue = '<img src="'.$value['mdvalimage_value'].'" />';
                    break;
                case 'video':
                    //TODO
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

            $res = [];
            $res['key'] = $key;
            $res['value'] = $htmlValue;
            $finalMetadata[] = $res;
        }

        return $finalMetadata;
    }
}