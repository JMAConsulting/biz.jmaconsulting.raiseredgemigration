<?php

class CRM_RaisersEdgeMigration_FieldMapping {

  public static function contact() {
    $contactCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_contact_id',
      'return' => 'id',
    ]);

    return [
      'CONSTITUENT_ID' => 'custom_' . $contactCFID,
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
      'Business Address' => "Work",
      'Home Address' => "Home",
      'Previous Home Phone' => 'Home',
      'Previous contact information' => 'Home',
      'Previous Business Phone' => 'Work',
      'Previous Business Address' => 'Work',
      'Previous Home Address' => 'Home',
      'Secondary Address' => 'Other',
    ];
  }

  public static function solicitCode() {
    return [
      'Do Not Mail' => "do_not_email",
      'Do Not Phone' => "do_not_phone",
      'Do Not Email' => "do_not_email",
      'Do not call' => "do_not_phone",
      'Do Not Trade' => 'do_not_trade',
    ];
  }

  public static function activityStatus() {
    return [
      'Completed' => 'Completed',
      'To be completed' => 'Scheduled',
      'Can not be completed' => 'Cancelled',
    ];
  }

  public static function activity() {
    $activityCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_activity_id',
      'return' => 'id',
    ]);
    return [
      'ID' => 'custom_' . $activityCFID,
      'ADDED_BY' => 'source_contact_id',
      'external_identifier' => 'target_contact_id',
      'action_contact_id' => 'assignee_contact_id',
      'type' => 'activity_type_id',
      'DTE' => 'activity_date_time',
      'Description' => 'description',
      'title' => 'subject',
      'status' => 'activity_status_id',
      'location' => 'location',
      'PRIORITY' => 'priority_id',
      'DateAdded' => 'created_date',
      'DateChanged' => 'modified_date',
      'phone_number' => 'phone_number',
    ];
  }

  public static function paymentType() {
    return [
      1 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Cash'),
      2 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check'),
      3 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check'),
      4 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Credit Card'),
      5 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Standing Order'),
      6 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Debit Card'),
      7 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Voucher'),
      8 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Other'),
    ];
  }

  public static function membershipType() {
    return [
      'Lifetime Member' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Lifetime Member'),
      'Individual' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Individual / Family'),
      'Professional' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Professional'),
      'Senior/ Student' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Senior/ Student'),
      'Complimentary' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Complimentary'),
      'Family' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Individual / Family'),
      'Friend' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Friend'),
      'Agency/ Group' => CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'membership_type_id', 'Agency'),
    ];
  }

  public static function softCreditType() {
    return [
      807 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionSoft', 'soft_credit_type_id', 'in_honor_of'),
      821 => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionSoft', 'soft_credit_type_id', 'in_memory_of'),
    ];
  }

  public static function campaign() {
    $campaignCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_campaign_id',
      'return' => 'id',
    ]);
    return [
      'ID' => 'custom_' . $campaignCFID,
      'CAMPAIGN_ID' => 'title',
      'DESCRIPTION' => 'description',
      'START_DATE' => 'start_date',
      'END_DATE' => 'end_date',
    ];
  }

  public static function financtypeToRevenueCode() {
    $financialTypes = [];
    $results = civicrm_api3('FinancialAccount', 'get', [
      'sequential' => 1,
      'return' => ["accounting_code"],
      'financial_account_type_id' => "Revenue",
      'accounting_code' => ['IS NOT NULL' => 1],
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($results as $result) {
      $record = civicrm_api3('EntityFinancialAccount', 'get', [
        'sequential' => 1,
        'entity_table' => 'civicrm_financial_type',
        'financial_account_id' => $result['id'],
      ])['values'][0];
      $financialTypes[$result['accounting_code']] = $record['entity_id'];
    }

    return $financialTypes;
  }

  public static function contribution() {
    $contributionCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_contribution_id',
      'return' => 'id',
    ]);
    return [
      'GiftSplitId' => 'custom_' . $contributionCFID,
      'appeal' => 'source',
      'DTE' => 'recieve_date',
    ];
  }

}
