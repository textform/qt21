<?php 

function determine_class($input){
	if (substr($input,-5) == '-Gold') { return substr($input,0,-5); }
	else { return $input; }
}

function determine_issue($input){
	if ($input == 'Extraneous') { return 'Extraneous function word'; }
	else if ($input == 'Incorrect') { return 'Incorrect function word'; }
	else if ($input == 'Missing') { return 'Missing function word'; }
	else { return $input; }
}

function remove_mqmtags($anno)
{
    if (strpos($anno,'<mqm') === FALSE){
		return $anno;
	} else {
		$tagstartpos = strpos($anno,'<mqm');
		$tagendpos = strpos($anno,'/>') + 2;
		$head = "" . substr($anno,0,$tagstartpos);
		$tail = remove_mqmtags(substr($anno,$tagendpos));
		return $head . $tail;
	}
}

function remove_other_issues($anno)
{
    if (strpos($anno,'<') === FALSE && strpos($anno,'&lt;') === FALSE){
		return $anno;
	} else {
		$tagstartpos = strpos($anno,'<');
		$tagendpos = strpos($anno,'/>') + 2;
		$head = "" . substr($anno,0,$tagstartpos);
		$tail = remove_other_issues(substr($anno,$tagendpos));
		return $head . $tail;
	}
}

$uploaddir = '../uploads/';
$uploadcsvfile = $uploaddir . basename($_FILES['csvfile']['name']);

echo '<pre>';

if (move_uploaded_file($_FILES['csvfile']['tmp_name'], $uploadcsvfile)) {
    echo "The CSV file you provided is valid and has been uploaded.\n";
} else {
    echo "No valid CSV file received.\n";
}

$problematic_string = file_get_contents($uploadcsvfile);
$newstring = str_replace('->','-&gt;',str_replace('< -','&lt; -',str_replace('<-','&lt;-',str_replace('""',"'",str_replace('""",','\'",',str_replace(',"""',',"\'',$problematic_string))))));
file_put_contents($uploadcsvfile, $newstring);

$rows = 0;

if (($handle = fopen($uploadcsvfile, "r")) !== FALSE) {
    while (($csv = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
        $num = count($csv);
//      echo "<p> $num Felder in Zeile $row: <br /></p>\n";
        for ($c=0; $c < $num; $c++) {
			$newdata[$rows][$c] = $csv[$c];
//	        echo $csv[$c] . "<br />\n";
        }
        $rows++;
    }
    fclose($handle);
}

$uploadhtmlfile = $uploaddir . basename($_FILES['htmlfile']['name']);

echo '<pre>';
if (!move_uploaded_file($_FILES['htmlfile']['tmp_name'], $uploadhtmlfile)) {

    echo "No valid HTML file has been received. Hence, a new HTML file was created. \n\n";
	$xhtml = new DOMDocument();
	$xhtml->loadXML('<html><body><table></table></body></html>');
	$doc = 'empty';
	
} else {

	echo "The QT21 HTML file you provided has been uploaded and used to allocate any new annotations. \n\n";
	$xhtml = new DOMDocument();
	$xhtml->loadHTMLFile($uploadhtmlfile);
	$doc = "not empty";

}

$xhtml->formatOutput = true;
$table = $xhtml->getElementsByTagName('body')->item(0)->getElementsByTagName('table')->item(0);

$xpath = new DOMXPath($xhtml);

for($i=0; $i < $rows-1; $i++)
{
	
//	echo $i . "<br/>";
	$author = $newdata[$i+1][0];
	$idtobe = str_replace('/','_',$author);
	
	$find_id = "count(//tbody[@id ='". $idtobe ."'])";
	$found_ids = $xpath->evaluate($find_id,$xhtml);

	if($found_ids == 0){
	
//	echo $found_ids . " previous annotations of the same sentence found.\n";
//	echo "The HTML doc you are inserting data into is ".$doc.".\n";
	
	$currentnode = $table->appendChild(new DOMElement('tbody'));
	
	$currentnode->setAttribute('id', $idtobe);
	$classtobe = '('. determine_class(substr($author,strrpos($author,'_') + 1)) . ')';
	$currentnode->setAttribute('class',$classtobe);
	
	$currentnode->appendChild(new DOMElement('tr'));
	$sourcelist = $currentnode->getElementsByTagName('tr');
	$newsource = $sourcelist->item(0);
	$newsource->setAttribute('class','source');
	$newsource->appendChild(new DOMElement('td'));
	$scolumnlist = $newsource->getElementsByTagName('td');
	$newcolumn = $scolumnlist->item(0);
	$newcolumn->setAttribute('colspan',7);
	$newcolumn->appendChild(new DOMElement('p',"[" . substr($author,0,strrpos($author,'-')) . "]"));
	$newcolumn->appendChild(new DOMElement('p',str_replace('&','&amp;',$newdata[$i+1][1])));
	$paralist = $newcolumn->getElementsByTagName('p');
	$paralist->item(0)->setAttribute('class','segment-id');
	$paralist->item(1)->setAttribute('class','source-text');
	
	$newtarget = $currentnode->appendChild(new DOMElement('tr'));
	$newtarget->setAttribute('class','target');
	$newtarget->appendChild(new DOMElement('td', $i+1));
	$newtarget->appendChild(new DOMElement('td',$classtobe));
	$newtarget->appendChild(new DOMElement('td',remove_mqmtags(str_replace('&','&amp;',$newdata[$i+1][2]))));
	
	$tcolumnlist = $newtarget->getElementsByTagName('td');
	$tcolumnlist->item(0)->setAttribute('class','row-number');
	$tcolumnlist->item(1)->setAttribute('class','type');
	$tcolumnlist->item(2)->setAttribute('class','target-text');

	}

	$anno = new DOMDocument();
	$orig = str_replace('mqm:','',str_replace('&','&amp;',$newdata[$i+1][2]));
	$anno->loadXML("<td>" . $orig . "</td>");
	
	$startissuetag_list = $anno->getElementsByTagName('startIssue');
	$endissuetag_list = $anno->getElementsByTagName('endIssue');
	
	$issues = new DOMDocument();
	$issues->loadXML('<ul></ul>');
	$issue_start = 0;
	
//	echo $issue_num . "\n";
	
	while($startissuetag_list->item(0))
	{
		$oldstartnode = $startissuetag_list->item(0);
		$type = $oldstartnode->getAttribute('type');
		$id = $oldstartnode->getAttribute('id');
		
		$issue_start = strpos($orig,'/>',strpos($orig,$type,$issue_start)) + 2;
//		echo $issue_start . "\n";
		$issue_end = strpos($orig,'<endIssue',$issue_start);
		$issue_length = $issue_end - $issue_start;
		$issue_obj = remove_other_issues(substr($orig,$issue_start,$issue_length));
		
		$newissue = $issues->documentElement->appendChild(new DOMElement('li'));
		$newissue->setAttribute('id','d'.$id);
		$newissue->appendChild(new DOMElement('span',determine_issue($type)));
		$newissue->firstChild->setAttribute('class','issuetag');
		$issue_obj_text = new DOMText("  [".trim($issue_obj)."]");
		$newissue->appendChild($issue_obj_text);
		
		$newstartnode = $anno->documentElement->appendChild(new DOMElement('span','&lt;'.determine_issue($type).'&gt;'));
		
		$oldendnode = $endissuetag_list->item(0);
		$newendnode = $anno->documentElement->appendChild(new DOMElement('span','&lt;/'.determine_issue($type).'&gt;'));
		
		$anno->documentElement->replaceChild($newstartnode,$oldstartnode);
		$anno->documentElement->replaceChild($newendnode,$oldendnode);
		
		$newstartnode->setAttribute('class','issuetag');
		$newstartnode->setAttribute('id','issue-'.$id);
		$newendnode->setAttribute('class','issuetag');
	}
	$import = $anno->documentElement;
	
	$issue_num = $import->getElementsByTagName('span')->length / 2;
	$anno_pos = 3;	
	
	if($found_ids == 0){
	
	$newtarget->appendChild(new DOMElement('td', $issue_num . "-". $issue_num));
	$tcolumnlist->item(3)->setAttribute('class','class');
	$currentnode->setAttribute('data-min',$issue_num);
	$currentnode->setAttribute('data-max',$issue_num);
	
	} else {
	
	$get_matching_node = "//tbody[@id='". $idtobe ."']";
	$currentnode = $xpath->evaluate($get_matching_node,$xhtml)->item(0);
	
	$count_annos = "count(tr[@class='annotated-row'])";
	$num_annos = $xpath->evaluate($count_annos,$currentnode);
	
	while($anno_pos <= $num_annos){
		$name = $currentnode->getElementsByTagName('tr')->item($anno_pos)->getElementsByTagName('td')->item(1)->nodeValue;		
		if(strnatcmp($_POST['annotator'],$name) < 0){ break; }
		$anno_pos++;
	}
	
	$get_datamin = "@data-min";
	$olddatamin = $xpath->evaluate($get_datamin,$currentnode)->item(0)->nodeValue;
	$newdatamin = min($olddatamin,$issue_num);
	$currentnode->setAttribute('data-min',$newdatamin);
	$get_datamax = "@data-max";
	$olddatamax = $xpath->evaluate($get_datamax,$currentnode)->item(0)->nodeValue;
	$newdatamax = max($olddatamax,$issue_num);
	$currentnode->setAttribute('data-max',$newdatamax);
	
	$get_target = "tr[@class='target']/td[@class='class']";
	$targetnode = $xpath->evaluate($get_target,$currentnode)->item(0);
	$newtargetclass = $targetnode->parentNode->appendChild(new DOMElement('td',$newdatamin.'-'.$newdatamax));
	$newtargetclass->setAttribute('class','class');
	$targetnode->parentNode->replaceChild($newtargetclass,$targetnode);
	}
	
	$check_for_same_annotator = "count(//tbody[@id='".$idtobe."']/tr[td/@class='annotator' and td='".$_POST['annotator']."'])";
	
	if ($xpath->evaluate($check_for_same_annotator,$xhtml) == 0){
	
//	$newanno = $currentnode->appendChild(new DOMElement('tr'));
	$newanno = $currentnode->insertBefore(new DOMElement('tr'),$currentnode->getElementsByTagName('tr')->item($anno_pos));
	$newanno->setAttribute('class','annotated-row');
	$newanno->setAttribute('data-annothisrow',$issue_num);
	$newanno->appendChild(new DOMElement('td'));
	$newanno->appendChild(new DOMElement('td',$_POST['annotator']));
	$newanno->appendChild($xhtml->importNode($anno->documentElement,true));
	$newanno->appendChild(new DOMElement('td',$issue_num));
	$newanno->appendChild(new DOMElement('td'));
	$acolumnlist = $newanno->getElementsByTagName('td');
	$acolumnlist->item(0)->setAttribute('colspan','2');
	$acolumnlist->item(1)->setAttribute('class','annotator');
	$acolumnlist->item(2)->setAttribute('class','annotated');
	$acolumnlist->item(3)->setAttribute('class','num-annotations');
	$acolumnlist->item(4)->setAttribute('class','annotations');
	$acolumnlist->item(4)->setAttribute('colspan',2);	
	$acolumnlist->item(4)->appendChild($xhtml->importNode($issues->documentElement,true));

	if($found_ids != 0){
	
	$find_space = "tr[@class='space']";
	$oldspace = $xpath->evaluate($find_space,$currentnode)->item(0);
	$oldspace->parentNode->removeChild($oldspace);
	
	}

	$newspace = $currentnode->appendChild(new DOMElement('tr'));
	$newspace->setAttribute('class','space');
	$newspace->appendChild(new DOMElement('td'));
	$newspace->getElementsByTagName('td')->item(0)->setAttribute('colspan',7);

	}
}

echo 'Wrote: ' . $xhtml->saveHTMLFile($_POST['targetname'].".html") . ' bytes <br/><br/>The target directory is the directory containing this php file (uploaded.php).'; // Wrote: 129 bytes

print "</pre>";

?>
