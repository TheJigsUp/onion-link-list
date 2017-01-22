<?php
/*
* Onion Link List - Setup
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

include('common_config.php');
if(!extension_loaded('pdo_mysql')){
	die($I['pdo_mysqlextrequired']);
}
if(!extension_loaded('pcre')){
	die($I['pcreextrequired']);
}
if(!extension_loaded('json')){
	die($I['jsonextrequired']);
}
if(!extension_loaded('curl')){
	die($I['curlextrequired']);
}
if(!extension_loaded('date')){
	die($I['dateextrequired']);
}
try{
	$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
}catch(PDOException $e){
	try{
		//Attempt to create database
		$db=new PDO('mysql:host=' . DBHOST . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		}else{
			die($I['nodb']);
		}
	}catch(PDOException $e){
		die($I['nodb']);
	}
}
if(!@$db->query('SELECT * FROM ' . PREFIX . 'settings LIMIT 1;')){
	//create tables
	$db->exec('CREATE TABLE ' . PREFIX . 'onions (id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, address varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL, md5sum binary(16) NOT NULL UNIQUE, lasttest int(10) UNSIGNED NOT NULL, lastup int(10) UNSIGNED NOT NULL, timediff int(10) UNSIGNED NOT NULL, timeadded int(10) UNSIGNED NOT NULL, description text CHARACTER SET utf8mb4 NOT NULL, category smallint(6) NOT NULL, locked smallint(6) NOT NULL, special int(10) UNSIGNED NOT NULL, INDEX(address), INDEX(lasttest), INDEX(timediff), INDEX(category), INDEX(special));');
	$db->exec('CREATE TABLE ' . PREFIX . 'phishing (onion_id int(10) UNSIGNED NOT NULL PRIMARY_KEY, original varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL, FOREIGN KEY (onion_id) REFERENCES onions(id) ON DELETE CASCADE ON UPDATE CASCADE);');
	$db->exec('CREATE TABLE ' . PREFIX . 'settings (setting varchar(50) NOT NULL PRIMARY KEY, value varchar(20000) NOT NULL);');
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('version', ?);");
	$stmt->execute([DBVERSION]);
	echo "$I[succdbcreate]\n";
}else{
	$res=$db->query('SELECT value FROM ' . PREFIX . "settings WHERE setting='version';");
	$version=$res->fetch(PDO::FETCH_NUM)[0];
	if($version<2){
		$olddb=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		$stmt=$olddb->query('SELECT onion_id, original  FROM ' . PREFIX . 'phishing;');
		$phishings=$stmt->fetchAll(PDO::FETCH_NUM);
		$stmt=$olddb->query('SELECT id, address, md5sum, lasttest, lastup, timediff, timeadded, description, category, locked, special FROM ' . PREFIX . 'onions;');
		$onions=$stmt->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'phishing;');
		$db->exec('DROP TABLE ' . PREFIX . 'onions;');
		$db->exec('CREATE TABLE ' . PREFIX . 'onions (id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, address varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL, md5sum binary(16) NOT NULL UNIQUE, lasttest int(10) UNSIGNED NOT NULL, lastup int(10) UNSIGNED NOT NULL, timediff int(10) UNSIGNED NOT NULL, timeadded int(10) UNSIGNED NOT NULL, description text CHARACTER SET utf8mb4 NOT NULL, category smallint(6) NOT NULL, locked smallint(6) NOT NULL, special int(10) UNSIGNED NOT NULL, INDEX(address), INDEX(lasttest), INDEX(timediff), INDEX(category), INDEX(special));');
		$db->exec('CREATE TABLE ' . PREFIX . 'phishing (onion_id int(10) UNSIGNED NOT NULL PRIMARY KEY, original varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL, FOREIGN KEY (onion_id) REFERENCES onions(id) ON DELETE CASCADE ON UPDATE CASCADE);');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'onions (id, address, md5sum, lasttest, lastup, timediff, timeadded, description, category, locked, special) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		foreach($onions as $onion){
			$stmt->execute($onion);
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'phishing (onion_id, original) VALUES (?, ?);');
		foreach($phishings as $phishing){
			$stmt->execute($phishing);
		}
	}
	$stmt=$db->prepare('UPDATE ' . PREFIX . "settings SET value=? WHERE setting='version';");
	$stmt->execute([DBVERSION]);
	echo "$I[statusok]\n";
}
?>
