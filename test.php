<?php
/*
* Onion Link List - Manual testing of hidden services
*
* Copyright (C) 2016 Daniel Winzen <d@winzen4.de>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
header('Content-Type: text/html; charset=UTF-8');
if($_SERVER['REQUEST_METHOD']==='HEAD'){
	exit; // headers sent, no further processing needed
}
include('common_config.php');
echo '<!DOCTYPE html><html><head>';
echo "<title>$I[testtitle]</title>";
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="author" content="Daniel Winzen">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<style type="text/css">.red{color:red;} .green{color:green;}</style>';
echo '</head><body>';
echo '<h1>Online-Test</h1>';
print_langs();
echo "<p>$I[testdesc]</p>";
echo "<form action=\"$_SERVER[SCRIPT_NAME]\" method=\"POST\">";
echo "<input type=\"hidden\" name=\"lang\" value=\"$language\">";
echo "<p>$I[link]: <br><input name=\"addr\" size=\"30\" value=\"";
if(isSet($_REQUEST['addr'])){
	echo htmlspecialchars($_REQUEST['addr']);
}else{
	echo "http://$_SERVER[HTTP_HOST]";
}
echo '" required></p>';
echo "<input type=\"submit\" name=\"action\" value=\"$I[test]\"></form><br>";
if(!empty($_REQUEST['addr'])){
	if(ob_get_level()>0){
		ob_end_flush();
	}
	try{
		$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
	}catch(PDOException $e){
		die('No DB connection');
	}
	if(!preg_match('~(^(https?://)?([a-z0-9]*\.)?([a-z2-7]{16}|[a-z2-7]{56})(\.onion(/.*)?)?$)~i', trim($_REQUEST['addr']), $addr)){
		echo "<p class=\"red\">$I[invalonion]</p>";
		echo "<p>$I[valid]: http://tt3j2x4k5ycaa5zt.onion</p>";
	}else{
		$ch=curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, USERAGENT);
		curl_setopt($ch, CURLOPT_PROXY, PROXY);
		curl_setopt($ch, CURLOPT_PROXYTYPE, 7);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_URL, "http://$addr[4].onion/");
		$addr=strtolower($addr[4]);
		$md5=md5($addr, true);
		//display warning, if a phishing clone was tested
		$phishing=$db->prepare('SELECT original FROM ' . PREFIX . 'phishing, ' . PREFIX . 'onions WHERE address=? AND onion_id=' . PREFIX . 'onions.id;');
		$phishing->execute([$addr]);
		if($orig=$phishing->fetch(PDO::FETCH_NUM)){
			printf("<p class=\"red\">$I[testphishing]</p>", "<a href=\"http://$orig[0].onion\">$orig[0].onion</a>");
		}
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'onions WHERE md5sum=? AND timediff=0 AND lasttest>?;');
		$stmt->execute([$md5, time()-60]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			echo "<p class=\"green\">$I[testonline]</p>";
		}elseif(curl_exec($ch)!==false){
			if(isSet($db)){
				//update entry in database
				$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'onions WHERE md5sum=?;');
				$stmt->execute([$md5]);
				if(!$stmt->fetch(PDO::FETCH_NUM)){
					$db->prepare('INSERT INTO ' . PREFIX . 'onions (address, md5sum, timeadded) VALUES (?, ?, ?);')->execute([$addr, $md5, time()]);
				}
				$db->prepare('UPDATE ' . PREFIX . 'onions SET lasttest=?, lastup=lasttest, timediff=0 WHERE md5sum=?;')->execute([time(), $md5]);
			}
			echo "<p class=\"green\">$I[testonline]</p>";
		}else{
			if(isSet($db)){
				$time=time();
				$db->prepare('UPDATE ' . PREFIX . 'onions SET lasttest=?, timediff=lasttest-lastup WHERE md5sum=? AND lasttest<?;')->execute([$time, $md5, $time]);
			}
			echo "<p class=\"red\">$I[testoffline]</p>";
		}
		curl_close($ch);
	}
}
echo '<br><p style="text-align:center;font-size:small;"><a target="_blank" href="https://github.com/DanWin/onion-link-list">Onion Link List - ' . VERSION . '</a></p>';
echo '</body></html>';
