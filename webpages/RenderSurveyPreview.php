<?php
// Copyright (c) 2020 Peter Olszowka. All rights reserved. See copyright document for more details.
// File created by Syd Weinstein on 2020-09-03

require_once('StaffCommonCode.php');

function render_question() {
    //error_log("\n------------------\nstart render_question:");
    // init xml structure for build
    $xml = new DomDocument("1.0", "UTF-8");
    $doc = $xml -> createElement("doc");
    $doc = $xml -> appendChild($doc);

    $questions = getParam("questions");
    //error_log("\n\nquestion:\n");
    //var_error_log($questions);
    //$questions = json_decode($questions);
    foreach ($questions as &$question) {
        // $jsonstring = base64_decode($question->data);
        // $json = json_decode($jsonstring);
        //var_error_log($json);
        $numberquery = "years";
        switch($question['typename']) {
            case "openend":
                $size = $question['max_value'];
                $question['size'] = $size > 100 ? 100 : ($size < 50 ? 50 : $size);
                break;
            case "text":
            case "html-text":
                $size = $question['max_value'] / 4;
                $question['size'] = $size > 100 ? 100 : ($size < 50 ? 50 : $size);
                $question['rows'] = $question['max_value'] > 500 ? 8 : 4;
                break;
            case "numberselect":
                $numberquery = "options";   // fall into monthyear
            case "monthyear":
                // build xml array from begin to end
                $options = [];
                $question_id = $question['questionid'];
                if ($question['ascending'] == 1) {
                    $next = $question['min_value'];
                    $end = $question['max_value'];
                    while ($next <= $end) {
                        $options['questionid'] = $question_id;
                        $options['value'] = $next;
                        $options['optionshort'] = $next;
                        $next = $next + 1;
                    }
                } else {
                    $next = $question['max_value'];
                    $end = $question['min_value'];
                    while ($next >= $end) {
                        $options['questionid'] = $question_id;
                        $options['value'] = $next;
                        $options['optionshort'] = $next;
                        $next = $next - 1;
                    }
                }
                //var_error_log($options);
                $xml = ArraytoXML($numberquery, $options, $xml);
                break;
        }
        //var_error_log($json);
        
    }
    $xml = ArraytoXML("questions", $questions, $xml);
    //error_log("\n\nxml after questions\n");
    //var_error_log($xml->saveXML());

    $options = getParam("options");
    if ($options) {
        //error_log("\n\noptions: '" . $options . "'\n");
        //$options = json_decode($options);
        //var_error_log($options);
        //foreach ($options as $opt) {
        //    $opt->value = base64_decode($opt->value);
        //    $opt->optionshort = base64_decode($opt->optionshort);
        //    $opt->optionhover = base64_decode($opt->optionhover);
        //}
        //var_error_log($options);
        $xml = ArrayToXML("options", $options, $xml);
    }
    //error_log("\n\nxml after options\n");
    //var_error_log($xml->saveXML());

    // Start of display portion
	$paramArray = array();
    $paramArray["size"] = 50;
    $paramArray["rows"] = 4;
    $paramArray["buttons"] = "";
    RenderXSLT('RenderSurvey.xsl', $paramArray, $xml);
}

// Start here.  Should be AJAX requests only
$ajax_request_action = getString("ajax_request_action");
if ($ajax_request_action == "") {
    exit();
}

switch ($ajax_request_action) {
    case "renderquestion":
        render_question();
        break;

    default:
        exit();
}
?>