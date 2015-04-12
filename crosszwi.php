<?php
require "configzwi.php";
require "Mail.php";
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
switch ($settings['Debug']) {
	case 1:
		error_reporting(E_ALL);
		break;
	case 2:
		error_reporting(E_ALL);
		break;
	case 3: 
		error_reporting(E_ALL & E_WARNING);
		break;
}

function debugReport() {
	global $settings;
	$count = 0;
	foreach (func_get_args() as $arg) {
		if ($count == 0) {
			$level = $arg;
		}
		if ($count == 1) {
			$key = $arg;
		}
		if ($count == 2) {
			$value = $arg;
		}
		$count++;
	}
	$backtrace = debug_backtrace();
	$last = next($backtrace);
	$last2 = next($backtrace);
	if ($settings['Debug'] <= $level) {
		$time = microtime(TRUE);
		if ($level == 0) {
			$report = $time." - ".$last['file'].": ".$last['line'].": Called ".$last['function'].": With Args:";
			echo $report;
			var_dump($last['args']);
		}
		else {
			$report = $time." - ".$last['function'].": ".$key.": ";
			echo $report;
			if ($settings['Debugdump']) {
				var_dump($value);
			}
			else {
				try {
					if (gettype($value) == "array") {
						throw new Exception('Array');
					}
					if (gettype($value) == "object") {
						throw new Exception('Object');
					}
					if (gettype($value) == "resource") {
						throw new Exception('Resource');
					}
					echo $value;
				}
				catch (Exception $e) {
					echo $e->getMessage();
				}
			}
		}
		echo "<BR>";
	}
}


$stempelungencross = readCrossFile($settings['file']);
parseStempelungen($stempelungencross);


function readCrossFile($file) {
	global $settings;
	//* Lese $file zeilenweise ein und gebe $stempelungen zurück.
	debugReport(0);
	$handle = fopen($file, 'r');
	if ($handle) {
		$x = 0;
		while(!feof($handle)) {
			$buffer = fgets($handle);
			if ($x != 0 && $buffer != NULL) {
				$split = explode(";",$buffer);
				$betrnr = $split[0];
				$betrnr = str_replace('"','',$betrnr);
				$datum = $split[1];
				$persnr = $split[2];
				$mechnr = $split[3];
				$mechnr = str_replace('"','',$mechnr);
				$gptnr = $split[4];
				$gptnr = str_replace('"','',$gptnr);
				$zeit = $split[5];
				$zeit = str_replace('"','',$zeit);
				$kommengeht = $split[6];
				$kommengeht = str_replace('"','',$kommengeht);
				$anwesend = $split[7];
				$anwesend = str_replace('"','',$anwesend);
				$beschreibung = $split[8];
				$beschreibung = str_replace('"','',$beschreibung);
				$stempelungen[$betrnr][$datum][$mechnr]['Anwesend'] = $anwesend;
				if ($kommengeht == "K") {
					$stempelungen[$betrnr][$datum][$mechnr]['Kommt'] = $zeit;
				}
				if ($kommengeht == "G") {
					$stempelungen[$betrnr][$datum][$mechnr]['Geht'] = $zeit;
				}
				$stempelungen[$betrnr][$datum][$mechnr]['Beschreibung'] = $beschreibung;
			}
			$x++;
		}
		return $stempelungen;
	}
	else {
		debugReport(3, "Error opening File",$file);
	}
}

function parseStempelungen($stempelungen) {
	debugReport(0);
	global $report;
	global $settings;
	$anwesend = 0;
	$nichtanwesend = 0;
	//* Die Hauptfunktion um $stempelungen in die ZWI Datenbank zu importieren
	foreach ($stempelungen as $key => $value) {
		$betrnr = $key;
		if ($betrnr == $settings['Betriebsnummer']) {
			foreach ($value as $key2 => $value2) {
				$datum = $key2;
				foreach ($value2 as $key3 => $value3) {
					$Mechnr = $key3;
					$Anwesend = $value3['Anwesend'];
					$Kommt = $value3['Kommt'];
					$Geht = $value3['Geht'];
					$Beschreibung = $value3['Beschreibung'];
					debugReport(1, "Found", $betrnr.";".$datum.";".$Mechnr.";".$Anwesend.";".$Kommt.";".$Geht.";".$Beschreibung);
					if ($Anwesend == "J") {
						$anwesend++;
						$report .= insertStempelung($Mechnr, $datum, $Kommt, $Geht, $Beschreibung);
					}
					else {
						$nichtanwesend++;
					}					
				}
			}
		}
	}
	if ($settings['email']['sendreport']) {
		$gesamt = $nichtanwesend+$anwesend;
		$report .= "\n Gefundene Stempelungen: ".$gesamt." Nicht Anwesend: ".$nichtanwesend." Anwesend: ".$anwesend."\n";
		sendReport($report);
	}
}
function sendReport($text) {
	debugReport(0);
	global $settings;
	$report = "\n Eingefuegt - FAKEY - PEKEY - UeberstundenVor - UeberstundenNach - PausenZeit - Kommt - Geht - Stempel Zeit in Minuten - VorAZBeginn - NachAZEnde - Korrekturbeginn - Korrekturende \n";
	$text = $report.$text;
	$smtp = Mail::factory('smtp',array('host' => $settings['email']['host'], 'auth' => $settings['email']['auth'], 'username' => $settings['email']['username'], 'password' => $settings['email']['password']));
	$mail = call_user_func($smtp->send($settings['email']['to'], $settings['email']['headers'], $text));
}


function getSqlDate($datum) {
	debugReport(0);
	//* Funktion um ein Dateobject in ein SQL Datum umzuwandeln
	$timestamp = date_timestamp_get($datum);
	$sqldatum = date('Y-m-d 00:00:00.000', $timestamp);
	debugReport(1, "returnvalue",$sqldatum);
	return $sqldatum;
}

function getArbeitsZeitZuordnung($perskey, $datum) {
	debugReport(0);
	global $settings;
	//* Arbeitszeitzuordnungen ermitteln
	//* Datum = dateobject!
	global $zwidat;
	$sqldatum = getSqlDate($datum);
	$abfrage = "SELECT TOP 1 DATVON, WOCHE1, WOCHE2, WOCHE3, WOCHE4, WOCHE5, WOCHE6, WOCHE7, WOCHE8, WOCHE9, WOCHE10, WOCHE11, WOCHE12, WOCHE13, WOCHE14, WOCHE15 FROM [zwidat].[dbo].[tblArbeitsZeitenZuordnung] WHERE PEKEY='".$perskey."' AND DATVON<convert(datetime,'".$sqldatum."', 121) ORDER BY DATVON DESC";
	debugReport(1, "SQLQuery",$abfrage);
	$result = sqlsrv_query($zwidat, $abfrage);
	$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
	$zuordnung['datvon'] = $row['DATVON'];
	$zuordnung[1] = $row['WOCHE1'];
	$zuordnung[2] = $row['WOCHE2'];
	$zuordnung[3] = $row['WOCHE3'];
	$zuordnung[4] = $row['WOCHE4'];
	$zuordnung[5] = $row['WOCHE5'];
	$zuordnung[6] = $row['WOCHE6'];
	$zuordnung[7] = $row['WOCHE7'];
	$zuordnung[8] = $row['WOCHE8'];
	$zuordnung[9] = $row['WOCHE9'];
	$zuordnung[10] = $row['WOCHE10'];
	$zuordnung[11] = $row['WOCHE11'];
	$zuordnung[12] = $row['WOCHE12'];
	$zuordnung[13] = $row['WOCHE13'];
	$zuordnung[14] = $row['WOCHE14'];
	$zuordnung[15] = $row['WOCHE15'];
	$x = 1;
	if ($row['WOCHE2'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE3'] != NULL) {
		$x = $x+1;
	}	
	if ($row['WOCHE4'] != NULL) {
		$x = $x+1;
	}	
	if ($row['WOCHE5'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE6'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE7'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE8'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE9'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE10'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE11'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE12'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE13'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE14'] != NULL) {
		$x = $x+1;
	}
	if ($row['WOCHE15'] != NULL) {
		$x = $x+1;
	}
	$zuordnung['count'] = $x;
	debugReport(1, "returnvalue",$zuordnung);
	return $zuordnung;
}

function getWochenModell($perskey, $datum) {
	debugReport(0);
	global $settings;
	//* Arbeitszeit eines Datums ermitteln
	//* $datum = dateobject!
	//* Ermittle die Zuordnungen die bei $datum gültig sind!
	$zuordnung = getArbeitsZeitZuordnung($perskey, $datum);
	//* Rechne wieviele Wochen seit dem Datum DATVON (Arbeitszeitzuordnung) vergangen sind
	$datvon = $zuordnung['datvon'];
	$datvonts = date_timestamp_get($datvon);
	$datheute = new DateTime();
	$datheutets = date_timestamp_get($datheute);
	//* Vergangene Sekunden 
	$sekunden = $datheutets-$datvonts;
	$wochen = $sekunden/(86400*7);
	$wochen = floor($wochen);
	$wochenzuordnung = ($wochen%$zuordnung['count'])+1;
	$wochenmodell = $zuordnung[$wochenzuordnung];
	debugReport(1, "Week discovered",$wochen);
	debugReport(1, "Wochenzuordnung",$wochenzuordnung);
	debugReport(1, "Wochenmodell ID ",$wochenmodell);
	//* Wochenmodell von Wochenzuordnung:
	debugReport(1, "returnvalue",$wochenmodell);
	return $wochenmodell;	
}

function getTagesModell($perskey, $datum) {
	debugReport(0);
	global $settings;
	global $zwidat;
	//* Lese das Wochenmodell aus der ZWI Datenbank aus:
	//* $datum = dateobject!
	$wochenmodell = getWochenModell($perskey, $datum);
	//* Hole das Tagesmodell von $datum!
	$abfrage = "SELECT ";
	
	$timestamp = date_timestamp_get($datum);
	$dayofweek = date('N', $timestamp);
	switch ($dayofweek) {
		case 1:
			$abfrage .= "ID_TAGMOD_MO";
			break;
		case 2:
			$abfrage .= "ID_TAGMOD_DI";
			break;
		case 3:
			$abfrage .= "ID_TAGMOD_MI";
			break;
		case 4:
			$abfrage .= "ID_TAGMOD_DO";
			break;
		case 5:
			$abfrage .= "ID_TAGMOD_FR";
			break;
		case 6:
			$abfrage .= "ID_TAGMOD_SA";
			break;			
		case 7:
			$abfrage .= "ID_TAGMOD_SO";
			break;
	}
	$abfrage .= " FROM [zwidat].[dbo].[tblWochenmodelle] WHERE WModKey='$wochenmodell'";
	debugReport(1, "SQLQuery",$abfrage);
	$result = sqlsrv_query($zwidat, $abfrage);
	$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC);
	$tagesmodell = $row[0];
	debugReport(1, "Returnvalue", $tagesmodell);
	return $tagesmodell;
}

function getArbeitsZeitID($tagesmodell) {
	debugReport(0);
	global $settings;
	global $zwidat;
	$abfrage = "SELECT ARBZEITKEY FROM [zwidat].[dbo].[tblTagesModelle] WHERE ID_TAGMOD='".$tagesmodell."'";
	debugReport(1, "SQLQuery",$abfrage);
	$result = sqlsrv_query($zwidat, $abfrage);
	$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
	$azid = $row['ARBZEITKEY'];
	debugReport(1, "Returnvalue",$azid);
	return $azid;
}
function getPausen($tagesmodell) {
	debugReport(0);
	global $settings;
	global $zwidat;
	if ($tagesmodell != NULL) {
		$abfrage = "SELECT PAUSZEITKEY FROM [zwidat].[dbo].[tblTagesModelle_Pausen] WHERE ID_TAGMOD='".$tagesmodell."'";
		debugReport(1, "SQLQuery",$abfrage);
		$result = sqlsrv_query($zwidat, $abfrage);
		$x = 0;
		while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
			$pausenzeit = getPausenZeit($row['PAUSZEITKEY']);
			$return[$x] = $pausenzeit;
			$return[$x]['id'] = $row['PAUSZEITKEY'];
			$x++;
		}
		$return['count'] = $x;
	}
	else {
		$return = FALSE;
	}
	debugReport(1, "Returnvalue",$return);
	return $return;
}
function getPausenZeit($pausenid) {
	debugReport(0);
	global $settings;
	global $zwidat;
	if ($pausenid != NULL) {
		$abfrage = "SELECT PZEITBEG, PZEITEND FROM [zwidat].[dbo].[tblPausenzeiten] WHERE PAUSZEITKEY='".$pausenid."'";
		debugReport(1, "SQLQuery",$abfrage);
		$result = sqlsrv_query($zwidat, $abfrage);
		$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
		if ($row != NULL) {
			$return['beginn']['string'] = date_format($row['PZEITBEG'], 'H:i');
			$return['beginn']['stunden'] = date_format($row['PZEITBEG'], 'H');
			$return['beginn']['minuten'] = date_format($row['PZEITBEG'], 'i');
			$return['beginn']['ind'] = ($return['beginn']['stunden']*100)+($return['beginn']['minuten']/60*100);
			$return['ende']['string'] = date_format($row['PZEITEND'], 'H:i');
			$return['ende']['stunden'] = date_format($row['PZEITEND'], 'H');
			$return['ende']['minuten'] = date_format($row['PZEITEND'], 'i');
			$return['ende']['ind'] = ($return['ende']['stunden']*100)+($return['ende']['minuten']/60*100);
			$return['dauer']['ind'] = $return['ende']['ind']-$return['beginn']['ind'];
			$return['dauer']['minuten'] = $return['dauer']['ind']/100*60;
		}
		else {
			$return = FALSE;
		}
	}
	else {
		$return = FALSE;
	}
	debugReport(1, "Returnvalue",$return);
	return $return;
}
	
	
function getArbeitsZeit($perskey, $datum) {
	debugReport(0);
	global $settings;
	global $zwidat;
	//* Hole das Tagesmodell von $datum
	//* $datum = dateobject!
	$tagesmodell = getTagesModell($perskey, $datum);
	if ($tagesmodell) {
		$arbeitszeitmodell = getArbeitsZeitID($tagesmodell);
		if ($arbeitszeitmodell) {
			$abfrage = "SELECT ARBZEITBEG, ARBZEITEND, ARBZEITBEZ, KorrekturZeitBeg, KorrekturZeitEnd FROM [zwidat].[dbo].[tblArbeitszeiten] WHERE ARBZEITKEY='".$arbeitszeitmodell."'";
			debugReport(1, "SQLQuery",$abfrage);
			$result = sqlsrv_query($zwidat, $abfrage);
			$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
			if ($row != NULL) {
				$return['bezeichnung'] = $row['ARBZEITBEZ'];
				
				if ($row['KorrekturZeitBeg'] != NULL) {
					$return['korrekturzeit']['beginn']['string'] = date_format($row['KorrekturZeitBeg'], 'H:i');
					$return['korrekturzeit']['beginn']['stunden'] = date_format($row['KorrekturZeitBeg'], 'H');
					$return['korrekturzeit']['beginn']['minuten'] = date_format($row['KorrekturZeitBeg'], 'i');
					$return['korrekturzeit']['beginn']['ind'] = ($return['korrekturzeit']['beginn']['stunden']*100)+($return['korrekturzeit']['beginn']['minuten']/60*100);
					$return['korrekturzeit']['beginn'] = TRUE;
				}
				else {
					$return['korrekturzeit']['beginn'] = FALSE;
				}
				
				if ($row['KorrekturZeitEnd'] != NULL) {
					$return['korrekturzeit']['ende']['string'] = date_format($row['KorrekturZeitEnd'], 'H:i');
					$return['korrekturzeit']['ende']['stunden'] = date_format($row['KorrekturZeitEnd'], 'H');
					$return['korrekturzeit']['ende']['minuten'] = date_format($row['KorrekturZeitEnd'], 'i');
					$return['korrekturzeit']['ende']['ind'] = ($return['korrekturzeit']['ende']['stunden']*100)+($return['korrekturzeit']['ende']['minuten']/60*100);
					$return['korrekturzeit']['ende'] = TRUE;
				}
				else {
					$return['korrekturzeit']['ende'] = FALSE;
				}
				
				$return['beginn']['string'] = date_format($row['ARBZEITBEG'], 'H:i');
				$return['beginn']['stunden'] = date_format($row['ARBZEITBEG'], 'H');
				$return['beginn']['minuten'] = date_format($row['ARBZEITBEG'], 'i');
				$return['beginn']['ind'] = ($return['beginn']['stunden']*100)+($return['beginn']['minuten']/60*100);
				
				$return['ende']['string'] = date_format($row['ARBZEITEND'], 'H:i');
				$return['ende']['stunden'] = date_format($row['ARBZEITEND'], 'H');
				$return['ende']['minuten'] = date_format($row['ARBZEITEND'], 'i');
				$return['ende']['ind'] = ($return['ende']['stunden']*100)+($return['ende']['minuten']/60*100);
				
				$return['pausen'] = getPausen($tagesmodell);
				
			}
			else {
				debugReport(2, "Kein Arbeitszeitmodell hinterlegt!","");
				$return = FALSE;
			}
		}
		else {
			$return = FALSE;
		}
	}
	else {
		$return = FALSE;
	}
	debugReport(1, "Returnvalue",$return);
	return $return;
}

function getFirstDateOfWeek($datum) {
	debugReport(0);
	global $settings;
	//* Funktion um den ersten Tag einer Woche in der sich $datum befindet zu ermitteln
	//* Wochen starten mit Montag!
	//* $datum = dateobject!
	$timestamp = date_timestamp_get($datum);
	$wochentag = date('N', $timestamp);
	//* Erster Tag der Woche 
	$timestamp = $timestamp-(($wochtag-1)*86400);
	$return = new DateTime("@$timestamp");
	debugReport(1, "Returnvalue",$return);
	return $return;
}

function insertStempelung($Mechnr, $datum, $kommt, $geht, $beschreibung) {
	debugReport(0);
	//* Stempelung hinzufügen
	global $settings;
	global $zwidat;
	$perskey = getPersKey($Mechnr);
	$beschreibung = "CROSS REACT - ".$beschreibung;
	
	$stempelzeit['kommt']['stunden'] = substr($kommt, 0, 2);;
	$stempelzeit['kommt']['minuten'] = substr($kommt, 3, 2);
	$stempelzeit['kommt']['ind'] = ($stempelzeit['kommt']['stunden']*100)+($stempelzeit['kommt']['minuten']/60*100);
	
	$stempelzeit['geht']['stunden'] = substr($geht, 0, 2);
	$stempelzeit['geht']['minuten'] = substr($geht, 3, 2);
	$stempelzeit['geht']['ind'] = ($stempelzeit['geht']['stunden']*100)+($stempelzeit['geht']['minuten']/60*100);
	
	$datumobj = date_create_from_format('d.m.Y', $datum);
	
	//* Hole das Arbeitszeitarray:
	$arbeitszeit = getArbeitsZeit($perskey, $datumobj);
	
	//* Prüfe ob Stempelung nach AZ Beginn aber innerhalb der Korrekturzeit:
	if ($arbeitszeit['korrekturzeit']['beginn']) {
		if($stempelzeit['kommt']['ind'] <= $arbeitszeit['korrekturzeit']['beginn']['ind'] && $stempelzeit['kommt']['ind'] >= $arbeitszeit['beginn']['ind']) {
			$korrektur['beginn']['true'] = TRUE;
		}
		else {
			$korrektur['beginn']['true'] = FALSE;
		}
	}
	else {
		$korrektur['beginn']['true'] = FALSE;
	}	

	//* Prüfe ob Stempelung nach AZ Ende aber innerhalb der Korrekturzeit:
	if ($arbeitszeit['korrekturzeit']['ende']) {
		if($stempelzeit['geht']['ind'] <= $arbeitszeit['korrekturzeit']['ende']['ind'] && $stempelzeit['geht']['ind'] >= $arbeitszeit['ende']['ind']) {
			$korrektur['ende']['true'] = TRUE;
		}
		else {
			$korrektur['ende']['true'] = FALSE;
		}	
	}
	else {
			$korrektur['ende']['true'] = FALSE;
	}	
	
	//* Prüfe ob kommt vor dem Arbeitszeitbeinn liegt:
	if (($stempelzeit['kommt']['ind'] < $arbeitszeit['beginn']['ind']) && $arbeitszeit) {
		$vorazbeginn = TRUE;
	}
	else {
		$vorazbeginn = FALSE;
	}
	//* Prüfe ob geht nach dem Arbeitszeitende liegt:
	if (($stempelzeit['geht']['ind'] > $arbeitszeit['ende']['ind']) && $arbeitszeit) {
		$nachazende = TRUE;
	}
	else {
		$nachazende = FALSE;
	}
	
	//* Hole ZeitRechnenVorArbBeg und ZeitRechnenNachArbEnde
	$zeitrechnen = getZAZuordnung($perskey, 1);
	
	//* Wenn vor AZ Beginn oder innerhalb Korrekturzeit und ZeitRechnenVorArbBeg (oder ArbEnde) = 1, dann nehme AZ beginn zum berechnen der $stempelzeitinminuten 
	$calc['beginn'] = 0;
	$calc['ende'] = 0;
	if (($vorazbeginn || $korrektur['beginn']['true']) && $zeitrechnen['ZeitRechnenVorArbBeg'] != 1) {
		$calc['beginn'] = $arbeitszeit['beginn']['ind'];
	}
	else {
		$calc['beginn'] = $stempelzeit['kommt']['ind'];
	}
	
	//* Same with AZ Ende
	if (($nachazende || $korrektur['ende']['true']) && $zeitrechnen['ZeitRechnenNachArbEnde'] != 1) {
		$calc['ende'] = $arbeitszeit['ende']['ind'];
	}
	else {
		$calc['ende'] = $stempelzeit['geht']['ind'];
	}	
	
	$pause['ind'] = 0;
	$pause['minuten'] = 0;
	//* Berechne die Pausenzeit:
	if ($arbeitszeit['pausen']) {
		foreach ($arbeitszeit['pausen'] as $key=>$value) {
			if ($value['beginn']['ind'] >= $stempelzeit['kommt']['ind'] && $value['ende']['ind'] <= $stempelzeit['geht']['ind']) {
				$pause['ind'] = $pause['ind'] + $value['dauer']['ind'];
			}	
		}
		$pause['minuten'] = $pause['ind']/100*60;
	}

	
	$stempelzeitinminuten = (($calc['ende']-$calc['beginn']-$pause['ind'])/100*60);
	$gebrauchtezeitinminuten = $stempelzeitinminuten;
	
	debugReport(2,"Verarbeitete Stempelung","");
	debugReport(2,"Mechnr",$Mechnr);
	debugReport(2,"Beginn",$kommt);
	debugReport(2,"Ende",$geht);
	debugReport(2,"Stempelzeitinminuten",$stempelzeitinminuten);
	debugReport(2,"Stempelzeit ind",round($stempelzeitinminuten/60*100));
	debugReport(2,"Arbeitszeitbezeichnung",$arbeitszeit['bezeichnung']);
	debugReport(2,"Arbeitszeit Beginn",$arbeitszeit['beginn']['ind']);
	debugReport(2,"Arbeitszeit Ende",$arbeitszeit['ende']['ind']);
	debugReport(2,"Vorazbeginn",$vorazbeginn);
	debugReport(2,"Nachazende",$nachazende);
	debugReport(2,"Innerhalb Korrekturzeit Beginn",$korrektur['beginn']['true']);
	debugReport(2,"Innerhalb Korrekturzeit Ende",$korrektur['ende']['true']);
	debugReport(2,"Pause Minuten",$pause['minuten']);
	debugReport(2,"","<BR>");
	
	
	$stempeldat = date_timestamp_get($datumobj);
	$ZWI['FAKEY'] = $settings['zwi']['fakey'];
	$ZWI['Aktivstatus'] = 'A';
	$ZWI['LfdNr'] = 999;
	$ZWI['AbstempelZeit24h'] = 0;	
	$ZWI['StempDat'] = date('Y-m-d 00:00:00.000', $stempeldat);
	$ZWI['AnstempelZeit'] = "1899-12-30 ".$stempelzeit['kommt']['stunden'].":".$stempelzeit['kommt']['minuten'].":00.000";
	$ZWI['AbstempelZeit'] = "1899-12-30 ".$stempelzeit['geht']['stunden'].":".$stempelzeit['geht']['minuten'].":00.000";
	$ZWI['AeDatum'] = $ZWI['StempDat'];
	$ZWI['AeZeit'] = $ZWI['AbstempelZeit'];
	$ZWI['AePCName'] = "DE484450REACT/react";
	$ZWI['AePCUser'] = "react";
	$ZWI['AePGMName'] = "react";
	$ZWI['PEKEY'] = $perskey;
	$ZWI['Zeitart_ID'] = 1;
	$ZWI['UeberstundenVor'] = $zeitrechnen['ZeitRechnenVorArbBeg'];
	$ZWI['UeberstundenNach'] = $zeitrechnen['ZeitRechnenNachArbEnde'];
	$ZWI['PausenZeit'] = $pause['minuten'];
	$ZWI['PauseEditManuell'] = 0;
	$ZWI['StempelZeitInMinuten'] = $stempelzeitinminuten;
	$ZWI['GebrauchteZeitInMinuten'] = $gebrauchtezeitinminuten;
	$ZWI['BDEGebrauchteZeit'] = 0;
	$ZWI['BDEPausenZeit'] = 0;
	$ZWI['AnlastungMal100'] = 10000;
	$ZWI['SollLeistungsgradMal100'] = 10000;
	$ZWI['Bemerkung'] = $beschreibung;
	$ZWI['NeuanlageAeDatum'] = date('Y-m-d 00:00:00.000');
	$ZWI['NeuanlageAeZeit'] = date('1899-12-30 H:i:s.000');
	$ZWI['NeuanlageAePCName'] = "DE484450REACT/react";
	$ZWI['NeuanlageAePCUser'] = "REACT";
	$ZWI['NeuanlageAePGMName'] = "REACT";
	
	$abfrage = "INSERT INTO [zwidat].[dbo].[tblStempel] (FAKEY,Aktivstatus,LfdNr,StempDat,AnstempelZeit,AbstempelZeit,AbstempelZeit24h,AeDatum,AeZeit,AePCName,AePCUser,AePGMName,PEKEY,Zeitart_ID,UeberstundenVor,UeberstundenNach,PausenZeit,PauseEditManuell,StempelZeitInMinuten,GebrauchteZeitInMinuten,BDEGebrauchteZeit,BDEPausenZeit,AnlastungMal100,SollLeistungsgradMal100,Bemerkung,NeuanlageAeDatum,NeuanlageAeZeit,NeuanlageAePCName,NeuanlageAePCUser,NeuanlageAePGMName)
							VALUES ('".$ZWI['FAKEY']."','".$ZWI['Aktivstatus']."','".$ZWI['LfdNr']."',convert(datetime,'".$ZWI['StempDat']."',121),convert(datetime,'".$ZWI['AnstempelZeit']."',121),convert(datetime,'".$ZWI['AbstempelZeit']."',121),'".$ZWI['AbstempelZeit24h']."',convert(datetime,'".$ZWI['AeDatum']."',121),convert(datetime,'".$ZWI['AeZeit']."',121),'".$ZWI['AePCName']."','".$ZWI['AePCUser']."','".$ZWI['AePGMName']."','".$ZWI['PEKEY']."','".$ZWI['Zeitart_ID']."','".$ZWI['UeberstundenVor']."','".$ZWI['UeberstundenNach']."','".$ZWI['PausenZeit']."','".$ZWI['PauseEditManuell']."','".$ZWI['StempelZeitInMinuten']."','".$ZWI['GebrauchteZeitInMinuten']."','".$ZWI['BDEGebrauchteZeit']."','".$ZWI['BDEPausenZeit']."','".$ZWI['AnlastungMal100']."','".$ZWI['SollLeistungsgradMal100']."','".$ZWI['Bemerkung']."',convert(datetime,'".$ZWI['NeuanlageAeDatum']."',121),convert(datetime,'".$ZWI['NeuanlageAeZeit']."',121),'".$ZWI['NeuanlageAePCName']."','".$ZWI['NeuanlageAePCUser']."','".$ZWI['NeuanlageAePGMName']."')";
	//* Führe die Abfrage nur aus wenn die Simulation nicht angeschaltet ist:
	if ($settings['Simulation'] != 1) {
		$result = sqlsrv_query($zwidat, $abfrage);
	}
	else {
		$result = FALSE;
	}
	
	if (!$result && $settings['Simulation'] != 1) {
		$true = FALSE;
	}
	elseif ($result && $settings['Simulation'] != 1) {
		$true = TRUE;
	}
	else {
		$true = "SIM!";
	}
	debugReport(1, "SQLQuery",$abfrage);
	$Mechnrprot = $Mechnr;
	while (strlen($Mechnrprot) < 5) {
		$Mechnrprot = $Mechnrprot." ";
	}
	$protokoll =  " \n ".$true." - ".$ZWI['FAKEY']." - ".$Mechnrprot." - ".$ZWI['UeberstundenVor']." - ".$ZWI['UeberstundenNach']." - ".$ZWI['PausenZeit']." - ".$stempelzeit['kommt']['stunden'].":".$stempelzeit['kommt']['minuten']." - ".$stempelzeit['geht']['stunden'].":".$stempelzeit['geht']['minuten']." - ".$stempelzeitinminuten." - ".$vorazbeginn." - ".$nachazende." - ".$korrektur['beginn']['true']." - ".$korrektur['ende']['true']."\n";
	
	return $protokoll;
}

function getPersKey($persnr) {
	debugReport(0);
	global $settings;
	//* Hole die Personalid von Personalnummer $perskey aus der ZWI-Datenbank 
	global $zwidat;
	$abfrage = "SELECT PEKEY FROM [zwidat].[dbo].[tblPersonalStamm] WHERE [PNR]='$persnr'";
	debugReport(1, "SQLQuery",$abfrage);
	$result = sqlsrv_query($zwidat, $abfrage);
	$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
	$perskey = $row['PEKEY'];
	debugReport(1, "returnvalue",$perskey);
	return $perskey;
}

function getZAZuordnung($perskey, $zeitartid) {
	debugReport(0);
	global $settings;
	global $zwidat;
	$abfrage = "SELECT ZeitRechnenVorArbBeg, ZeitRechnenNachArbEnde FROM [zwidat].[dbo].[tblPersonal_ZAZuordnung] WHERE Zeitart_ID='$zeitartid' AND PEKEY='$perskey'";
	debugReport(1, "SQLQuery",$abfrage);
	$result = sqlsrv_query($zwidat, $abfrage);
	$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
	debugReport(1, "returnvalue",$row);
	return $row;
}

?>