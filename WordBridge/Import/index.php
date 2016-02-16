<html>
<head>
    <title>Test Library</title>
</head>


<body>

<h1>Test Library</h1>

<p>This is a test library of docx to HTML conversion</p>

<div>
    <form>
        <input type="text" id="file" name="file" />
        <input type="submit" title="process" />
    </form>
</div>

<?php

include_once 'Word/XWPFToHTMLConverter.php';

//Set document directory
$progress = "";
$workingDir = "/home/peter/Documents";
$sourceFile = "/home/peter/Documents/Strikethrough.docx";

//Initiate time counter
$start = microtime(true);

// Init Apache POI
$converter = new XWPFToHTMLConverter($workingDir, $progress);

if (!$converter) {
    throw new Exception(
        '[WordProcessor::routine] '
        . 'Book ID ' . $this->bookId . ' cannot be processed as a working directory cannot be found.'
    );
}

// Set docx file to parse
$converter->setDocFileToParse($sourceFile);

// Convert everything to HTML
$converter->convertToHTML();

$hasToc = $converter->hasTOC();
if ($hasToc) {
    $tocNumbering = $converter->getTocNumbering();
    $TOC = $converter->getTableOfContents();
//    foreach($TOC as $entry){
//        if(strlen($entry['num']) == 0){
//            var_dump($entry);
//        }
//    }

    $contentStructure = array();
    $headlineList = $converter->getHeadLineList();

//    if(!empty($headlineList)) {
//
//        $currentChapter = '';
//        $prevItem = '';
//        $prevItemChapter = '';
//        $sequenceSections = -1;
//        $sequence = -1;
//
//        foreach ($headlineList as $key => $headline) {
//
//            if($key == 0 and $headline['tag'] == 'h1'){
//                $contentStructure[] = array(
//                    'id' => $key,
//                    'book_id' => 1,
//                    'parent_id' => 0,
//                    'type' => "volume",
//                    'header_label' => '',
//                    'header_number' => $key + 1,
//                    'header_name' => $headline['content'],
//                    'sequence' => 0,
//                    'page_ref' => '',
//                    'visible' => 1
//                );
//                $prevItem = $key;
//            }
//
//            if($headline['tag'] == 'h1' and $key != 0){
//                $currentChapter = $headline['content'];
//                $sequence = $sequence + 1;
//                $contentStructure[] = array(
//                    'id' => $key,
//                    'book_id' => 1,
//                    'parent_id' => $prevItem,
//                    'type' => "chapter",
//                    'header_label' => '',
//                    'header_number' => $key + 1,
//                    'header_name' => $headline['content'],
//                    'sequence' => $sequence,
//                    'page_ref' => '',
//                    'visible' => 1
//                );
//                $prevItemChapter = $key;
//                $sequenceSections = 0;
//            } else {
//                $sequenceSections = $sequenceSections +1;
//                $contentStructure[] = array(
//                    'id' => $key,
//                    'book_id' => 1,
//                    'parent_id' => $prevItemChapter,
//                    'type' => "section",
//                    'header_label' => '',
//                    'header_number' => $key + 1,
//                    'header_name' => $headline['content'],
//                    'sequence' => $sequenceSections,
//                    'page_ref' => '',
//                    'visible' => 1
//                );
//            }
//
//
//
//
//        }
//    }
//
//    //var_dump($headlineList);
//    var_dump($contentStructure);

}

// Get HTML pages
$pages = $converter->getHTMLPages();

foreach ($pages as $key => $page) {
    $pageContents[] = $pages[$key]->getBodyHTML();
    echo $pages[$key]->getBodyHTML();
}

// Get CSS
echo "<style>";
echo $converter->mainStyleSheet->getPagesCSS();
echo "</style>";

$elapsedTime = microtime(true) - $start;

// Save image assets
//$this->createImageAssets();
?>


<p>Time Elapsed to convert this document:<?php var_dump($elapsedTime); ?></p>

</body>
</html>