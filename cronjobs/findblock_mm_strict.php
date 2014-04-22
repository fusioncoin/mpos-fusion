#!/usr/bin/php
<?php

/*

Copyright:: 2013, Sebastian Grewe

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

 */

// Change to working directory
chdir(dirname(__FILE__));

// Include all settings and classes
require_once('shared.inc.php');

// Fetch our last block found from the DB as a starting point
$aLastBlock = @$block_mm->getLast();
$strLastBlockHash = $aLastBlock['blockhash'];
if (!$strLastBlockHash) $strLastBlockHash = '';

// Fetch all transactions since our last block
if ( $bitcoin_mm->can_connect() === true ){
  $aTransactions = $bitcoin_mm->listsinceblock($strLastBlockHash);
} else {
  $log->logFatal('Unable to connect to RPC server backend [MM]');
  $monitoring->endCronjob($cron_name, 'E0006', 1, true);
}

// Nothing to do so bail out
if (empty($aTransactions['transactions'])) {
  $log->logDebug('No new RPC transactions since last block [MM]');
} else {
  $header = false;
  // Let us add those blocks as unaccounted
  foreach ($aTransactions['transactions'] as $iIndex => $aData) {
    if ( $aData['category'] == 'generate' || $aData['category'] == 'immature' ) {
      // Table header, printe once if we found a block
      !$header ? $log->logInfo("Blockhash\t\tHeight\tAmount\tConfirmations\tDiff\t\tTime") : $header = true;

      $aBlockRPCInfo = $bitcoin_mm->getblock($aData['blockhash']);

      if ( !$iPreviousShareId = $block_mm->getLastShareId())
        $iPreviousShareId = 0;

      if (!$share_mm->findUpstreamShareStrict($aBlockRPCInfo, $iPreviousShareId))
        continue;

      $config['reward_type'] == 'block' ? $aData['amount'] = $aData['amount'] : $aData['amount'] = $config['reward'];
      $aData['height'] = $aBlockRPCInfo['height'];
      $aData['difficulty'] = $aBlockRPCInfo['difficulty'];
      $log->logInfo(substr($aData['blockhash'], 0, 15) . "...\t" .
        $aData['height'] . "\t" .
        $aData['amount'] . "\t" .
        $aData['confirmations'] . "\t\t" .
        $aData['difficulty'] . "\t" .
        strftime("%Y-%m-%d %H:%M:%S", $aData['time']));
      if ( ! empty($aBlockRPCInfo['flags']) && preg_match('/proof-of-stake/', $aBlockRPCInfo['flags']) ) {
        $log->logInfo("Block above with height " .  $aData['height'] . " not added to database, proof-of-stake block! [MM]");
        continue;
      }
      if (!$block_mm->addBlock($aData) ) {
        $log->logFatal('Unable to add block: ' . $aData['height'] . ': ' . $block_mm->getCronError());
      }
    }
  }
}

// Now with our blocks added we can scan for their upstream shares
$aAllBlocks = $block_mm->getAllUnsetShareId('ASC');
if (empty($aAllBlocks)) {
  $log->logDebug('No new blocks without share_id found in database');
} else {
  // Loop through our unaccounted blocks
  $log->logInfo("Block ID\tHeight\t\tAmount\tShare ID\tShares\tFinder\tWorker\t\tType");
  foreach ($aAllBlocks as $iIndex => $aBlock) {
    if (empty($aBlock['share_id'])) {
      // Fetch share information
      if ( !$iPreviousShareId = $block_mm->getLastShareId())
        $iPreviousShareId = 0;
      // Fetch this blocks upstream ID
      $aBlockRPCInfo = $bitcoin_mm->getblock($aBlock['blockhash']);
      if ($share_mm->findUpstreamShare($aBlockRPCInfo, $iPreviousShareId)) {
        $iCurrentUpstreamId = $share_mm->getUpstreamShareId();
        // Rarely happens, but did happen once to me
        if ($iCurrentUpstreamId == $iPreviousShareId) {
          $log->logFatal($share_mm->getErrorMsg('E0063'));
          $monitoring->endCronjob($cron_name, 'E0063', 1, true);
        }
        // Out of order share detection
        if ($iCurrentUpstreamId < $iPreviousShareId) {
          // Fetch our offending block
          $aBlockError = $block_mm->getBlockByShareId($iPreviousShareId);
          $log->logError('E0001: The block with height ' . $aBlock['height'] . ' found share ' . $iCurrentUpstreamId . ' which is < than ' . $iPreviousShareId . ' of block ' . $aBlockError['height'] . '.');
          if ( !($aShareError = $share_mm->getShareById($aBlockError['share_id'])) || !($aShareCurrent = $share_mm->getShareById($iCurrentUpstreamId))) {
            // We were not able to fetch all shares that were causing this detection to trigger, bail out
            $log->logFatal('E0002: Failed to fetch both offending shares ' . $iCurrentUpstreamId . ' and ' . $iPreviousShareId . '. Block height: ' . $aBlock['height']);
            $monitoring->endCronjob($cron_name, 'E0002', 1, true);
          }
          // Shares seem to be out of order, so lets change them
          if ( !$share_mm->updateShareById($iCurrentUpstreamId, $aShareError) || !$share_mm->updateShareById($iPreviousShareId, $aShareCurrent)) {
            // We couldn't update one of the shares! That might mean they have been deleted already
            $log->logFatal('E0003: Failed to change shares order: ' . $share_mm->getCronError());
            $monitoring->endCronjob($cron_name, 'E0003', 1, true);
          }
          // Reset our offending block so the next run re-checks the shares
          if (!$block_mm->setShareId($aBlockError['id'], NULL) && !$block_mm->setFinder($aBlockError['id'], NULL) || !$block_mm->setShares($aBlockError['id'], NULL)) {
            $log->logFatal('E0004: Failed to reset previous block: ' . $aBlockError['height']);
            $log->logError('Failed to reset block in database: ' . $aBlockError['height']);
            $monitoring->endCronjob($cron_name, 'E0004', 1, true);
          }
          $monitoring->endCronjob($cron_name, 'E0007', 0, true);
        } else {
          $iRoundShares = $share_mm->getRoundShares($iPreviousShareId, $iCurrentUpstreamId);
          $iAccountId = $user->getUserId($share_mm->getUpstreamFinder());
          $iWorker = $share_mm->getUpstreamWorker();
        }
      } else {
        $log->logFatal('E0005: Unable to fetch blocks upstream share, aborted:' . $share_mm->getCronError());
        $monitoring->endCronjob($cron_name, 'E0005', 0, true);
      }

      $log->logInfo(
        $aBlock['id'] . "\t\t"
        . $aBlock['height'] . "\t\t"
        . $aBlock['amount'] . "\t"
        . $iCurrentUpstreamId . "\t\t"
        . $iRoundShares . "\t"
        . "[$iAccountId] " . $user->getUserName($iAccountId) . "\t"
        . $iWorker . "\t"
        . $share_mm->share_type
      );

      // Store new information
      if (!$block_mm->setShareId($aBlock['id'], $iCurrentUpstreamId))
        $log->logError('Failed to update share ID in database for block ' . $aBlock['height'] . ': ' . $block_mm->getCronError());
      if (!empty($iAccountId) && !$block_mm->setFinder($aBlock['id'], $iAccountId))
        $log->logError('Failed to update finder account ID in database for block ' . $aBlock['height'] . ': ' . $block_mm->getCronError());
      if (!$block_mm->setFindingWorker($aBlock['id'], $iWorker))
        $log->logError('Failed to update worker ID in database for block ' . $aBlock['height'] . ': ' . $block_mm->getCronError());
      if (!$block_mm->setShares($aBlock['id'], $iRoundShares))
        $log->logError('Failed to update share count in database for block ' . $aBlock['height'] . ': ' . $block_mm->getCronError());
      if ($config['block_bonus'] > 0 && !empty($iAccountId) && !$transaction_mm->addTransaction($iAccountId, $config['block_bonus'], 'Bonus', $aBlock['id'])) {
        $log->logError('Failed to create Bonus transaction in database for user ' . $user->getUserName($iAccountId) . ' for block ' . $aBlock['height'] . ': ' . $transaction_mm->getCronError());
      }

      if ($setting->getValue('disable_notifications') != 1 && $setting->getValue('notifications_disable_block') != 1) {
        // Notify users
        $aAccounts = $notification->getNotificationAccountIdByType('new_block');
        if (is_array($aAccounts)) {
          foreach ($aAccounts as $aData) {
            $aMailData['height'] = $aBlock['height'];
            $aMailData['subject'] = 'New Block';
            $aMailData['email'] = $user->getUserEmail($user->getUserName($aData['account_id']));
            $aMailData['shares'] = $iRoundShares;
            if (!$notification->sendNotification($aData['account_id'], 'new_block', $aMailData))
              $log->logError('Failed to notify user of new found block: ' . $user->getUserName($aData['account_id']));
          }
        }
      }
    }
  }
}

require_once('cron_end.inc.php');
?>
