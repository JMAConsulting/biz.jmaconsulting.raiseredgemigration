<?php

/**
 * This api exposes CiviCRM DonorPerfect records.
 *
 * @package CiviCRM_APIv3
 */

/**
 * Job to migrate DonorPerfect profile as contacts in CiviCRM
 *
 * @param array $params
 *
 * @return array
 *   API result array
 */
function civicrm_api3_raisers_edge_migration_createContact($params) {
  $results = CRM_RaisersEdgeMigration_Util::createContact($params);
}
