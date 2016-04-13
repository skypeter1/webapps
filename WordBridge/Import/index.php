<html>
<head>
    <title>Test Library</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">

    <style type="text/css">
        body {
            margin-right: auto;
            max-width: 50%;
            margin-left: auto;
        }
    </style>
</head>


<body>

<h1>Test Library</h1>

<p>This is a test library of docx to HTML conversion</p>

    <form id="fileForm"  action="index.php" method="post">
        <input id="filename" placeholder="Enter document name" class="input-lg" type="text" id="file" name="file" />
        <input class="btn-success" type="submit" title="process" />
    </form>


<script>
    $( "#fileForm" ).submit(function( event ) {
        event.preventDefault();
    });
</script>

<?php
include_once 'Word/XWPFToHTMLConverter.php';

    $filename = 'IFRSTOC.docx';
    $entryPoint = new EntryPoint($filename);
    $entryPoint->run($filename);


//if(isset($_POST['file'])){
//    $filename  = $_POST['file'];
//    $entryPoint = new EntryPoint($filename);
//    $entryPoint->run();
//}else{
//    echo "<h2>Enter the document name in the form above</h2>";
//}


class EntryPoint
{

    public function run($filename)
    {
        //Set document directory
        $progress = "";
        $workingDir = "/home/peter/Documents";
        $sourceFile = $workingDir."/".$filename;

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
        $converter::setPageBreaks(false, false);

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

            //var_dump($headlineList);

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
//        $headlineList = $converter->getHeadLineList();
//        var_dump($headlineList);

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

        echo "<p>Time Elapsed to convert this document:".$elapsedTime."</p>";
    }
}
?>


</body>
</html>