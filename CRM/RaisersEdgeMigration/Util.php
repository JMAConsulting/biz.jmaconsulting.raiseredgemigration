<?php

use CRM_RaisersEdgeMigration_FieldMapping as FieldMapping;
use CRM_RaisersEdgeMigration_FieldInfo as FieldInfo;
use CRM_RaisersEdgeMigration_SQL as SQL;

class CRM_RaisersEdgeMigration_Util {

  public static function createContact() {
    $offset = 0;
    $limit = 1000;
    $totalCount = 110000;
    $attributes = FieldMapping::contact();
    while ($limit <= $totalCount) {
      $sql = sprintf("SELECT %s
        FROM records
        WHERE CONSTITUENT_ID IS NOT NULL
       LIMIT $offset, $limit ", implode(', ', array_keys($attributes)));

      $result = SQL::singleton()->query($sql);
      foreach ($result as $record) {
        $params = [];
        if ($id = CRM_Core_DAO::singleValueQuery('SELECT entity_id FROM civicrm_value_re_contact_de_35 where re_contact_id_736 = \'' . $record['CONSTITUENT_ID'] . '\' LIMIT 1')) {
          continue;
        }
        if (!CRM_Core_DAO::singleValueQuery('SELECT constituent_id FROM missing_re_contact WHERE constituent_id = \'' . $record['CONSTITUENT_ID'] . '\'')) {
          continue;
        }
        foreach ($attributes as $key => $columnName) {
          if ($columnName != 'id') {
            $params[$columnName] = $record[$key];
          }
        }
        $rule = NULL;
        if (!empty($record['ORG_NAME'])) {
          $params['contact_type'] = 'Organization';
        }
        else {
          $params['contact_type'] = 'Individual';
          $rule = 'RE_Individual_Rule_9';
        }
        $params = array_merge($params, self::getAddressParam($record['CONSTITUENT_ID']));

        $params['id'] = self::checkDuplicate($params, $rule);

        try {
          $contact = civicrm_api3('Contact', 'create', $params);
          self::createPhoneParam($record['CONSTITUENT_ID'], $contact['id']);
          CRM_Core_DAO::executeQuery("DELETE FROM missing_re_contact WHERE constituent_id = '" . $record['CONSTITUENT_ID'] . "'");
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['CONSTITUENT_ID'], 'records', [], $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function checkDuplicate($contactParams = array(), $rule = NULL) {
    $cid = NULL;
    if (!empty($contactParams)) {
      // Check with first, last and email for a duplicate.
      if (CRM_Utils_Array::value('organization_name', $contactParams)) {
        $type = "Organization";
        $params = array(
          'organization_name' => $contactParams['organization_name'],
          'contact_type' => $type,
        );
      }
      else {
        $type = "Individual";
        $params = array(
          'first_name' => $contactParams['first_name'],
          'last_name' => $contactParams['last_name'],
          'contact_type' => $type,
        );
      }
      $dedupeParams = CRM_Dedupe_Finder::formatParams($params, $type);
      $dedupeParams['check_permission'] = FALSE;
      if ($type == 'Individual') {
        $rule = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_dedupe_rule_group WHERE name = '{$rule}'");
      }
      $dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, $type);
      $cid = CRM_Utils_Array::value('0', $dupes, NULL);
    }

    return $cid;
  }

  public static function createPhoneParam($constituentID, $contactID) {
    $sql = "
    SELECT DISTINCT
    phones.CONSTIT_ID,
    NUM,
    DO_NOT_CALL,
    LONGDESCRIPTION AS location_type,
    phones.SEQUENCE,
    phones.PHONESID,
    phones.INACTIVE
    FROM phones
    LEFT JOIN tableentries ON PHONETYPEID = TABLEENTRIESID
    LEFT JOIN records r ON r.ID = phones.CONSTIT_ID
    WHERE CONSTIT_RELATIONSHIPS_ID IS NULL AND phones.CONSTIT_ID = '$constituentID'
    ORDER BY phones.PHONESID, phones.SEQUENCE
    ";
    $result = SQL::singleton()->query($sql);

    $params = $emailParams = $phoneParams = $websiteParams = [];
    foreach ($result as $k => $record) {
      if (CRM_Utils_Rule::phone($record['NUM'])) {
        $phoneParams[] = array_merge(
          ['phone' => $record['NUM'], 'entity_id' => $record['PHONESID']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'], TRUE)
        );
      }
      elseif (strstr($record['NUM'], '@')) {
        $emailParams[] = array_merge(
          [
            'email' => $record['NUM'],
            'entity_id' => $record['PHONESID'],
            'on_hold' => ($record['INACTIVE'] == 0) ? 0 : 1,
          ],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'])
        );
      }
      elseif ($record['location_type'] == 'Website') {
        $websiteParams[] = array_merge(
          ['url' => $record['NUM'], 'entity_id' => $record['PHONESID']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'])
        );
      }
    }

    foreach (['Email', 'Phone', 'Website'] as $type) {
      $records = ($type == 'Email') ? $emailParams : ($type == 'Phone') ? $phoneParams : $websiteParams;
      if (!empty($records)) {
        foreach ($records as $key => $record) {
          $params = array_merge([
            'contact_id' => $contactID,
            'is_primary' => ($key == 0),
          ], $record);
          try {
            civicrm_api3($type, 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['entity_id'], 'PHONES', $params, $e->getMessage());
          }
        }
      }
    }

    return $params;
  }

  public static function recordError($entityID, $entityTable, $params, $errorMessage) {
    $sql = sprintf(
      "INSERT INTO `re_error_data`(column_name, table_name, parameters, error_message) VALUES('%s', '%s', '%s', '%s')",
      $entityID,
      $entityTable,
      serialize($params),
      serialize($errorMessage)
    );
    CRM_Core_DAO::executeQuery($sql);
  }

  public static function getAddressParam($constituentID) {
    $sql = "
    SELECT
    ca.ADDRESS_ID,
    ca.CONSTIT_ID,
    LOC_TYPE.LONGDESCRIPTION as location_type,
    CTY.LONGDESCRIPTION as country,
    ADDRESS_BLOCK,
    CITY,
    STATE,
    POST_CODE,
    ca.PREFERRED,
    ca.INDICATOR
    FROM address a
    LEFT JOIN tableentries AS CTY ON CTY.TABLEENTRIESID = COUNTRY
    JOIN constit_address ca ON a.ID = ca.ADDRESS_ID
    LEFT JOIN tableentries AS LOC_TYPE ON ca.TYPE = LOC_TYPE.TABLEENTRIESID
    LEFT JOIN records r ON ca.CONSTIT_ID = r.ID
    LEFT JOIN constit_address cr ON ca.ID = cr.ADDRESS_ID AND ca.CONSTIT_ID = cr.CONSTIT_ID
    WHERE ca.INDICATOR <> 7 AND ADDRESS_BLOCK IS NOT NULL AND ca.CONSTIT_ID = '$constituentID' ";
    $result = SQL::singleton()->query($sql);

    $attributes = FieldMapping::address();
    $addressParams = [];
    foreach ($result as $k => $record) {
      foreach ($attributes as $key => $columnName) {
        if ($key == 'location_type') {
          $params['location_type_id'] = CRM_Utils_Array::value($record[$key], FieldMapping::locationType(), 'Home');
        }
        elseif ($key == 'STATE' && !empty($record[$key])) {
          try {
            $params['state_province_id'] = civicrm_api3('StateProvince', 'getvalue', [
              'abbreviation' => $record[$key],
              'options' => [
                'limit' => 1,
                'sort' => 'id ASC',
              ],
              'return' => 'id',
            ]);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['ADDRESS_ID'], 'phones', $params, $e->getMessage());
          }
          continue;
        }
        elseif ($key == 'country') {
          if (empty($record[$key])) {
            try {
              $params['country_id'] = civicrm_api3('StateProvince', 'getvalue',[
                'id' => $params['state_province_id'],
                'options' => [
                  'limit' => 1,
                  'sort' => 'id ASC',
                ],
                'return' => 'country_id',
              ]);
            }
            catch (CiviCRM_API3_Exception $e) {}
          }
          else {
            try {
              $params['country_id'] = civicrm_api3('Country', 'getvalue',[
                'name' => $record[$key],
                'options' => [
                  'limit' => 1,
                  'sort' => 'id ASC',
                ],
                'return' => 'id',
              ]);
            }
            catch (CiviCRM_API3_Exception $e) {}
          }
          continue;
        }
        $params[$columnName] = $record[$key];
      }
      if ($k > 0) {
        $i = $k + 1;
        $addressParams['api.Address.create.' . $i] = $params;
      }
      else {
        $addressParams['api.Address.create'] = ['is_primary' => TRUE] + $params;
      }
    }

    return $addressParams;
  }

  public static function createMissingEmail() {
    $offset = 0;
    $limit = 1000;
    $totalCount = 30000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT DISTINCT
      phones.CONSTIT_ID,
      NUM,
      DO_NOT_CALL,
      LONGDESCRIPTION AS location_type,
      phones.SEQUENCE,
      phones.PHONESID,
      phones.INACTIVE
      FROM phones
      LEFT JOIN tableentries ON PHONETYPEID = TABLEENTRIESID
      LEFT JOIN records r ON r.ID = phones.CONSTIT_ID
      WHERE CONSTIT_RELATIONSHIPS_ID IS NULL AND phones.NUM LIKE '%@%'
      ORDER BY phones.PHONESID, phones.SEQUENCE
      LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        if ($contactID = CRM_Core_DAO::singleValueQuery("SELECT entity_id FROM civicrm_value_re_contact_de_35 WHERE re_contact_id_736 = '" . $record['CONSTIT_ID'] . "' LIMIT 1 ")) {
          $params = [
            'email' => $record['NUM'],
            'on_hold' => ($record['INACTIVE'] == 0) ?: 1,
            'contact_id' => $contactID,
          ] + FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type']);
          try {
            civicrm_api3('Email', 'create', $params);
            CRM_Core_DAO::executeQuery("DELETE FROM re_error_data WHERE column_name = '" . $record['PHONESID'] . "' AND table_name = 'PHONES'");
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['PHONESID'], 'PHONES', $params, $e->getMessage());
          }
        }

      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function createGroupContact() {
    $tableName = FieldInfo::getCustomTableName('RE_group_details');
    $columName = FieldInfo::getCustomFieldColumnName('re_group_id');
    $groupCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_group_id',
      'return' => 'id',
    ]);

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $offset = 0;
    $limit = 1000;
    $totalCount = 70000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT DISTINCT te.LONGDESCRIPTION as group, cc.*
       FROM `constituent_codes` cc
        INNER JOIN records r ON r.CONSTITUENT_ID = cc.CONSTIT_ID
        LEFT JOIN tableentries te ON te.TABLEENTRIESID = cc.CODE
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $groupID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $tableName, $columName, $record['CODE']));
        if (empty($groupID)) {
          try {
            $params = [
              'title' => $record['group'],
              'custom_' . $groupCustomFieldID => $record['CODE'],
            ];
            $groupID = civicrm_api3('Group', 'create', $params)['id'];
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['CODE'], 'GROUPS', $params, $e->getMessage());
          }
        }
        $contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['CONSTIT_ID']));

        if (empty($contactID)) {
          self::recordError($record['CONSTIT_ID'], 'records', [], 'No contact found');
        }
        try {
          $params = [
            'contact_id' => $contactID,
            'group_id' => $groupID,
            "status" => "Added",
          ];
          civicrm_api3('GroupContact', 'create', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['ID'], 'constituent_codes', $params, $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function createSolicitCodes() {
    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');
    $attributes = FieldMapping::contact();

    $offset = 0;
    $limit = 1000;
    $totalCount = 10000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT ID, RECORDSID AS external_identifier, LONGDESCRIPTION as solicit_code
      FROM CONSTITUENT_SOLICITCODES JOIN TABLEENTRIES ON SOLICIT_CODE = TABLEENTRIES.TABLEENTRIESID
      WHERE TABLEENTRIES.ACTIVE = -1
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['external_identifier']));
        if (!empty($contactID)) {
          $params = [
            'contact_id' => $contactID,
          ];
          if ($record['solicit_code'] == 'Do not contact') {
            $params += [
              'do_not_email' => 1,
              'do_not_phone' => 1,
              'do_not_sms' => 1,
              'do_not_trade' => 1,
            ];
          }
          elseif (array_key_exists($record['solicit_code'], $attributes)) {
            $params[$attributes[$record['solicit_code']]] = 1;
          }
          else {
            continue;
          }
          try {
            civicrm_api3('Contact', 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['ID'], 'CONSTITUENT_SOLICITCODES', $params, $e->getMessage());
          }
        }
      }

      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

}
