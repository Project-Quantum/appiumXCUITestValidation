<?php

require 'helpers.php';
require 'appiumDeprecated.php';
require 'perfectoAppium.php';
require 'inProgress.php';

$src = $argv[1];

/* *** CUSTOMIZE *** */
$searchRegex = "\/\/";

$urlValidate = 'https://xpathvalidator.herokuapp.com/validateNew';
$urlValidateTarget = 'https://xpathvalidator.herokuapp.com/?bdata=';

$outPutFN = "./results/" . hash('md5', time()) . "_xPathValidation.html";
if (!is_dir('./results')) {
    mkdir('./results');
}

$outPut = fopen($outPutFN, "w") or die("Unable to open file!");
$htmlBegin = htmlBegin();

fwrite($outPut, $htmlBegin);

chdir ($src) or die("Unable to cd to $src!");
$htmlOut = "";

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
$files = array(); 
foreach ($rii as $file) {
    if ($file->isDir()){ 
        continue;
    }
    $files[] = $file->getPathname(); 
}
foreach ($files as $filename) {
  $stripFN =  str_replace($src, "./", $filename); 
  $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
  $htmlOut = "";
  $appDepHTML = "";
  $perfectoAppiumHTML = "";
  $inProgressHTML = "";
  $xpathPrint = 0;
  $appiumDeprecationPrint = 0;
  $perfectoAppiumPrint = 0;
  $inProgressPrint = 0;
  $lineNum = 0;
  $objFilePath = $filename;
  $objFile = fopen($objFilePath, "r") or die("Unable to open file!");
    while (($line = fgets($objFile)) !== false) {
       $lineNum++;
       if(preg_match("/$searchRegex/i", $line)) {
          // strip whitespace off of beginning of line
          $line = trim($line);
          // skip if begin with (# or //) or contains /// or look for  //skipXpath to intentionally skip
          if(preg_match("/^#/", $line) || preg_match("/^\/\//", $line) || preg_match("/\/\/skipXpath/", $line) || preg_match("/\/\/\//", $line)) {
             continue;
          }
          // if using regex to search for "//" - skip if matched http:// or https://
          if(preg_match("/http:\/\//", $line) || preg_match("/https:\/\//", $line)) {
             continue;
          }
          $xPathSplit = preg_split("/$searchRegex/i", $line);
          $xPathSplit[1] = strstr($xPathSplit[1], '.click', true) ?: $xPathSplit[1];
          $xPathSplit[1] = strstr($xPathSplit[1], '.sendKeys', true) ?: $xPathSplit[1];
          $xPathSplit[1] = strstr($xPathSplit[1], '//', false) ?: $xPathSplit[1];
          $xPathSplit[1] = strstr($xPathSplit[1], '}', true) ?: $xPathSplit[1];
          $xPathSplit[1] = strstr($xPathSplit[1], ')"))', true) ?: $xPathSplit[1];
          $xPathSplit[1] = strstr($xPathSplit[1], '"))', true) ?: $xPathSplit[1];
          $xPathSplit[1] = strstr($xPathSplit[1], '");', true) ?: $xPathSplit[1];   
          $xPathSplit[1] = strstr($xPathSplit[1], ',\"desc\"', true) ?: $xPathSplit[1];
          if (strcmp($searchRegex, "\/\/") == 0){
             // if using regex to search for "//", add back to the beginning of the string
             $xPathSplit[1] = "//" . $xPathSplit[1];
             // Noise reduction - try to eliminate processing comments at the end of lines using // in Java, JS, PHP
             if(preg_match('(java|js|php)', $fileExt) === 1) { 
                if(preg_match('(\"\(\/\/|\'\(\/\/|\"\/\/|\'\/\/|\.\/\/|\*\/\/)', $line) === 0){  //Xpath must match "(// , '(// , "// , '// , .// , *//
                   //print "skipping: $line\n";
                   continue;
                }
             } 
          }
          $xpathPrint = 1;
          $xpScore = testXPath($urlValidate, $xPathSplit[1]);
          $labelStat = calcLabel($xpScore);
          $xPathEncode = "<a href=\"$urlValidateTarget" . urlencode($xPathSplit[1]) . "\">(View Details)</a> (line: $lineNum)";
          $htmlOut .= "<strong><span class=\"label $labelStat\">$xpScore</span></strong> $xPathSplit[1] $xPathEncode</br>\n";
          $htmlOut .= checkTranslation($line);
       }
       $appDepHTML .= checkAppiumDeprecated($line, $lineNum, $appDep);
       if (strlen($appDepHTML) > 1){
          $appiumDeprecationPrint = 1;
       }
       $perfectoAppiumHTML .= checkPerfectoAppium($line, $lineNum, $perfectoAppium);
       if (strlen($perfectoAppiumHTML) > 1){
          $perfectoAppiumPrint = 1;
       }
       $inProgressHTML .= checkInProgress($line, $lineNum, $inProgress);
       if (strlen($inProgressHTML) > 1){
          $inProgressPrint = 1;
       }
    }
  fclose($objFile);
  if($xpathPrint === 1 || $appiumDeprecationPrint === 1 || $perfectoAppiumPrint === 1 || $inProgressPrint === 1){
    print "$stripFN\n";
    $htmlFileName = "<strong>$stripFN</strong><br/>";
    fwrite($outPut, $htmlFileName);
  }
  if($xpathPrint === 1){
    $htmlOut = $htmlOut . "<br/>";
    fwrite($outPut, $htmlOut);
  }
  if($appiumDeprecationPrint === 1 ){
    $appDepHTML = "<strong>Appium Deprecation Warnings in $stripFN</strong></br>" . $appDepHTML . "<br/>";
    fwrite($outPut, $appDepHTML);
  }
  if($perfectoAppiumPrint === 1 ){
    $perfectoAppiumHTML = "<strong>Appium Compatibility Warnings in $stripFN</strong></br>" . $perfectoAppiumHTML . "<br/>";
    fwrite($outPut, $perfectoAppiumHTML);
  }
  if($inProgressPrint === 1 ){
    $inProgressHTML = "<strong>In Development, but currently unsupported Appium API warnings in $stripFN</strong></br>" . $inProgressHTML . "<br/>";
    fwrite($outPut, $inProgressHTML);
  }
    
}
$htmlEND = htmlEnd();
fwrite($outPut, $htmlEND);
fclose($outPut);


?>