<?php

class CRM_RaisersEdgeMigration_FieldMapping {

  public static function contact() {
    return [
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
}
