<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'create RE Group Contacts',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Create RE Contacts',
      'description' => 'This scheduled job migrates contacts from RE to be assigned respective regular groups in CiviCRM.',
      'run_frequency' => 'Daily',
      'api_entity' => 'RaisersEdgeMigration',
      'api_action' => 'createGroupContact',
      'parameters' => '',
    ),
  ),
);
