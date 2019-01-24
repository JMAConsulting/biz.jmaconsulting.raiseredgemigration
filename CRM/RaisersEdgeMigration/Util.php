<?php

use CRM_RaisersEdgeMigration_FieldMapping as FieldMapping;
use CRM_RaisersEdgeMigration_FieldMapping as SQL;

class CRM_RaisersEdgeMigration_Util {

  public static function createContact() {
    $attributes = FieldMapping::contact();
    $sql = sprintf("SELECT %s FROM records ", implode(', ', array_keys($attributes)));
    $result = SQL::singleton()->query($sql);
    foreach ($result as $record) {
      $params = [];
      foreach ($attributes as $key => $columnName) {
        if ($columnName == 'id') {
          continue;
        }
        $params[$columnName] = $record[$key];
      }
      if (!empty($record['ORG_NAME'])) {
        $params['contact_type'] = 'Organization';
      }
      else {
        $params['contact_type'] = 'Individual';
      }
      $params = array_merge($params, self::getAddressParam($record['CONSTITUENT_ID']));
      $params = array_merge($params, self::getPhoneParam($record['CONSTITUENT_ID']));

      $contact = civicrm_api3('Contact', 'create', $params);

      if (!empty($contact['is_error'])) {
        $sql = sprintf(
          "INSERT INTO `re_error_data`(column_name, table_name, parameters, error_message) VALUES('CONSTITUENT_ID', 'records', '%s', '%s')",
          serialize($params),
          serialize($contact['error_message'])
        );
      }
    }
  }

  public static function getPhoneParam($constituentID) {
    $sql = "
    SELECT DISTINCT
    PHONES.CONSTIT_ID,
    NUM,
    DO_NOT_CALL,
    LONGDESCRIPTION AS location_type,
    PHONES.SEQUENCE,
    PHONES.PHONESID
    FROM PHONES
    LEFT JOIN TABLEENTRIES ON PHONETYPEID = TABLEENTRIESID
    LEFT JOIN RECORDS r ON r.ID = PHONES.CONSTIT_ID
    WHERE PHONES.INACTIVE = 0
    AND CONSTIT_RELATIONSHIPS_ID IS NULL AND PHONES.CONSTIT_ID = $constituentID
    ORDER BY PHONES.PHONESID, PHONES.SEQUENCE
    ";
    $result = SQL::singleton()->query($sql);

    $params = $emailParams = $phoneParams = $websiteParams = [];
    foreach ($result as $k => $record) {
      if (CRM_Utils_Rule::phone($record['NUM'])) {
        $phoneParams[] = array_merge(
          ['phone' => $record['NUM']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'], TRUE)
        );
      }
      elseif (CRM_Utils_Rule::email($record['NUM'])) {
        $emailParams[] = array_merge(
          ['email' => $record['NUM']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'])
        );
      }
      elseif ($record['location_type'] == 'Website') {
        $websiteParams[] = array_merge(
          ['website' => $record['NUM']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'])
        );
      }
    }

    foreach (['Email', 'Phone', 'Website'] as $type) {
      $records = ($type == 'Email') ? $emailParams : ($type == 'Phone') ? $phoneParams : $websiteParams;
      if (!empty($records)) {
        foreach ($records as $key => $record) {
          $attribute = sprintf('api.%s.create', $type);
          if ($key > 0) {
            $i = $k + 1;
            $params["$attribute.$i"] = array_merge($record, ['contact_id' => '\$value.id']);
          }
          else {
            $params[$attribute] = array_merge($params, ['contact_id' => '\$value.id', 'is_primary' => TRUE]);
          }
        }
      }
    }
  }

  public static function getAddressParam($constituentID) {
    $sql = "
    SELECT
    ADDRESS_ID,
    ca.CONSTIT_ID,
    LOC_TYPE.LONGDESCRIPTION as location_type,
    CTY.LONGDESCRIPTION as country,
    cr.IS_PRIMARY,
    ADDRESS_BLOCK,
    CITY,
    STATE,
    POST_CODE,
    PREFERRED,
    cr.RELATION_ID,
    ca.INDICATOR
    FROM ADDRESS a
    LEFT JOIN TABLEENTRIES AS CTY ON CTY.TABLEENTRIESID = COUNTRY
    JOIN CONSTIT_ADDRESS ca ON a.ID = ca.ADDRESS_ID
    LEFT JOIN TABLEENTRIES AS LOC_TYPE ON ca.TYPE = LOC_TYPE.TABLEENTRIESID
    LEFT JOIN RECORDS r ON ca.CONSTIT_ID = r.ID
    LEFT JOIN CONSTIT_RELATIONSHIPS cr ON ca.ID = cr.CONSTIT_ADDRESS_ID AND ca.CONSTIT_ID = cr.CONSTIT_ID
    WHERE INDICATOR <> 7 AND ADDRESS_BLOCK IS NOT NULL AND ca.CONSTIT_ID = $constituentID ";
    $result = SQL::singleton()->query($sql);

    $attributes = FieldMapping::address();
    $addressParams = [];
    foreach ($result as $k => $record) {
      foreach ($attributes as $key => $columnName) {
        if ($key == 'location_type') {
          $params['location_type_id'] = CRM_Utils_Array::value($record[$key], FieldMapping::locationType(), 'Home');
        }
        elseif ($key == 'STATE' && !empty($record[$key])) {
          $params['state_province_id'] = civicrm_api3('StateProvince', 'getvalue', [
            'abbreviation' => $record[$key],
            'options' => [
              'limit' => 1,
              'sort' => 'id ASC',
            ],
            'return' => 'id',
          );
          continue;
        }
        elseif ($key == 'country') {
          if (empty($record[$key])) {
            $params['country_id'] = civicrm_api3('StateProvince', 'getvalue',[
              'id' => $params['state_province_id'],
              'return' => 'country_id',
            ]);
          }
          else {
            $params['country_id'] = civicrm_api3('Country', 'getvalue',[
              'name' => $record[$key],
              'return' => 'id',
            ]);
          }
        }
        $params[$columnName] = $record[$key];
      }
      if ($k > 0) {
        $i = $k + 1;
        $addressParams['api.Address.create.' . $i] = array_merge($params, ['contact_id' => '\$value.id']);
      }
      else {
        $addressParams['api.Address.create'] = array_merge($params, ['contact_id' => '\$value.id', 'is_primary' => TRUE]);
      }
    }

    return $addressParams;
  }


}
