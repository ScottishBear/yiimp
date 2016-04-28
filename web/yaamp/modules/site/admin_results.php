<?php

/////////////////////////////////////////////////////////////////////////////////////

echo <<<end
<style type="text/css">
tr.ssrow.filtered { display: none; }
th.status, td.status { min-width: 30px; max-width: 48px; }
td.status span { font-variant: small-caps; font-weight: bolder; }
.red { color: red; } .green { color: green; } .blue { color: blue; }
</style>
end;

showTableSorter('maintable', '{
tableClass: "dataGrid",
widgets: ["zebra","filter","Storage","saveSort"],
widgetOptions: {
	saveSort: true,
	filter_saveFilters: true,
	filter_external: ".search",
	filter_columnFilters: false,
	filter_childRows : true,
	filter_ignoreCase: true
}}');

echo <<<end
<thead>
<tr>
<th data-sorter="" width="30"></th>
<th data-sorter="text" width="30" class="status"></th>

<th data-sorter="text">Name</th>
<th data-sorter="text">Server</th>
<th data-sorter="currency" align="right">Diff/Height</th>
<th data-sorter="currency" align="right">Profit</th>
<th data-sorter="currency" align="right">Owed/BTC</th>
<th data-sorter="currency" align="right">Balance/Mint</th>
<th data-sorter="currency" align="right">Price</th>
<th data-sorter="currency" align="right">BTC</th>
<th data-sorter="currency" align="right">USD</th>
<th data-sorter="currency" align="right">Win/Market</th>

</tr>
</thead><tbody>
end;

$server = getparam('server');
if(!empty($server)) {
	$coins = getdbolist('db_coins', "(installed OR enable) AND rpchost=:server ORDER BY algo, index_avg DESC",
		array(':server'=>$server));
}
else
	$coins = getdbolist('db_coins', "(installed OR enable) ORDER BY algo, index_avg DESC");

$mining = getdbosql('db_mining');

foreach($coins as $coin)
{
	echo '<tr class="ssrow">';

	$lowsymbol = strtolower($coin->symbol);
	echo '<td><img src="'.$coin->image.'" width="24"></td>';

	$algo_color = getAlgoColors($coin->algo);
	echo '<td class="status" style="background-color: '.$algo_color.';">';

	if($coin->enable)
	{
		if($coin->auto_ready) echo '<span class="green" title="Auto enable">a</span>';
		else echo '<span class="red" title="Stratum disabled">d</span>';

		if($coin->visible) echo '<span title="Visible to public"> v</span>';
		else echo '<span title="Hidden"> h</span>';

		if($coin->auxpow) echo '<span title="AUX PoW"> x</span>';

		echo '<br/>';

		if($coin->rpccurl) echo '<span title="RPC with Curl">c</span>';
		else echo '&nbsp;';

		if($coin->rpcssl) echo '<span title="RPC over SSL"> s</span>';
		else echo ' &nbsp;';

		if(yaamp_watched_coin($coin->symbol))
			echo '<span title="Monitored (history)"> m</span>';

		if($coin->block_height < $coin->target_height) {
			$percent = round($coin->block_height*100/$coin->target_height, 2);
			echo '<br/><span style="font-size: .8em">'.$percent.'%</span>';
		}
	}

	echo "</td>";
	$version = formatWalletVersion($coin);
	if (!empty($coin->symbol2)) $version .= " ({$coin->symbol2})";

	echo "<td><b><a href='/site/coin?id=$coin->id'>$coin->name ($coin->symbol)</a></b>
		<br><span style='font-size: .8em'>$version</span></td>";

	echo "<td>$coin->rpchost:$coin->rpcport";
	if($coin->connections) echo " ($coin->connections)";
	echo "<br><span style='font-size: .8em'>$coin->rpcencoding <span style='background-color:$algo_color;'>&nbsp; ($coin->algo) &nbsp;</span></span></td>";

	$difficulty = Itoa2($coin->difficulty, 3);
	if ($difficulty > 1e20) $difficulty = '&nbsp;';

	if(!empty($coin->errors))
		echo '<td align="right" style="font-size: .9em;" class="red" title="'.$coin->errors.'"><b>'.$difficulty.'</b><br/>'.$coin->block_height.'</td>';
	else
		echo '<td align="right" style="font-size: .9em;"><b>'.$difficulty.'</b><br>'.$coin->block_height.'</td>';

// 	$network_ttf = $coin->network_ttf? sectoa($coin->network_ttf): '';
// 	$actual_ttf = $coin->actual_ttf? sectoa($coin->actual_ttf): '';
// 	$pool_ttf = $coin->pool_ttf? sectoa($coin->pool_ttf): '';
	$btcmhd = yaamp_profitability($coin);
	$btcmhd = mbitcoinvaluetoa($btcmhd);

	$h = $coin->block_height-100;
	$ss1 = dboscalar("SELECT count(*) FROM blocks WHERE coin_id={$coin->id} AND height>=$h AND category!='orphan'");
	$ss2 = dboscalar("SELECT count(*) FROM blocks WHERE coin_id={$coin->id} AND height>=$h AND category='orphan'");

	$percent_pool1 = $ss1? $ss1.'%': '';
	$percent_pool2 = $ss2? $ss2.'%': '';

// 	echo "<td align=right style='font-size: .9em'>$network_ttf<br>$actual_ttf</td>";
// 	echo "<td align=right style='font-size: .9em'>$pool_ttf<br></td>";

	if($ss1 > 50)
		echo '<td align="right" style="font-size: .9em;"><b>'.$btcmhd.'</b><br/><span class="blue">'.$percent_pool1.'</span>';
	else
		echo '<td align="right" style="font-size: .9em;"><b>'.$btcmhd.'</b><br/>'.$percent_pool1;

	echo '<span class="red"> '.$percent_pool2.'</span></td>';

	$owed = (double) dboscalar("SELECT sum(balance) FROM accounts WHERE coinid={$coin->id}");
	$owed_btc = bitcoinvaluetoa($owed*$coin->price);
	$owed_data = $owed ? bitcoinvaluetoa($owed).'<br/>'.bitcoinvaluetoa($owed_btc) : '';

	if($coin->balance+$coin->mint < $owed)
		echo '<td align="right" style="font-size: .9em;"><span class="red">'.$owed_data.'</span></td>';
	else
		echo '<td align="right" style="font-size: .9em;">'.$owed_data.'</td>';

	echo '<td align="right" style="font-size: .9em;">'.$coin->balance.'<br/>'.$coin->mint.'</td>';

	$price = bitcoinvaluetoa($coin->price);
	$price2 = bitcoinvaluetoa($coin->price2);
//	$marketcount = getdbocount('db_markets', "coinid=$coin->id");

	if($coin->dontsell && YAAMP_ALLOW_EXCHANGE)
		echo "<td align=right style='font-size: .9em; background-color: #ffaaaa'>$price<br>$price2</td>";
	else
		echo "<td align=right style='font-size: .9em'>$price<br>$price2</td>";

	$btc = bitcoinvaluetoa($coin->balance * $coin->price);
	$mint = bitcoinvaluetoa($coin->mint * $coin->price);
	echo '<td align="right" style="font-size: .9em;">'.$btc.'<br/>'.$mint.'</td>';

	$fiat = round($coin->balance * $coin->price * $mining->usdbtc, 2). ' $';
	$mint = round($coin->mint * $coin->price * $mining->usdbtc, 2). ' $';
	echo '<td align="right" style="font-size: .9em;">'.$fiat.'<br/>'.$mint.'</td>';

	$marketname = '';
	$bestmarket = getBestMarket($coin);
	if($bestmarket)	$marketname = $bestmarket->name;

	echo "<td align=right style='font-size: .9em'>$coin->reward<br>$marketname</td>";

	echo "</tr>";
}

$total = count($coins);
echo '</tbody>';

echo '<tr><th colspan="12">'.$total.' wallets</th></tr>';

echo '</table>';

//////////////////////////////////////////

echo "<br/>";













