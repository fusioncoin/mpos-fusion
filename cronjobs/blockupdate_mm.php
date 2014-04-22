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

if ( $bitcoin_mm->can_connect() !== true ) {
  $log->logFatal("Failed to connect to RPC server\n");
  $monitoring->endCronjob($cron_name, 'E0006', 1, true);
}

// Fetch all unconfirmed blocks
$aAllBlocks = $block_mm->getAllUnconfirmed(max($config['network_confirmations'],$config['confirmations']));

$header = false;
foreach ($aAllBlocks as $iIndex => $aBlock) {
  !$header ? $log->logInfo("ID\tHeight\tBlockhash\tConfirmations") : $header = true;
  try
  {
      $aBlockInfo = $bitcoin_mm->getblock($aBlock['blockhash']);
  }
  catch(Exception $e)
  {
  	$log->logInfo("  can not find  Block , maybe orphan, skip [MM] ". $aBlock['blockhash']);
  	continue;
  }

  // Fetch this blocks transaction details to find orphan blocks
  $aTxDetails = $bitcoin_mm->gettransaction($aBlockInfo['tx'][0]);
  $log->logInfo($aBlock['id'] . "\t" . $aBlock['height'] .  "\t" . $aBlock['blockhash'] . "\t" . $aBlock['confirmations'] . " -> " . $aBlockInfo['confirmations']);
  if ($aTxDetails['details'][0]['category'] == 'orphan') {
    // We have an orphaned block, we need to invalidate all transactions for this one
    if ($block_mm->setConfirmations($aBlock['id'], -1)) {
      $log->logInfo("    Block marked as orphan [MM]");
    } else {
      $log->logError("    Block became orphaned but unable to update database entries [MM]");
    }
    continue;
  }
  if ($aBlock['confirmations'] == $aBlockInfo['confirmations']) {
    $log->logDebug('    No update needed');
  } else if (!$block_mm->setConfirmations($aBlock['id'], $aBlockInfo['confirmations'])) {
    $log->logError('    Failed to update block confirmations [MM]: ' . $block_mm->getCronMessage());
  }
}

require_once('cron_end.inc.php');
?>
