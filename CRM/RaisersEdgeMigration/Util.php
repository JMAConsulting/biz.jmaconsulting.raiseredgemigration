<?php

class CRM_RaisersEdgeMigration_Util {

  public static function createContact() {
    $attributes = CRM_RaiserEdgeMigration_FieldMapping::contact();
    $sql = sprintf("SELECT %s FROM records ", implode(', ', array_keys($attributes)));
    $result = CRM_RaiserEdgeMigration_SQL::singleton()->query($sql);
    foreach ($result as $record) {
      $params = [];
      foreach ($attributes as $key => $columnName) {
        $params[$columnName] = $record[$key];
      }
      if (!empty($record['ORG_NAME'])) {
        $params['contact_type'] = 'Organization';
      }
      else {
        $params['contact_type'] = 'Individual';
      }
      // TODO : create address, phone, salutation and groupContact
      civicrm_api3('Contact', 'create', $params);
    }
  }

}
