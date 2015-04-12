<?php
//* ZWI Datenbank Parameter:
$settings['zwi']['servername'] = "DE484450SZWI\ZWIDAT"; //serverName\instanceName
$settings['zwi']['user'] = "react";
$settings['zwi']['database'] = "zwidat";
$settings['zwi']['password'] = "haching34";
$settings['zwi']['fakey'] = 1;

//* Einstellungen:
$settings['Betriebsnummer'] = "48445";
$settings['Debug'] = 2; //* Debug Levels: 0: With Backtrace, 1: Debug, 2: Notice, 3: Error, 4: Nothing
$settings['Debugdump'] = FALSE; //* Dump Vars instead of just displaying them
$settings['Simulation'] = 1;


//* Email Versand Parameter:
$settings['email']['sendreport'] = FALSE;
$settings['email']['from'] = "React Reporting <e-d-v@ac-berner.de>";
$settings['email']['to'] = "edv@ac-berner.de";
$settings['email']['subject'] = "Import der CROSS Stempelungen";
$settings['email']['host'] = "192.168.99.100";
$settings['email']['auth'] = TRUE;
$settings['email']['username'] = "e-d-v@ac-berner.de";
$settings['email']['password'] = "edv";

//* Datei Zum Aulesen:
$settings['file'] = join(DIRECTORY_SEPARATOR, array("K:","reports","devwd_zeitstempelungen.csv"));

//* Don't touch Things below this Line!
$settings['email']['headers'] = array('From' => $settings['email']['from'], 'To' => $settings['email']['to'], 'Subject' => $settings['email']['subject']);

$connectionInfo = array( "Database"=>$settings['zwi']['database'], "UID"=>$settings['zwi']['user'], "PWD"=>$settings['zwi']['password']);
$zwidat = sqlsrv_connect( $settings['zwi']['servername'], $connectionInfo);
if($zwidat) {
	if ($settings['Debug'] == 1) {
		echo "Connection established.<br />";
	}
}
else {
     echo "Fehler mit der ZWI Datenbank Anbindung!<br />";
     die( print_r( sqlsrv_errors(), true));
}







?>