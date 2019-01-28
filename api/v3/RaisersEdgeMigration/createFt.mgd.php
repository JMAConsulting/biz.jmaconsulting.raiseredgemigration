<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'Create RE Financial Types',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Create RE Financial Types',
      'description' => 'This scheduled job migrates financial types from RE to be saved in CiviCRM.',
      'run_frequency' => 'Daily',
      'api_entity' => 'RaisersEdgeMigration',
      'api_action' => 'createFt',
      'parameters' => '',
    ),
  ),
);
