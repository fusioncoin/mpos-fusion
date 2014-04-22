         <tr>
           <td colspan="2"><b><u>Network Info</u></b></td>
         </tr>
         <tr>
           <td><b>Difficulty</b></td>
           <td id="b-diff" class="right">{$NETWORK_MM.difficulty|number_format:"8"}</td>
         </tr>
         <tr>
           <td><b>Est Next Difficulty</b></td>
           <td id="b-nextdiff" class="right">{$NETWORK_MM.EstNextDifficulty|number_format:"8"} (Change in {$NETWORK_MM.BlocksUntilDiffChange} Blocks)</td>
         </tr>
         <tr>
           <td><b>Est. Avg. Time per Block</b></td>
           <td id="b-esttimeperblock" class="right">{$NETWORK_MM.EstTimePerBlock|seconds_to_words}</td>
         </tr>
         <tr>
           <td><b>Current Block</b></td>
           <td id="b-nblock" class="right">{$NETWORK_MM.block}</td>
         </tr>
