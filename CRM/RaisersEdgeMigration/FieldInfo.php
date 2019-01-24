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
    ];
    return $customGroups;
  }

  public static function getCustomFields($customGroupName) {
    $customGroups = [
      'RE_contact_details' => [
        're_contact_id' => [
          'label' => ts('RE Contact ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'Integer',
          'html_type' => 'Text',
          'name' => 're_contact_id',
        ],
      ],
      'RE_group_details' => [
        're_group_id' => [
          'label' => ts('RE Group ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'Integer',
          'html_type' => 'Text',
          'name' => 're_group_id',
        ],
      ],
      'RE_address_details' => [
        're_address_id' => [
          'label' => ts('RE Address ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'Integer',
          'html_type' => 'Text',
          'name' => 're_address_id',
        ],
        're_location_type' => [
          'label' => ts('RE Address ID', array('domain' => 'org.civicrm.raisersedgemigration')),
          'text_length' => 10,
          'data_type' => 'String',
          'html_type' => 'Text',
          'name' => 're_location_type',
        ],
      ],
    ];
    return CRM_Utils_Array::value($customGroupName, $customGroups, []);
  }

}
