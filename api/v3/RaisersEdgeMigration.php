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

function civicrm_api3_raisers_edge_migration_createAddress($params) {
  $results = CRM_RaisersEdgeMigration_Util::createAddress($params);
}

function civicrm_api3_raisers_edge_migration_createPhone($params) {
  $results = CRM_RaisersEdgeMigration_Util::createPhone($params);
}

function civicrm_api3_raisers_edge_migration_createGroupContact($params) {
  $results = CRM_RaisersEdgeMigration_Util::createGroupContact($params);
}

function civicrm_api3_raisers_edge_migration_createSolicitCodes($params) {
  $results = CRM_RaisersEdgeMigration_Util::createSolicitCodes($params);
}

function civicrm_api3_raisers_edge_migration_createFt($params) {
  $results = CRM_RaisersEdgeMigration_Util::createFinancialTypes($params);
}

function civicrm_api3_raisers_edge_migration_createActivity($params) {
  $results = CRM_RaisersEdgeMigration_Util::createActivity($params);
}

function civicrm_api3_raisers_edge_migration_createRelationship($params) {
  $results = CRM_RaisersEdgeMigration_Util::createRelationship($params);
}

function civicrm_api3_raisers_edge_migration_createCampaign($params) {
  $results = CRM_RaisersEdgeMigration_Util::createCampaign($params);
}

function civicrm_api3_raisers_edge_migration_correctContribution($params) {
  $results = CRM_RaisersEdgeMigration_Util::correctContribution($params);
}

function civicrm_api3_raisers_edge_migration_createContribution($params) {
  $results = CRM_RaisersEdgeMigration_Util::createContribution($params);
}

function civicrm_api3_raisers_edge_migration_createPledges($params) {
  $results = CRM_RaisersEdgeMigration_Util::createPledges($params);
}

function civicrm_api3_raisers_edge_migration_createRecurringContribution($params) {
  $results = CRM_RaisersEdgeMigration_Util::createRecurringContribution($params);
}

function civicrm_api3_raisers_edge_migration_createContactNotes($params) {
  $results = CRM_RaisersEdgeMigration_Util::createContactNotes($params);
}

function civicrm_api3_raisers_edge_migration_createActivityNotes($params) {
  $results = CRM_RaisersEdgeMigration_Util::createActivityNotes($params);
}
