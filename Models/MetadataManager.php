<?php

namespace Mikroatlas\Models;

use PDO;

class MetadataManager
{
    public function loadAllMetadata(int $id, bool $isObject = false): array
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
            WHERE '.($isObject ? 'micormd_object' : 'micormd_microorganism').'= ? AND mdkey_hidden = FALSE;
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
                $objectMetadata = $this->loadAllMetadata($objectId, true);
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
                    SELECT micormd_key FROM metadata_value WHERE micormd_microorganism = ?
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
        //TODO
    }
}