<?php
/**
 * Takes a GEDCOM file and converts it to an XML file.
 *
 * @author      Michael Duncan
 * @copyright   (c) 2016
 */

ini_set('auto_detect_line_endings', true);

$gedcom = (isset($argv[1])) ? $argv[1] : '';
$path = (isset($argv[2])) ? $argv[2] : '';

$info = pathinfo($gedcom);

$xml = $path.$info['filename'].'.xml';

$time_start = microtime(true);

$handle = fopen($gedcom, 'r');

$node = 0;

if (!$handle) {

	echo 'Could not locate file.';
	die();

} else {

    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(true);
    $writer->startDocument('1.0', 'UTF-8');
    $writer->startElement("GEDCOM");

    $i = 0;
	while (!feof($handle)) {
        $i++;

		// utf8 encode the line/string for internationalization
		$line = utf8_encode(fgets($handle));

    	// get parent level
    	$parent_node = ($node) ? $node : 0;

    	// get level
    	$node = substr($line, 0, 1);
        
    	/**
     	 * Three ways to match
     	 *
         * 	2 PLAC McAlester OK     => '2', 'PLAC', 'McAlester OK'
         *	1 NOTE @NI01@           => '1', 'NOTE', 'NI01'
         * 	0 @NI01@ NOTE           => '0', 'NI01', 'NOTE'
    	 */

    	// 1 NOTE @NI01@
    	preg_match( '/\d{1,2} (\w+) (@\w*@)/', $line, $matches );

		if (count($matches) > 0) {
        	$tag        = $matches[1];
        	$attribute  = str_replace('@', '', $matches[2]);
			$text       = trim(substr($line, strlen($matches[0])));
    	} else {
        	// 0 @NI01@ NOTE
        	preg_match( '/\d{1,2} (@\w*@) (\w+)/', $line, $matches );

        	if (count($matches) > 0) {
        	    $tag        = $matches[2];
        	    $attribute  = str_replace('@', '', $matches[1]);
            	$text       = trim(substr($line, strlen($matches[0])));
        	} else {
           		// 2 PLAC Orlando FL
            	// This has to be last because it will match.
                preg_match( '/\d{1,2} (\w+)/', $line, $matches );

            	if (count($matches) > 0) {
            	    $tag        = $matches[1];
           			$attribute  = '';
            		$text       = trim(substr($line, strlen($matches[0])));
            	} else {

            	}
        	}
    	}

        // If the difference between the parent node and the child node
        // results in -1 then you have reached a new child node.
        // If the difference is 0 then end the current child node and
        // and start a new child node at the same level.
        // If the difference is greater than 0 then end the child node.

        $diff = $parent_node - $node;

        if ($tag != '') {

            if ($tag == 'HEAD') {
                // first tag head, so write tag and text

                $writer->startElement($tag);

            } else {

                if ($diff == '-1') {
                    // new child tag and write attribute and text

                    $writer->startElement($tag);

                    if($attribute != '') {
                        $writer->writeAttribute('id', $attribute);
                    }

                    $writer->text($text);

                } elseif ($diff == 0) {
                    // end the element and add same level child tag and write text

                    $writer->endElement();
                    $writer->startElement($tag);

                    if ($attribute != '') {
                        $writer->writeAttribute('id', $attribute);
                    }

                    $writer->text($text);

                } else {
                    // end the elements

                    $writer->endElement();
                    while ($diff > 0) {
                        $writer->endElement();
                        $diff--;
                    }

                    $writer->startElement($tag);

                    if ($attribute != '') {
                        $writer->writeAttribute('id', $attribute);
                    }

                    $writer->text($text);
                }
            }
        }

        $tag = '';
        $attribute = '';
        $text = '';
        $line = '';

        // Dump the xml from memory to the file and flush memory.        
        if (0 == $i % 1000) {
            file_put_contents($xml, $writer->flush(true), FILE_APPEND);
        }
	}

    $writer->endElement();
    $writer->endDocument();
    file_put_contents($xml, $writer->flush(true), FILE_APPEND);

	fclose($handle);
}

$time_end = microtime(true);
$time = $time_end - $time_start;
$size =  memory_get_peak_usage($real_usage=true);

echo "\n";
echo 'Parsed: ' . $gedcom;
echo "\n";
echo 'Created: ' . $xml;
echo "\n";
echo 'Memory Usage: '.$size;
echo "\n";
echo 'Executed in ' . $time . ' seconds';
echo "\n";
echo 'Done.';
echo "\n";

?>
