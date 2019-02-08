<?php

require __DIR__ . '/../../vendor/autoload.php';

use CRM_RaisersEdgeMigration_FieldMapping as FieldMapping;
use CRM_RaisersEdgeMigration_FieldInfo as FieldInfo;
use CRM_RaisersEdgeMigration_SQL as SQL;

class CRM_RaisersEdgeMigration_Util {

  public static function createContact($apiParams) {
    $offset = 0;
    $limit = 1000;
    $totalCount = 110000;
    $attributes = FieldMapping::contact();
    while ($limit <= $totalCount) {
      $sql = sprintf("SELECT %s
        FROM records
        WHERE CONSTITUENT_ID IS NOT NULL
       LIMIT $offset, $limit ", implode(', ', array_keys($attributes)));

      $result = SQL::singleton()->query($sql);
      foreach ($result as $record) {
        $params = [];
        if ($id = CRM_Core_DAO::singleValueQuery('SELECT entity_id FROM civicrm_value_re_contact_de_35 where re_contact_id_736 = \'' . $record['CONSTITUENT_ID'] . '\' LIMIT 1')) {
          continue;
        }
        if (!CRM_Core_DAO::singleValueQuery('SELECT constituent_id FROM missing_re_contact WHERE constituent_id = \'' . $record['CONSTITUENT_ID'] . '\'')) {
          continue;
        }
        foreach ($attributes as $key => $columnName) {
          if ($columnName != 'id') {
            $params[$columnName] = $record[$key];
          }
        }
        $rule = NULL;
        if (!empty($record['ORG_NAME'])) {
          $params['contact_type'] = 'Organization';
        }
        else {
          $params['contact_type'] = 'Individual';
          $rule = 'RE_Individual_Rule_9';
        }
        $params = array_merge($params, self::getAddressParam($record['CONSTITUENT_ID']));

        $params['id'] = self::checkDuplicate($params, $rule);

        try {
          $contact = civicrm_api3('Contact', 'create', $params);
          self::createPhoneParam($record['CONSTITUENT_ID'], $contact['id']);
          CRM_Core_DAO::executeQuery("DELETE FROM missing_re_contact WHERE constituent_id = '" . $record['CONSTITUENT_ID'] . "'");
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['CONSTITUENT_ID'], 'records', [], $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function checkDuplicate($contactParams = array(), $rule = NULL) {
    $cid = NULL;
    if (!empty($contactParams)) {
      // Check with first, last and email for a duplicate.
      if (CRM_Utils_Array::value('organization_name', $contactParams)) {
        $type = "Organization";
        $params = array(
          'organization_name' => $contactParams['organization_name'],
          'contact_type' => $type,
        );
      }
      else {
        $type = "Individual";
        $params = array(
          'first_name' => $contactParams['first_name'],
          'last_name' => $contactParams['last_name'],
          'contact_type' => $type,
        );
      }
      $dedupeParams = CRM_Dedupe_Finder::formatParams($params, $type);
      $dedupeParams['check_permission'] = FALSE;
      if ($type == 'Individual') {
        $rule = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_dedupe_rule_group WHERE name = '{$rule}'");
      }
      $dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, $type);
      $cid = CRM_Utils_Array::value('0', $dupes, NULL);
    }

    return $cid;
  }

  public static function createPhoneParam($constituentID, $contactID) {
    $sql = "
    SELECT DISTINCT
    phones.CONSTIT_ID,
    NUM,
    DO_NOT_CALL,
    LONGDESCRIPTION AS location_type,
    phones.SEQUENCE,
    phones.PHONESID,
    phones.INACTIVE
    FROM phones
    LEFT JOIN tableentries ON PHONETYPEID = TABLEENTRIESID
    LEFT JOIN records r ON r.ID = phones.CONSTIT_ID
    WHERE CONSTIT_RELATIONSHIPS_ID IS NULL AND phones.CONSTIT_ID = '$constituentID'
    ORDER BY phones.PHONESID, phones.SEQUENCE
    ";
    $result = SQL::singleton()->query($sql);

    $params = $emailParams = $phoneParams = $websiteParams = [];
    foreach ($result as $k => $record) {
      if (CRM_Utils_Rule::phone($record['NUM'])) {
        $phoneParams[] = array_merge(
          ['phone' => $record['NUM'], 'entity_id' => $record['PHONESID']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'], TRUE)
        );
      }
      elseif (strstr($record['NUM'], '@')) {
        $emailParams[] = array_merge(
          [
            'email' => $record['NUM'],
            'entity_id' => $record['PHONESID'],
            'on_hold' => ($record['INACTIVE'] == 0) ? 0 : 1,
          ],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'])
        );
      }
      elseif ($record['location_type'] == 'Website') {
        $websiteParams[] = array_merge(
          ['url' => $record['NUM'], 'entity_id' => $record['PHONESID']],
          FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type'])
        );
      }
    }

    foreach (['Email', 'Phone', 'Website'] as $type) {
      $records = ($type == 'Email') ? $emailParams : ($type == 'Phone') ? $phoneParams : $websiteParams;
      if (!empty($records)) {
        foreach ($records as $key => $record) {
          $params = array_merge([
            'contact_id' => $contactID,
            'is_primary' => ($key == 0),
          ], $record);
          try {
            civicrm_api3($type, 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['entity_id'], 'PHONES', $params, $e->getMessage());
          }
        }
      }
    }

    return $params;
  }

  public static function recordError($entityID, $entityTable, $params, $errorMessage) {
    $sql = sprintf(
      "INSERT INTO `re_error_data`(column_name, table_name, parameters, error_message) VALUES('%s', '%s', '%s', '%s')",
      $entityID,
      $entityTable,
      serialize($params),
      serialize($errorMessage)
    );
    CRM_Core_DAO::executeQuery($sql);
  }

  public static function getAddressParam($constituentID) {
    $sql = "
    SELECT
    ca.ADDRESS_ID,
    ca.CONSTIT_ID,
    LOC_TYPE.LONGDESCRIPTION as location_type,
    CTY.LONGDESCRIPTION as country,
    ADDRESS_BLOCK,
    CITY,
    STATE,
    POST_CODE,
    ca.PREFERRED,
    ca.INDICATOR
    FROM address a
    LEFT JOIN tableentries AS CTY ON CTY.TABLEENTRIESID = COUNTRY
    JOIN constit_address ca ON a.ID = ca.ADDRESS_ID
    LEFT JOIN tableentries AS LOC_TYPE ON ca.TYPE = LOC_TYPE.TABLEENTRIESID
    LEFT JOIN records r ON ca.CONSTIT_ID = r.ID
    LEFT JOIN constit_address cr ON ca.ID = cr.ADDRESS_ID AND ca.CONSTIT_ID = cr.CONSTIT_ID
    WHERE ca.INDICATOR <> 7 AND ADDRESS_BLOCK IS NOT NULL AND ca.CONSTIT_ID = '$constituentID' ";
    $result = SQL::singleton()->query($sql);

    $attributes = FieldMapping::address();
    $addressParams = [];
    foreach ($result as $k => $record) {
      foreach ($attributes as $key => $columnName) {
        if ($key == 'location_type') {
          $params['location_type_id'] = CRM_Utils_Array::value($record[$key], FieldMapping::locationType(), 'Home');
        }
        elseif ($key == 'STATE' && !empty($record[$key])) {
          try {
            $params['state_province_id'] = civicrm_api3('StateProvince', 'getvalue', [
              'abbreviation' => $record[$key],
              'options' => [
                'limit' => 1,
                'sort' => 'id ASC',
              ],
              'return' => 'id',
            ]);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['ADDRESS_ID'], 'phones', $params, $e->getMessage());
          }
          continue;
        }
        elseif ($key == 'country') {
          if (empty($record[$key])) {
            try {
              $params['country_id'] = civicrm_api3('StateProvince', 'getvalue',[
                'id' => $params['state_province_id'],
                'options' => [
                  'limit' => 1,
                  'sort' => 'id ASC',
                ],
                'return' => 'country_id',
              ]);
            }
            catch (CiviCRM_API3_Exception $e) {}
          }
          else {
            try {
              $params['country_id'] = civicrm_api3('Country', 'getvalue',[
                'name' => $record[$key],
                'options' => [
                  'limit' => 1,
                  'sort' => 'id ASC',
                ],
                'return' => 'id',
              ]);
            }
            catch (CiviCRM_API3_Exception $e) {}
          }
          continue;
        }
        $params[$columnName] = $record[$key];
      }
      if ($k > 0) {
        $i = $k + 1;
        $addressParams['api.Address.create.' . $i] = $params;
      }
      else {
        $addressParams['api.Address.create'] = ['is_primary' => TRUE] + $params;
      }
    }

    return $addressParams;
  }

  public static function createMissingEmail() {
    $offset = 0;
    $limit = 1000;
    $totalCount = 30000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT DISTINCT
      phones.CONSTIT_ID,
      NUM,
      DO_NOT_CALL,
      LONGDESCRIPTION AS location_type,
      phones.SEQUENCE,
      phones.PHONESID,
      phones.INACTIVE
      FROM phones
      LEFT JOIN tableentries ON PHONETYPEID = TABLEENTRIESID
      LEFT JOIN records r ON r.ID = phones.CONSTIT_ID
      WHERE CONSTIT_RELATIONSHIPS_ID IS NULL AND phones.NUM LIKE '%@%'
      ORDER BY phones.PHONESID, phones.SEQUENCE
      LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        if ($contactID = CRM_Core_DAO::singleValueQuery("SELECT entity_id FROM civicrm_value_re_contact_de_35 WHERE re_contact_id_736 = '" . $record['CONSTIT_ID'] . "' LIMIT 1 ")) {
          $params = [
            'email' => $record['NUM'],
            'on_hold' => ($record['INACTIVE'] == 0) ?: 1,
            'contact_id' => $contactID,
          ] + FieldMapping::getLocationTypeOfPhoneEmailWebsite($record['location_type']);
          try {
            civicrm_api3('Email', 'create', $params);
            CRM_Core_DAO::executeQuery("DELETE FROM re_error_data WHERE column_name = '" . $record['PHONESID'] . "' AND table_name = 'PHONES'");
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['PHONESID'], 'PHONES', $params, $e->getMessage());
          }
        }

      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function createGroupContact($apiParams) {
    $tableName = FieldInfo::getCustomTableName('RE_group_details');
    $columnName = FieldInfo::getCustomFieldColumnName('re_group_id');
    $groupCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_group_id',
      'return' => 'id',
    ]);

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $offset = 0;
    $limit = 1000;
    $totalCount = 70000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT DISTINCT te.LONGDESCRIPTION as group, cc.*
       FROM `constituent_codes` cc
        INNER JOIN records r ON r.CONSTITUENT_ID = cc.CONSTIT_ID
        LEFT JOIN tableentries te ON te.TABLEENTRIESID = cc.CODE
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $groupID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $tableName, $columnName, $record['CODE']));
        if (empty($groupID)) {
          try {
            $params = [
              'title' => $record['group'],
              'custom_' . $groupCustomFieldID => $record['CODE'],
            ];
            $groupID = civicrm_api3('Group', 'create', $params)['id'];
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['CODE'], 'GROUPS', $params, $e->getMessage());
          }
        }
        $contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['CONSTIT_ID']));

        if (empty($contactID)) {
          self::recordError($record['CONSTIT_ID'], 'records', [], 'No contact found');
        }
        try {
          $params = [
            'contact_id' => $contactID,
            'group_id' => $groupID,
            "status" => "Added",
          ];
          civicrm_api3('GroupContact', 'create', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['ID'], 'constituent_codes', $params, $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function createSolicitCodes($apiParams) {
    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');
    $attributes = FieldMapping::solicitCode();

    $offset = 0;
    $limit = 1000;
    $totalCount = 10000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT ID, RECORDSID AS external_identifier, LONGDESCRIPTION as solicit_code
      FROM constituent_solicitcodes JOIN tableentries ON SOLICIT_CODE = tableentries.TABLEENTRIESID
      WHERE tableentries.ACTIVE = -1
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['external_identifier']));
        if (!empty($contactID)) {
          $params = [
            'contact_id' => $contactID,
          ];
          if ($record['solicit_code'] == 'Do not contact') {
            $params += [
              'do_not_email' => 1,
              'do_not_phone' => 1,
              'do_not_sms' => 1,
              'do_not_trade' => 1,
            ];
          }
          elseif (array_key_exists($record['solicit_code'], $attributes)) {
            $params[$attributes[$record['solicit_code']]] = 1;
          }
          else {
            continue;
          }
          try {
            civicrm_api3('Contact', 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['ID'], 'CONSTITUENT_SOLICITCODES', $params, $e->getMessage());
          }
        }
      }

      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function createFinancialTypes($apiParams) {
    $offset = 0;
    $limit = 100;
    $totalCount = self::getTotalCountByRETableName('fund');
    while ($limit <= $totalCount) {
      $sql = "
      SELECT
      ID,
      DESCRIPTION,
      SUBSTRING(DESCRIPTION , 1, LOCATE('-', DESCRIPTION) - 1) as fund_name,
      SUBSTRING(FUND_ID, 1, 4) as account_code
       FROM fund
      ORDER BY ID
      LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $params = [
          'name' => !CRM_Utils_System::isNull($record['fund_name']) ? $record['fund_name'] : $record['DESCRIPTION'],
          'is_active' => TRUE,
        ];
        if (empty($params['name'])) {
          continue;
        }
        $r = civicrm_api3('FinancialAccount', 'get', [
          'sequential' => 1,
          "financial_account_type_id" => "Revenue",
          'accounting_code' => $record['account_code'],
        ])['values'];
        if (!empty($r)) {
          continue;
        }
        try {
          $financialTypeID = civicrm_api3('FinancialType', 'create', $params)['id'];
          $FAID = civicrm_api3('EntityFinancialAccount', 'getsingle', [
            'entity_table' => 'civicrm_financial_type',
            'entity_id' => $financialTypeID,
            "financial_account_id.financial_account_type_id" => "Revenue",
          ])['financial_account_id'];
          civicrm_api3('FinancialAccount', 'create', [
            'id' => $FAID,
            'accounting_code' => $record['account_code'],
          ]);
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['ID'], 'fund', $params, $e->getMessage());
        }

      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 100;
    }
  }

  public static function createCampaign($apiParams) {
    $offset = 0;
    $limit = 100;
    $totalCount = self::getTotalCountByRETableName('campaign');
    $attributes = FieldMapping::campaign();

    while ($limit <= $totalCount) {
      $sql = " SELECT * FROM campaign LIMIT $offset, $limit ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $params = [
          'status_id' => "Completed",
          'campaign_type_id' => "Constituent Engagement",
        ];
        foreach ($attributes as $key => $columnName) {
          $params[$columnName] = $record[$key];
        }
        try {
          civicrm_api3('Campaign', 'create', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['ID'], 'campaign', $params, $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 100;
    }
  }

  public static function createContribution($apiParams) {
    require_once 'CRM/EFT/BAO/EFT.php';
    $offset = 0;
    $limit = 1000;
    $totalCount = self::getTotalCountByRETableName('giftsplit');
    $paymentTypes = FieldMapping::paymentType();
    $financialTypeCodes = FieldMapping::financtypeToRevenueCode();
    $priceSetParams = FieldInfo::createREPriceSet();

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $reCampignTableName = FieldInfo::getCustomTableName('RE_campaign_details');
    $reCampaignCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_campaign_id');

    $reContributionTableName = FieldInfo::getCustomTableName('RE_contribution_details');
    $reContributionCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contribution_id');

    $contributionCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_contribution_id',
      'return' => 'id',
    ]);

    while ($limit <= $totalCount) {
      $result = SQL::singleton()->query("
       SELECT ID FROM gift
        WHERE ID NOT IN (SELECT DISTINCT PledgeId FROM installment)
        AND ID NOT IN (SELECT DISTINCT RecurringGiftId FROM recurringgiftactivity)
        LIMIT $offset, $limit
      ");
      $result = CRM_Core_DAO::executeQuery("SELECT column_name as ID FROM re_error_data WHERE table_name LIKE 'gift' LIMIT $offset, $limit ")->fetchAll();
      foreach ($result as $k => $record) {
        if (CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContributionTableName, $reContributionCustomFieldColumnName, $record['ID']))) {
          continue;
        }
        $sql = "
        SELECT
  gs.GiftSplitId,
  gs.GiftId,
  g.ID
  , g.CONSTIT_ID
  , r.CONSTITUENT_ID
  , gs.Amount
  , g.CURRENCY_AMOUNT as total_amount
  , g.DTE as gift_date
  , gs.CampaignId
  , fund.DESCRIPTION as fund
  , SUBSTRING(fund.FUND_ID, 1, 4) as account_code
  , SUBSTRING(fund.FUND_ID, 6, 4) as chapter_code
  , campaign.DESCRIPTION as campaign
  , appeal.DESCRIPTION as appeal
  , g.PAYMENT_TYPE
  , g.ACKNOWLEDGE_FLAG
  , g.CHECK_NUMBER
  , g.CHECK_DATE
  , g.BATCH_NUMBER
  , g.ANONYMOUS
  , gst.LONGDESCRIPTION as giftsubtype
  , g.TYPE
  FROM giftsplit gs
  LEFT JOIN fund on gs.FundId = fund.id
  LEFT JOIN appeal on gs.AppealId = appeal.id
  LEFT JOIN campaign on gs.CampaignId = campaign.id
  LEFT JOIN gift g on gs.GiftId = g.ID
  LEFT JOIN records r on r.ID = g.CONSTIT_ID
  LEFT JOIN tableentries gst on g.GIFTSUBTYPE = gst.TABLEENTRIESID
  WHERE g.ID = {$record['ID']}
        ";
        $lineitems = SQL::singleton()->query($sql);
        $params = $chapterItems = [];
        $firstChapter = NULL;
        $priceFieldIDs = array_keys($priceSetParams['price_field_id']);
        $firstItem = $lineitems[0];
        if (count($lineitems) > 1) {
          $params['line_item'] = [$priceSetParams['price_set_id'] => []];
          $financialTypeID = NULL;
          foreach ($lineitems as $k => $lineItem) {
            $params['line_item'][$priceSetParams['price_set_id']][$priceFieldIDs[$k]] = [
              'financial_type_id' => CRM_Utils_Array::value($lineItem['account_code'], $financialTypeCodes),
              'unit_price' => 1.00,
              'line_total' => $lineItem['Amount'],
              'qty' => 1.00,
              'label' => empty($lineItem['appeal']) ? 'RE contribution amount ' : $lineItem['appeal'],
              'price_field_id' => $priceFieldIDs[$k],
              'price_field_value_id' => $priceSetParams['price_field_id'][$priceFieldIDs[$k]],
            ];

            if (!empty($lineItem['chapter_code'])) {
              // Add chapter codes for each line item.
              $chapterItems[$lineItem['GiftSplitId']] = [
                'chapter' => $lineItem['chapter_code'],
                'price_field_value_id'=> $priceSetParams['price_field_id'][$priceFieldIDs[$k]],
                'price_field_id' => $priceFieldIDs[$k],
              ];
            }
          }
        }
        elseif (!empty($firstItem['chapter_code'])) {
          $firstChapter = $firstItem['chapter_code'];
        }
        $params['total_amount'] = $firstItem['total_amount'];
        $params['campaign_id'] = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reCampignTableName, $reCampaignCustomFieldColumnName, $firstItem['CampaignId']));
        $params['payment_instrument_id'] = $paymentTypes[$firstItem['PAYMENT_TYPE']];
        $params['recieve_date'] = $firstItem['gift_date'];
        $params['financial_type_id'] = CRM_Utils_Array::value($firstItem['account_code'], $financialTypeCodes);
        $params['check_number'] = $firstItem['CHECK_NUMBER'];
        $params['currency'] = 'USD';
        $params['skipRecentView'] = TRUE;
        $params['contribution_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
        $params['custom_' . $contributionCFID] = $firstItem['ID'];
        $params['contact_id'] = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $firstItem['CONSTIT_ID']));
        if (empty($params['contact_id']) && !empty($firstItem['CONSTIT_ID'])) {
          if ($constiID = self::getTotalCountByRESQL(sprintf("SELECT CONSTITUENT_ID as total_count FROM records WHERE ID = '%s'", $firstItem['CONSTIT_ID']))) {
            $params['contact_id'] = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $constiID));
          }
        }
        $params['source'] = str_replace("'", "\'", $firstItem['appeal']);
        try {
          $contribution = civicrm_api3('Contribution', 'create', $params);

          // Now add entry for chapter codes as well.
          if (!empty($chapterItems)) {
            // Get Lineitems
            $lis = civicrm_api3('LineItem', 'get', ['contribution_id' => $contribution['id']])['values'];
            foreach ($lis as $li) {
              foreach ($chapterItems as $chapter) {
                if (($chapter['price_field_value_id'] == $li['price_field_value_id']) && ($chapter['price_field_id'] == $li['price_field_id'])) {
                  $eft = new CRM_EFT_DAO_EFT;
                  $eft->entity_id = $li['id'];
                  $eft->entity_table = "civicrm_line_item";
                  $eft->chapter_code = $chapter['chapter'];
                  $eft->save();
                }
              }
            }
          }
          if (!empty($firstChapter)) {
            CRM_EFT_BAO_EFT::addChapterFund($firstChapter, NULL, $contribution['id'], "civicrm_line_item");
          }
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($firstItem['ID'], 'gift', $params, $e->getMessage());
          CRM_Core_DAO::executeQuery("DELETE FROM re_error_data WHERE column_name = '" . $record['ID'] . "' AND table_name = 'gift'");
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 10;
    }
  }

  public static function createRecurringContribution($apiParams) {
    require_once 'CRM/EFT/BAO/EFT.php';
    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $reContributionTableName = FieldInfo::getCustomTableName('RE_contribution_details');
    $reContributionCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contribution_id');

    $financialTypeCodes = FieldMapping::financtypeToRevenueCode();
    $paymentTypes = FieldMapping::paymentType();

    $contributionRecurCFID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_contribution_recur_id',
      'return' => 'id',
    ]);

    $result = SQL::singleton()->query("
    SELECT RecurringGiftId,
       MAX(VInstallmentNumber) as installment,
       MIN(VInstallmentDate) as start_date,
       MAX(VInstallmentDate) as end_date
     FROM `recurringgiftactivity`
      GROUP BY RecurringGiftId
    ");
    foreach ($result as $k => $record) {
      $params = [
        'custom_' . $contributionRecurCFID => $record['RecurringGiftId'],
        'installments' => $result['installment'],
        'frequency_interval' => $result['installment'],
        'start_date' => $result['start_date'],
        'end_date' => $result['end_date'],
        'contribution_status_id' => "Completed",
      ];
      $recurDetails = SQL::singleton()->query("
      SELECT DISTINCT
        g.CONSTIT_ID,
        g.ID as GiftId,
        g.PAYMENT_TYPE,
        SUBSTRING(fund.FUND_ID, 1, 4) as account_code,
        SUBSTRING(fund.FUND_ID, 6, 4) as chapter_code,
        g.INSTALLMENT_FREQUENCY,
        g.Amount
        FROM gift g
        LEFT JOIN giftsplit gs on g.ID = gs.GiftId
        LEFT JOIN fund on gs.FundId = fund.id
        WHERE g.ID = {$record['RecurringGiftId']}
      ")[0];
      $params['contact_id'] = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $recurDetails['CONSTIT_ID']));
      $params['financial_type_id'] = CRM_Utils_Array::value($recurDetails['account_code'], $financialTypeCodes);
      $params['frequency_unit'] = self::getInstallmentFrequency($recurDetails['INSTALLMENT_FREQUENCY']);
      $params['amount'] = (float) $params['installments'] * $recurDetails['Amount'];
      $params['payment_instrument_id'] = $paymentTypes[$recurDetails['PAYMENT_TYPE']];
      try {
        $contributionRecurID = civicrm_api3('ContributionRecur', 'create', $params)['id'];

        // Save chapter information for recurring contributions.
        $eft = new CRM_EFT_DAO_EFT;
        $eft->entity_id = $contributionRecurID;
        $eft->entity_table = "civicrm_contribution_recur";
        $eft->chapter_code = $recurDetails['chapter_code'];
        $eft->save();

        self::createRecurPayment($contributionRecurID, $record['GiftId'], $reContributionTableName, $reContributionCustomFieldColumnName);
      }
      catch (CiviCRM_API3_Exception $e) {
        self::recordError($record['GiftId'], 'recurring_contribution', $params, $e->getMessage());
      }
    }
  }

  public static function createRecurPayment($contributionRecurID, $giftID, $reContributionTableName, $reContributionCustomFieldColumnName) {
    $result = SQL::singleton()->query("
    SELECT PaymentId
       VInstallmentDate as recieve_date
     FROM `recurringgiftactivity`
     WHERE RecurringGiftId = {$giftID}
    ");
    foreach ($result as $k => $record) {
      if ($contributionID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['PaymentId']))) {
        civicrm_api3('Contribution', 'create', [
          'id' => $contributionID,
          'recieve_date' => $record['recieve_date'],
          'contribution_recur_id' => $contributionRecurID,
        ]);
      }
    }
  }

  public static function createPledges($apiParams) {
    require_once 'CRM/EFT/BAO/EFT.php';
    $offset = 0;
    $limit = 100;
    $totalCount = self::getTotalCountByRESQL('SELECT COUNT(DISTINCT g.ID) as total_count FROM gift g INNER JOIN installment i ON g.ID = i.PledgeId');
    $financialTypeCodes = FieldMapping::financtypeToRevenueCode();

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $rePledgeIDCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_pledge_id',
      'return' => 'id',
    ]);
    $rePledgeFreqCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_pledge_frequency',
      'return' => 'id',
    ]);

    while ($limit <= $totalCount) {
      $result = SQL::singleton()->query("
      SELECT DISTINCT
        g.CONSTIT_ID
        , g.ID as GiftId
        , g.Amount
        , g.DTE as receive_date
        , fund.DESCRIPTION as fund
        , SUBSTRING(fund.FUND_ID, 1, 4) as account_code
        , SUBSTRING(fund.FUND_ID, 6, 4) as chapter_code
        , campaign.DESCRIPTION as campaign
        , appeal.DESCRIPTION as appeal
        , g.PAYMENT_TYPE
        , g.ACKNOWLEDGEDATE
        , g.TYPE as type
        , g.REF as note
        ,DATE_1ST_PAY
        ,g.DATEADDED
        ,g.DATECHANGED
        ,INSTALLMENT_FREQUENCY
        ,NUMBER_OF_INSTALLMENTS
        ,POST_DATE
        ,POST_STATUS
        ,REMIND_FLAG
        ,Schedule_Month
        ,Schedule_DayOfMonth
        ,Schedule_MonthlyDayOfWeek
        ,Schedule_Spacing
        ,Schedule_MonthlyType
        ,Schedule_MonthlyOrdinal
        ,Schedule_WeeklyDayOfWeek
        ,Schedule_DayOfMonth2
        ,Schedule_SMDayType1
        ,Schedule_SMDayType2
        ,NextTransactionDate
        ,Schedule_EndDate
        ,FrequencyDescription
        , r.CONSTITUENT_ID
        FROM Gift g
        LEFT JOIN giftsplit gs on g.ID = gs.GiftId
        LEFT JOIN fund on gs.FundId = fund.id
        LEFT JOIN appeal on gs.AppealId = appeal.id
        LEFT JOIN campaign on gs.CampaignId = campaign.id
        LEFT JOIN records r ON g.CONSTIT_ID = r.ID
        JOIN installment i ON g.ID = i.PledgeId
        LIMIT $offset, $limit "
      );

      foreach ($result as $k => $record) {
        $params = [
          'contact_id' => CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['CONSTIT_ID'])),
          'installments' => $record['NUMBER_OF_INSTALLMENTS'],
          'start_date' => date('Y-m-d', $record['DATE_1ST_PAY']),
          'create_date' => date('Y-m-d', $record['DATEADDED']),
          'financial_type_id' => CRM_Utils_Array::value($record['account_code'], $financialTypeCodes),
          'amount' => $record["Amount"],
          'frequency_interval' => 1,
          'original_installment_amount' => (float) ($record["Amount"] / $record['NUMBER_OF_INSTALLMENTS']),
          'frequency_unit' => self::getInstallmentFrequency($record['INSTALLMENT_FREQUENCY']),
          'frequency_day' => CRM_Utils_Array::value('Schedule_DayOfMonth', $record, NULL),
          'custom_' . $rePledgeIDCustomFieldID => $record['GiftId'],
          'custom_' . $rePledgeFreqCustomFieldID => CRM_Utils_Array::value('FrequencyDescription', $record, NULL),
        ];
        if ($ack = CRM_Utils_Array::value('ACKNOWLEDGEDATE', $record, NULL)) {
          $params['acknowledge_date'] = $ack;
        }
        try {
          $pledgeID = civicrm_api3('Pledge', 'create', $params)['id'];

          // Save chapter information for pledges.
          $eft = new CRM_EFT_DAO_EFT;
          $eft->entity_id = $pledgeID;
          $eft->entity_table = "civicrm_pledge";
          $eft->chapter_code = $record['chapter_code'];
          $eft->save();

          self::createPledgePayment($pledgeID, $record['GiftId'], $params);

        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['GiftId'], 'PLEDGES', $params, $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 100;
    }
  }

  public static function createPledgePayment($pledgeID, $giftID, $params) {
    $paymentTypes = FieldMapping::paymentType();
    $civiPledgePayments = civicrm_api3('PledgePayment', 'get', ['pledge_id' => $pledgeID, 'sequential' => 1])['values'];
    $REpledgePayments = SQL::singleton()->query("
   SELECT
     i.InstallmentId
     , g.ID
     , i.Amount
     , i.Dte
     , g.PAYMENT_TYPE
     FROM installment i
     INNER JOIN gift g on i.PledgeId = g.ID
     WHERE g.ID = $giftID
    ");
    foreach ($REpledgePayments as $k => $REpledgePayment) {
      try {
        $contributionID = civicrm_api3('Contribution', 'create', [
          'total_amount' => $REpledgePayment['Amount'],
          'payment_instrument_id' => $paymentTypes[$REpledgePayment['PAYMENT_TYPE']],
          'recieve_date' => $REpledgePayment['Dte'],
          'currency' => 'USD',
          'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
          'contact_id' => $params['contact_id'],
          'financial_type_id' => $params['financial_type_id'],
        ]);
        $PPParams = [
          'id' => $civiPledgePayments[$k]['id'],
          'contribution_id' => $contributionID,
          'scheduled_amount' => $REpledgePayment['Amount'],
          'start_date' => $REpledgePayment['Dte'],
        ];
        civicrm_api3('PledgePayment', 'create', $PPParams);
      }
      catch (CiviCRM_API3_Exception $e) {
        self::recordError($REpledgePayment['InstallmentId'], 'PLEDGEPAYMENT', $PPParams, $e->getMessage());
      }
    }
  }

  public static function getInstallmentFrequency($freq) {
    switch ($freq) {
    case 5:
      return 'month';
    case 10:
      return 'day';
    case 1:
      return 'year';
    default:
      break;
    }
  }

  public static function createActivity($apiParams) {
    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');
    $attributes = FieldMapping::activity();

    $offset = 0;
    $limit = 1000;
    $totalCount = 2000;
    while ($limit <= $totalCount) {
      $sql = "
      SELECT
        a.ADDED_BY
      , a.ID
      , a.AUTO_REMIND
      , a.RECORDS_ID as external_identifier
      , cr.RELATION_ID as action_contact_id
      , a.DTE
      , actionnotepad.Description
      , actionnotepad.title
      , LETTER.LONGDESCRIPTION as letter
      , a.PRIORITY
      , a.DateAdded
      , a.DateChanged
      , a.REMIND_VALUE
      , a.CATEGORY
      , a.Completed
      , a.COMPLETED_DATE
      , a.FUND_ID
      , a.FOLLOWUPTO_ID
      , a.TRACKACTION_ID
      , a.PhoneNumber as phone_number
      , a.Remind_Frequency
      , a.WORDDOCNAME
      , a.APPEAL_ID
      , a.APPEAL_LETTER_CODE
      , a.OUTLOOK_EMAIL_SUBJECT
      , STATUS.LONGDESCRIPTION as status
      , TYPE.LONGDESCRIPTION as type
      , LOCATION.LONGDESCRIPTION as location
      , actionnotepad.ActualNotes
      , campaign.DESCRIPTION as campaign
      FROM actions a
      LEFT JOIN tableentries as STATUS ON a.STATUS = STATUS.TABLEENTRIESID
      LEFT JOIN tableentries as TYPE ON a.TYPE = TYPE.TABLEENTRIESID
      LEFT JOIN tableentries as LOCATION ON a.Location = LOCATION.TABLEENTRIESID
      LEFT JOIN tableentries as LETTER on a.LETTER_CODE = LETTER.TABLEENTRIESID
      LEFT JOIN actionnotepad ON a.ID = actionnotepad.ParentId
      LEFT JOIN campaign on a.CAMPAIGN_ID = campaign.id
      LEFT JOIN constit_relationships cr on a.CONTACT_ID = cr.ID
      LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $params = [];
        foreach ($attributes as $key => $columnName) {
          if (empty($record[$key])) {
            continue;
          }
          if (in_array($key, ['ADDED_BY', 'external_identifier', 'action_contact_id'])) {
            $contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record[$key]));
            if ($contactID) {
              $params[$columnName] = $contactID;
            }
          }
          elseif ($key == 'status') {
            $params[$columnName] = CRM_Utils_Array::value($record[$key], FieldMapping::activityStatus(), 'Completed');
          }
          elseif ($key == 'type') {
            $activityTypeID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', $record[$key]);
            if (!$activityTypeID) {
              $activityTypeID = civicrm_api3('OptionValue', 'create', [
                'label' => $record[$key],
                'option_group_id' => 'activity_type',
              ]);
            }
            $params[$columnName] = $activityTypeID;
          }
          elseif ($key == 'PRIORITY') {
            $params[$columnName] = $record[$key] == 1 ? 'Normal' : 'Low';
          }
          elseif ($key == 'Description') {
            $params[$columnName] = str_replace("'", '', $record[$key]);
          }
          else {
            $params[$columnName] = $record[$key];
          }
        }
        try {
          if (empty($params['target_contact_id'])) {
            $params['target_contact_id'] = [$params['source_contact_id']];
          }
          civicrm_api3('Activity', 'create', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['ID'], 'actions', [], $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }


  public static function createContactNotes() {
    $offset = 0;
    $limit = 100;
    $totalCount = self::getTotalCountByRETableName('constituentnotepad');

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $reContactNoteCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_contact_note',
      'return' => 'id',
    ]);

    while ($limit <= $totalCount) {
      $sql = "
      SELECT
        Title as subject
        , NotesId
        , Author
        , ActualNotes
        , CONVERT(Notes USING utf8) as note
        , ParentId
        , cn.DateChanged
        , cn.DateAdded
        , LONGDESCRIPTION as NoteType
        FROM constituentnotepad cn
        LEFT JOIN tableentries ON NoteTypeId = TABLEENTRIESID
        LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        if ($contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['ParentId']))) {
          $params = [
            'entity_table' => 'civicrm_contact',
            'entity_id' => $contactID,
            'subject' => !CRM_Utils_System::isNull($record['subject']) ? $record['subject'] : $record['Author'],
            'note' => $record['ActualNotes'],
            'modified_date' => $record['DateChanged'],
          ];
          if (strstr($record['note'], '{\rtf1')) {
            $reader = new RtfReader();
            $formatter = new RtfHtml('UTF-8');
            $rtftext = $reader->Parse($record['note']);
            civicrm_api3('Contact', 'create', [
              'id' => $contactID,
              'custom_' . $reContactNoteCustomFieldID => sprintf('%s',$formatter->Format($reader->root)),
            ]);
          }
          try {
            if (empty($params['note'])) {
              continue;
            }
            civicrm_api3('Note', 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['NotesId'], 'ConstituentNotepad', [], $e->getMessage());
          }
        }
        else {
          self::recordError($record['NotesId'], 'ConstituentNotepad', [], 'Contact ID Not found for record ID : ' . $record['ParentId']);
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 100;
    }
  }

  public static function createActivityNotes() {
    $offset = 0;
    $limit = 100;
    $totalCount = self::getTotalCountByRETableName('actionnotepad');

    $reActivityTableName = FieldInfo::getCustomTableName('RE_activity_details');
    $reActivityCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_activity_id');

    $reActivityNoteCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_activity_note_id',
      'return' => 'id',
    ]);

    while ($limit <= $totalCount) {
      $sql = "
      SELECT
        Title as subject
        , NotesId
        , Author
        , ActualNotes
        , CONVERT(Notes USING utf8) as note
        , ParentId
        FROM actionnotepad an
        LEFT JOIN tableentries ON NoteTypeId = TABLEENTRIESID
        LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        if ($activityID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reActivityTableName, $reActivityCustomFieldColumnName, $record['ParentId']))) {
          $params = [
            'id' => $activityID,
            'details' => $record['ActualNotes'],
            'custom_' . $reActivityNoteCustomFieldID => $record['NotesId'],
          ];
          if (strstr($record['note'], '{\rtf1')) {
            $reader = new RtfReader();
            $formatter = new RtfHtml('UTF-8');
            $rtftext = $reader->Parse($record['note']);
            $params['details'] = sprintf('%s',$formatter->Format($reader->root));
          }
          try {
            civicrm_api3('Activity', 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['NotesId'], 'ActionNotepad', $params, $e->getMessage());
          }
        }
        else {
          self::recordError($record['NotesId'], 'ActionNotepad', [], 'Activity ID Not found for action ID : ' . $record['ParentId']);
        }
      }
    }
  }

  public static function createRelationship($apiParams) {
    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');
    $reRelationshipCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_relationship_id',
      'return' => 'id',
    ]);
    $reRelationshipABCustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_AB_relationship',
      'return' => 'id',
    ]);
    $reRelationshipBACustomFieldID = civicrm_api3('CustomField', 'getvalue', [
      'name' => 're_BA_relationship',
      'return' => 'id',
    ]);
    $employeeRelationTypeID = 5;

    $offset = 0;
    $limit = 1000;
    $totalCount = self::getTotalCountByRETableName('constit_relationships');
    while ($limit <= $totalCount) {
      $sql = "
      SELECT
cr.ID,
cr.ADDED_BY,
cr.CONSTIT_ID,
cr.RELATION_ID,
cr.RELATION_CODE,
cr.DATE_ADDED as start_date,
t1.LONGDESCRIPTION as relation_code_name,
cr.RECIP_RELATION_CODE,
t2.LONGDESCRIPTION as recip_relation_code,
cr.IS_HEADOFHOUSEHOLD,
cr.IS_SPOUSE,
cr.IS_EMPLOYEE,
cr.RELATIONSHIP_TYPE,
cr.RECIPROCAL_TYPE,
cr.POSITION
FROM constit_relationships cr
left join tableentries t1 on t1.TABLEENTRIESID = cr.RELATION_CODE
left join tableentries t2 on t2.TABLEENTRIESID = cr.RECIP_RELATION_CODE
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        if (empty($record['relation_code_name']) || empty($record['recip_relation_code'])) {
          continue;
        }
        $contactIDA = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['CONSTIT_ID']));
        $contactIDB = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['RELATION_ID']));
        if (!$contactIDA || !$contactIDB) {
          continue;
        }
        $params = [
          'custom_' . $reRelationshipCustomFieldID => $record['ID'],
          'contact_id_a' => $contactIDA,
          'contact_id_b' => $contactIDB,
          'custom_' . $reRelationshipABCustomFieldID => $record['recip_relation_code'],
          'custom_' . $reRelationshipBACustomFieldID => $record['relation_code_name'],
          'description' => $record['POSITION'],
          'skipRecentView' => TRUE,
        ];
        if (strstr($record['relation_code_name'], 'Employer') || strstr($record['recip_relation_code'], 'Employer')) {
          $params['relationship_type_id'] = $employeeRelationTypeID;
          if ($record['recip_relation_code'] == 'Employer') {
            $params['contact_id_a'] = $contactIDB;
            $params['contact_id_b'] = $contactIDA;
          }
        }
        else {
          $relationshipNameA = 'RE ' . $record['recip_relation_code'];
          $typeA = civicrm_api3('RelationshipType', 'get', [
            'name_a_b' => $relationshipNameA,
            'sequential' => 1,
          ])['values'];
          if (!empty($typeA[0]['id'])) {
            $params['relationship_type_id'] = $typeA[0]['id'];
          }
          else {
            $typeB = civicrm_api3('RelationshipType', 'get', [
              'name_b_a' => 'RE ' . $record['relation_code_name'],
              'sequential' => 1,
            ])['values'];
            $params['relationship_type_id'] = $typeB[0]['id'];
          }
          if (empty($params['relationship_type_id'])) {
            $params['relationship_type_id'] = civicrm_api3('RelationshipType', 'create', [
              'label_a_b' => $relationshipNameA,
              'name_a_b' => $relationshipNameA,
              'label_b_a' => 'RE ' . $record['relation_code_name'],
              'name_b_a' => 'RE ' . $record['relation_code_name'],
            ])['id'];
          }
          try {
            civicrm_api3('Relationship', 'create', $params);
          }
          catch (CiviCRM_API3_Exception $e) {
            self::recordError($record['ID'], 'constit_relationships', $params, $e->getMessage());
          }
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 1000;
    }
  }

  public static function createAddress($apiParams) {
    $offset = 0;
    $limit = 100;
    $totalCount = self::getTotalCountByRETableName('address');
    $attributes = FieldMapping::address();

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    while ($limit <= $totalCount) {
      $sql = "
      SELECT
      ca.ADDRESS_ID,
      ca.CONSTIT_ID,
      LOC_TYPE.LONGDESCRIPTION as location_type,
      CTY.LONGDESCRIPTION as country,
      ADDRESS_BLOCK,
      CITY,
      STATE,
      POST_CODE,
      ca.PREFERRED,
      ca.INDICATOR,
      r.CONSTITUENT_ID
      FROM address a
      LEFT JOIN tableentries AS CTY ON CTY.TABLEENTRIESID = COUNTRY
      JOIN constit_address ca ON a.ID = ca.ADDRESS_ID
      LEFT JOIN tableentries AS LOC_TYPE ON ca.TYPE = LOC_TYPE.TABLEENTRIESID
      LEFT JOIN records r ON ca.CONSTIT_ID = r.ID
      LEFT JOIN constit_address cr ON ca.ID = cr.ADDRESS_ID AND ca.CONSTIT_ID = cr.CONSTIT_ID
      WHERE ca.INDICATOR <> 7 AND ADDRESS_BLOCK IS NOT NULL
      LIMIT $offset, $limit
      ";
      $result = SQL::singleton()->query($sql);
      foreach ($result as $k => $record) {
        $params = [];
        if ($contactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $record['CONSTITUENT_ID']))) {
          $params['contact_id'] = $contactID;
        }
        else {
          self::recordError($record['ADDRESS_ID'], 'address', $params, 'Contact ID not found for - ' . $record['CONSTITUENT_ID']);
          continue;
        }
        foreach ($attributes as $key => $columnName) {
          if ($key == 'location_type') {
            $params['location_type_id'] = CRM_Utils_Array::value($record[$key], FieldMapping::locationType(), 'Home');
          }
          elseif ($key == 'STATE' && !empty($record[$key])) {
            try {
              $params['state_province_id'] = civicrm_api3('StateProvince', 'getvalue', [
                'abbreviation' => $record[$key],
                'options' => [
                  'limit' => 1,
                  'sort' => 'id ASC',
                ],
                'return' => 'id',
              ]);
            }
            catch (CiviCRM_API3_Exception $e) {}
            continue;
          }
          elseif ($key == 'country') {
            if (empty($record[$key])) {
              try {
                $params['country_id'] = civicrm_api3('StateProvince', 'getvalue',[
                  'id' => $params['state_province_id'],
                  'options' => [
                    'limit' => 1,
                    'sort' => 'id ASC',
                  ],
                  'return' => 'country_id',
                ]);
              }
              catch (CiviCRM_API3_Exception $e) {}
            }
            else {
              try {
                $params['country_id'] = civicrm_api3('Country', 'getvalue',[
                  'name' => $record[$key],
                  'options' => [
                    'limit' => 1,
                    'sort' => 'id ASC',
                  ],
                  'return' => 'id',
                ]);
              }
              catch (CiviCRM_API3_Exception $e) {}
            }
            continue;
          }
          $params[$columnName] = $record[$key];
        }
        try {
          civicrm_api3('Address', 'create', $params);
          CRM_Core_DAO::executeQuery("DELETE FROM re_error_data WHERE column_name = '" . $record['ADDRESS_ID'] . "' AND table_name = 'address'");
        }
        catch (CiviCRM_API3_Exception $e) {
          self::recordError($record['ADDRESS_ID'], 'address', $params, $e->getMessage());
        }
      }
      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 100;
    }
  }

  public static function correctContribution() {
    $reContributionTableName = FieldInfo::getCustomTableName('RE_contribution_details');
    $reContributionCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contribution_id');

    $reContactTableName = FieldInfo::getCustomTableName('RE_contact_details');
    $reContactCustomFieldColumnName = FieldInfo::getCustomFieldColumnName('re_contact_id');

    $offset = 0;
    $limit = 100;
    $totalCount = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM $reContributionTableName");

    while ($limit <= $totalCount) {
      $sql = " SELECT * FROM $reContributionTableName LIMIT $offset, $limit ";
      $result = CRM_Core_DAO::executeQuery($sql)->fetchAll();
      foreach ($result as $k => $record) {
        $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $record['entity_id']]);
        $oldContactID = $contribution['contact_id'];
        $giftID = $record[$reContributionCustomFieldColumnName];

        $gift = SQL::singleton()->query("
        SELECT r.ID, r.CONSTITUENT_ID
        FROM gift g
        INNER JOIN records r ON r.ID = g.CONSTIT_ID
        WHERE g.ID = '$giftID'
        ");
        $newContactID = CRM_Core_DAO::singleValueQuery(sprintf("SELECT entity_id FROM %s WHERE %s = '%s'", $reContactTableName, $reContactCustomFieldColumnName, $gift['CONSTITUENT_ID']))

        if ($newContactID != $oldContactID) {
          civicrm_api3('Contribution', 'create', ['id' => $contribution['id'], 'contact_id' => $newContactID]);
          $financialItems = CRM_Core_DAO::executeQuery("
          SELECT DISTINCT fi.id
           FROM civicrm_financial_item fi
            INNER JOIN civicrm_line_item li ON fi.entity_id = li.id AND li.contribution_id = $contribution['id']
          ")->fetchAll();
          foreach ($financialItems as $item) {
            civicrm_api3('FinancialItem', 'create', ['id' => $item['id'], 'contact_id' => $newContactID]);
          }
        }
      }

      $offset += ($offset == 0) ? $limit + 1 : $limit;
      $limit += 100;
    }
  }

  public static function getTotalCountByRETableName($tableName) {
    return SQL::singleton()->query("SELECT count(*) as total_count from $tableName")[0]['total_count'];
  }

  public static function getTotalCountByRESQL($sql) {
    return SQL::singleton()->query($sql)[0]['total_count'];
  }

}
