<?php

class CRM_RaisersEdgeMigration_FieldMapping {

  public static function contact() {
    $contactCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_contact_id',
      'return' => 'id',
    ]);
    return [
      'CONSTITUENT_ID' => 'custom_' . $contactCFIDs,
      'BIRTH_DATE' => 'birth_date',
      'DATE_ADDED' => 'created_date',
      'DATE_LAST_CHANGED' => 'modified_date',
      'DECEASED_DATE' => 'deceased_date',
      'FIRST_NAME' => 'first_name',
      'MIDDLE_NAME' => 'middle_name',
      'LAST_NAME' => 'last_name',
      'NICKNAME' => 'nick_name',
      'ORG_NAME' => 'organization_name',
      'SEX' => 'gender_id',
    ];
  }

  public static function address() {
    $addressCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_address_id',
      'return' => 'id',
    ]);
    $locationTypeCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_location_type',
      'return' => 'id',
    ]);
    return [
      'ADDRESS_ID' => 'custom_' . $addressCFID,
      'location_type' => 'custom_' . $locationTypeCFID,
      'IS_PRIMARY' => 'is_primary',
      'ADDRESS_BLOCK' => 'street_address',
      'CITY' => 'city',
      'STATE' => 'state_province_id',
      'POST_CODE' => 'postal_code',
      'country' => 'country_id',
    ];
  }

  public static function getLocationTypeOfPhoneEmailWebsite($locationType, $isPhone = FALSE) {
    switch ($locationType) {
      case 'Business Email':
      case 'Work Email':
      case 'Work E-mail':
        return ['location_type_id' => 'Work'];

      case 'Business Fax':
      case 'Work Fax':
        return ['location_type_id' => 'Work', 'phone_type_id' => 'Fax'];

      case 'Business - Direct Line':
        return ['location_type_id' => 'Work', 'phone_type_id' => 'Phone'];

      case 'Cellular':
        return ['location_type_id' => 'Home', 'phone_type_id' => 'Mobile'];

      case 'Home':
      case 'TTY':
        return ['location_type_id' => 'Home', 'phone_type_id' => 'Phone'];

      case 'Alternative Email':
        return ['location_type_id' => 'Other'];

      case 'Business Toll Free':
      case 'Business Phone':
        return ['location_type_id' => 'Work', 'phone_type_id' => 'Phone'];

      case 'Alternate number':
        return ['location_type_id' => 'Other', 'phone_type_id' => 'Phone'];

      case 'Home Alternate':
        return $isPhone ? ['location_type_id' => 'Other', 'phone_type_id' => 'Phone'] : ['location_type_id' => 'Other'];

      case 'Cellular 2':
        return ['location_type_id' => 'Other', 'phone_type_id' => 'Mobile'];

      case 'Home Fax':
      case 'Fax':
        return ['location_type_id' => 'Home', 'phone_type_id' => 'Fax'];

      default:
        return ['location_type_id' => 'Home'];
      }
  }

  public static function locationType() {
    return [
      'Home' => "Home",
      'Business' => "Work",
      'Previous address' => "Other",
      'Business Address' "Work",
      'Home Address' => "Home",
      'Previous Home Phone' => 'Home',
      'Previous contact information' => 'Home',
      'Previous Business Phone' => 'Work',
      'Previous Business Address' => 'Work',
      'Previous Home Address' => 'Home',
      'Secondary Address' => 'Other',
    ];
  }
}
