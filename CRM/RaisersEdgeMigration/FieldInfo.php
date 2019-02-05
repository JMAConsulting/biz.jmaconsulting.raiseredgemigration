<?php

class CRM_RaisersEdgeMigration_FieldInfo {

  public static function getCustomGroups() {
    $customGroups = [
      'RE_contact_details' => [
        'title' => ts('RE contact details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_contact_details',
        'extends' => 'Contact',
      ],
      'RE_group_details' => [
        'title' => ts('RE group details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_group_details',
        'extends' => 'Group',
      ],
      'RE_address_details' => [
        'title' => ts('RE address details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_address_details',
        'extends' => 'Address',
      ],
      'RE_activity_details' => [
        'title' => ts('RE activity details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_activity_details',
        'extends' => 'Activity',
      ],
      'RE_relationship_details' => [
        'title' => ts('RE relationship details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_relationship_details',
        'extends' => 'Relationship',
      ],
      'RE_contribution_details' => [
        'title' => ts('RE contribution details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_contribution_details',
        'extends' => 'Contribution',
      ],
      'RE_pledge_details' => [
        'title' => ts('RE pledge details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_pledge_details',
        'extends' => 'Pledge',
      ],
      'RE_campaign_details' => [
        'title' => ts('RE campaign details', array('domain' => 'org.civicrm.raisersedgemigration')),
        'name' => 'RE_campaign_details',
        'extends' => 'Campaign',
      ],
    ];
    return $customGroups;
  }

  public static function getCustomFields($customGroupName) {
    $customGroups = [
      'RE_contact_details' => [
        're_contact_id' => [
          'label' => ts('RE Contact ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_contact_id',
        ],
      ],
      'RE_group_details' => [
        're_group_id' => [
          'label' => ts('RE Group ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_group_id',
        ],
      ],
      'RE_address_details' => [
        're_address_id' => [
          'label' => ts('RE Address ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_address_id',
        ],
        're_location_type' => [
          'label' => ts('RE Location Type', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_location_type',
        ],
      ],
      'RE_activity_details' => [
        're_activity_id' => [
          'label' => ts('RE Activity ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_activity_id',
        ],
      ],
      'RE_relationship_details' => [
        're_relationship_id' => [
          'label' => ts('RE Relationship ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 64,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_relationship_id',
        ],
        're_AB_relationship' => [
          'label' => ts('RE A to B Relationship', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 64,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_AB_relationship',
        ],
        're_BA_relationship' => [
          'label' => ts('RE B to A Relationship', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 64,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_BA_relationship',
        ],
      ],
      'RE_contribution_details' => [
        're_contribution_id' => [
          'label' => ts('RE Contribution ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 20,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_contribution_id',
        ],
      ],
      'RE_pledge_details' => [
        're_pledge_id' => [
          'label' => ts('RE Pledge ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_pledge_id',
        ],
        're_pledge_frequency' => [
          'label' => ts('RE Pledge Frequency', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 20,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_pledge_frequency',
        ],
      ],
      'RE_campaign_details' => [
        're_campaign_id' => [
          'label' => ts('RE Campaign ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 20,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_campaign_id',
        ],
      ],
    ];
    return CRM_Utils_Array::value($customGroupName, $customGroups, []);
  }

  public static function getCustomTableName($CGName) {
    return civicrm_api3('CustomGroup', 'getvalue', [
      'name' => $CGName,
      'return' => 'table_name',
    ]);
  }

  public static function getCustomFieldColumnName($CFName) {
    return civicrm_api3('CustomField', 'getvalue', [
      'name' => $CFName,
      'return' => 'column_name',
    ]);
  }

  public static function createREPriceSet() {
    $priceSetParams = [
      'title' => 'RE Price Set',
      'extends' => "CiviContribute",
      'is_quick_config' => 1,
      'financial_type_id' => CRM_Core_Pseudoconstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
    ];
    $result = civicrm_api3('PriceSet', 'get', ['title' => 'RE Price Set']);
    if (!empty($result['id'])) {
      return self::getREPriceSet($result['id']);
    }
    $priceSetID = civicrm_api3('PriceSet', 'create', $priceSetParams)['id'];
    $params = [
      'price_set_id' => $priceSetID,
      'price_field_id' => [],
    ];
    for ($i = 1; $i <= 7; $i++) {
      $priceFieldID = civicrm_api3('PriceField', 'create', [
        'html_type' => "Text",
        'label' => 'Contribution amount ' . $i,
        'price_set_id' => $priceSetID,
      ])['id'];
      $priceFieldValueID = civicrm_api3('PriceFieldValue', 'create', [
        'price_field_id' => $priceFieldID,
        'label' => 'Contribution amount ' . $i,
        'amount' => 1.00,
        'financial_type_id' => CRM_Core_Pseudoconstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
      ])['id'];
      $params['price_field_id'][$priceFieldID] = $priceFieldValueID;
    }
  }

  public static function getREPriceSet($priceSetID) {
      $params = [
        'price_set_id' => $priceSetID,
        'price_field_id' => [],
      ];
      $results = civicrm_api3('PriceField', 'get', ['price_set_id' => $priceSetID])['values'];
      foreach ($results as $result) {
        $params['price_field_id'][$result['id']] = civicrm_api3('PriceFieldValue', 'get', ['price_field_id' => $result['id']])['id'];
      }
    return $params;
  }

}
