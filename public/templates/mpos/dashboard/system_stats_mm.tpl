 <article class="module width_quarter">
   <header><h3>{$GLOBAL.config.currency_mm} {$GLOBAL.config.payout_system_mm|capitalize} Stats</h3></header>
   <div class="module_content">
     <table width="100%">
       <tbody>
{if $GLOBAL.config.payout_system_mm == 'pplns'}
         <tr>
           <td><b>PPLNS Target</b></td>
           <td id="b-pplns" class="right">{$GLOBAL.pplns.target_mm}</td>
         </tr>
{elseif $GLOBAL.config.payout_system_mm == 'pps'}
        <tr>
          <td><b>Unpaid Shares</b></td>
          <td id="b-ppsunpaid">{$GLOBAL.userdata.pps_mm.unpaidshares}</td>
        </tr>
        <tr>
          <td><b>Baseline PPS Rate</b></td>
          <td>{$GLOBAL.ppsvalue_mm|number_format:"12"} {$GLOBAL.config.currency_mm}</td>
        </tr>
        <tr>
          <td><b>Pools PPS Rate</b></td>
          <td>{$GLOBAL.poolppsvalue_mm|number_format:"12"} {$GLOBAL.config.currency_mm}</td>
        </tr>
        <tr>
          <td><b>PPS Difficulty</b></td>
          <td id="b-ppsdiff">{$GLOBAL.userdata.sharedifficulty_mm|number_format:"2"}</td>
        </tr>
{/if}
         <tr><td colspan="2">&nbsp;</td></tr>
         {include file="dashboard/round_shares_mm.tpl"}
         <tr><td colspan="2">&nbsp;</td></tr>
         {include file="dashboard/payout_estimates_mm.tpl"}
         <tr><td colspan="2">&nbsp;</td></tr>
         {include file="dashboard/network_info_mm.tpl"}
         <tr><td colspan="2">&nbsp;</td></tr>
       </tbody>
      </table>
    </div>
 </article>

