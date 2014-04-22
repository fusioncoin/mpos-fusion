<?php
$defflip = (!cfip()) ? exit(header('HTTP/1.1 401 Unauthorized')) : 1;

if ($user->isAuthenticated()) {
  if (! $interval = $setting->getValue('statistics_ajax_data_interval')) $interval = 300;
  // Defaults to get rid of PHP Notice warnings
  $dNetworkHashrate = 0;
  $dDifficulty = 1;
  $aRoundShares = 1;
  $dDifficulty_mm = 1;
  $aRoundShares_mm = 1;

  $aRoundShares = $statistics->getRoundShares();
  $dDifficulty = 1;
  $aRoundShares_mm = $statistics_mm->getRoundShares();
  $dDifficulty_mm = 1;
  
  $dNetworkHashrate = 1;
  $dNetworkHashrate_mm = 1;
  
  $iBlock = 0;
  $iBlock_mm = 0;
  if ($bitcoin->can_connect() === true) {
    $dDifficulty = $bitcoin->getdifficulty();
    $dNetworkHashrate = $bitcoin->getnetworkhashps();
    $iBlock = $bitcoin->getblockcount();
  }
  if ($bitcoin_mm->can_connect() === true) {
    $dDifficulty_mm = $bitcoin_mm->getdifficulty();
    $dNetworkHashrate_mm = $bitcoin_mm->getnetworkhashps();
    $iBlock_mm = $bitcoin_mm->getblockcount();
  }

  // Fetch some data
  // Round progress
  $iEstShares = $statistics->getEstimatedShares($dDifficulty);
  if ($iEstShares > 0 && $aRoundShares['valid'] > 0) {
    $dEstPercent = round(100 / $iEstShares * $aRoundShares['valid'], 2);
  } else {
    $dEstPercent = 0;
  }
  $iEstShares_mm = $statistics_mm->getEstimatedShares($dDifficulty_mm);
  if ($iEstShares_mm > 0 && $aRoundShares_mm['valid'] > 0) {
    $dEstPercent_mm = round(100 / $iEstShares_mm * $aRoundShares_mm['valid'], 2);
  } else {
    $dEstPercent_mm = 0;
  }

  if (!$iCurrentActiveWorkers = $worker->getCountAllActiveWorkers()) $iCurrentActiveWorkers = 0;
  $iCurrentPoolHashrate =  $statistics->getCurrentHashrate();
  $iCurrentPoolShareRate = $statistics->getCurrentShareRate();

  // Avoid confusion, ensure our nethash isn't higher than poolhash
  if ($iCurrentPoolHashrate > $dNetworkHashrate) $dNetworkHashrate = $iCurrentPoolHashrate;
  if ($iCurrentPoolHashrate > $dNetworkHashrate_mm) $dNetworkHashrate_mm = $iCurrentPoolHashrate;

  $dExpectedTimePerBlock = $statistics->getNetworkExpectedTimePerBlock();
  $dEstNextDifficulty = $statistics->getExpectedNextDifficulty();
  $iBlocksUntilDiffChange = $statistics->getBlocksUntilDiffChange();
  $dExpectedTimePerBlock_mm = $statistics_mm->getNetworkExpectedTimePerBlock_mm();
  $dEstNextDifficulty_mm = $statistics_mm->getExpectedNextDifficulty_mm();
  $iBlocksUntilDiffChange_mm = $statistics_mm->getBlocksUntilDiffChange_mm();

  // Make it available in Smarty
  $smarty->assign('DISABLED_DASHBOARD', $setting->getValue('disable_dashboard'));
  $smarty->assign('DISABLED_DASHBOARD_API', $setting->getValue('disable_dashboard_api'));
  $smarty->assign('ESTIMATES', array('shares' => $iEstShares, 'percent' => $dEstPercent));
  $smarty->assign('ESTIMATES_MM', array('shares' => $iEstShares_mm, 'percent' => $dEstPercent_mm));
  $smarty->assign('NETWORK', array('difficulty' => $dDifficulty, 'block' => $iBlock, 'EstNextDifficulty' => $dEstNextDifficulty, 'EstTimePerBlock' => $dExpectedTimePerBlock, 'BlocksUntilDiffChange' => $iBlocksUntilDiffChange));
  $smarty->assign('NETWORK_MM', array('difficulty' => $dDifficulty_mm, 'block' => $iBlock_mm, 'EstNextDifficulty' => $dEstNextDifficulty_mm, 'EstTimePerBlock' => $dExpectedTimePerBlock_mm, 'BlocksUntilDiffChange' => $iBlocksUntilDiffChange_mm));
  $smarty->assign('INTERVAL', $interval / 60);
  $smarty->assign('CONTENT', 'default.tpl');
}

?>
